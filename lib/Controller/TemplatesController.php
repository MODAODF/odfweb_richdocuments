<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Richdocuments\Controller;

use OCA\Richdocuments\TemplateManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OC\Files\Filesystem;
use OCP\Image as OCPImage;
use Psr\Log\LoggerInterface;

class TemplatesController extends Controller {
	private IConfig $config;
	private IL10N $l10n;
	private TemplateManager $manager;
	private IPreview $preview;
	private IMimeTypeDetector $mimeTypeDetector;
	private LoggerInterface $logger;

	/** @var int Max template size */
	private $maxSize = 20 * 1024 * 1024;

	/**
	 * Templates controller
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param TemplateManager $manager
	 * @param IPreview $preview
	 */
	public function __construct($appName,
		IRequest $request,
		IConfig $config,
		IL10N $l10n,
		TemplateManager $manager,
		IPreview $preview,
		IMimeTypeDetector $mimeTypeDetector,
		LoggerInterface $logger
	) {
		parent::__construct($appName, $request);

		$this->appName = $appName;
		$this->request = $request;
		$this->config  = $config;
		$this->l10n    = $l10n;
		$this->manager = $manager;
		$this->preview = $preview;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Get preview for a specific template
	 *
	 * @param int $fileId The template id
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 * @return DataResponse
	 * @throws NotFoundResponse
	 */
	public function getPreview($fileId,
		$x = 150,
		$y = 150,
		$a = false,
		$forceIcon = true,
		$mode = 'fill') {

		if ($fileId === '' || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$template = $this->manager->get($fileId);
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($template instanceof ISimpleFile) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$response = $this->fetchPreview($template, $x, $y, $a, $forceIcon, $mode);
		if (!$response instanceof FileDisplayResponse) {
			// 如果無法轉檔預覽，用檔案的 Thumbnail 預覽
			$response = $this->getTemplateThumbnail($template);
		}
		return $response;
	}

	/**
	 * Add a global template
	 *
	 * @return JSONResponse
	 */
	public function add() {
		$files = $this->request->getUploadedFile('files');

		if (!is_null($files)) {
			$mimeType = !empty($files['type'] ?? '') ? $files['type'] : $this->mimeTypeDetector->detect($files['tmp_name']);
			$error = $files['error'] ?? 0;

			if ($error !== 0) {
				$this->logger->error('Failed to get the uploaded file. PHP file upload error code: ' . $error);
				return new JSONResponse(
					['data' => ['message' => $this->l10n->t('Failed to upload the file')]],
					Http::STATUS_BAD_REQUEST
				);
			}

			if (is_uploaded_file($files['tmp_name']) && !Filesystem::isFileBlacklisted($files['tmp_name'])) {
				if ($files['size'] > $this->maxSize) {
					return new JSONResponse(
						['data' => ['message' => $this->l10n->t('File is too big')]],
						Http::STATUS_BAD_REQUEST
					);
				}

				if (!$this->manager->isValidTemplateMime($mimeType)) {
					return new JSONResponse(
						['data' => ['message' => $this->l10n->t('Only template files can be uploaded')]],
						Http::STATUS_BAD_REQUEST
					);
				}

				$templateName = $files['name'];
				$templateFile = file_get_contents($files['tmp_name']);

				unlink($files['tmp_name']);

				$template = $this->manager->add($templateName, $templateFile);

				return new JSONResponse(
					['data' => $template],
					Http::STATUS_CREATED
				);
			}
		}

		return new JSONResponse(
			['data' => ['message' => $this->l10n->t('Invalid file provided')]],
			Http::STATUS_BAD_REQUEST
		);
	}

	/**
	 * Delete a global template
	 *
	 * @param int $fileId
	 * @return JSONResponse
	 */
	public function delete($fileId) {
		try {
			$this->manager->delete($fileId);

			return new JSONResponse(
				['data' => ['status' => 'success']],
				Http::STATUS_NO_CONTENT
			);
		} catch (NotFoundException $e) {
			return new JSONResponse(
				['data' => ['message' => $this->l10n->t('Template not found')]],
				Http::STATUS_NOT_FOUND
			);
		}
	}

	/**
	 * @param Node $node
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 * @return DataResponse|FileDisplayResponse
	 */
	private function fetchPreview(
		Node $node,
		int $x,
		int $y,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = IPreview::MODE_FILL): Http\Response {

		if (!($node instanceof Node) || (!$forceIcon && !$this->preview->isAvailable($node))) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$node->isReadable()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$f        = $this->preview->getPreview($node, $x, $y, !$a, $mode);
			$response = new FileDisplayResponse($f, Http::STATUS_OK, ['Content-Type' => $f->getMimeType()]);
			$response->cacheFor(3600 * 24);

			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @return ISimpleFile
	 */
	private function getTemplateThumbnail($templateFile) {
		$fileId = $templateFile->getId();
		$filePath = $templateFile->getPath();
		$dataFolder = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');

		$zip = new \ZipArchive;
		$zipStat = $zip->open($dataFolder.$filePath);
		if (!$zipStat) return false;
		$im_string = $zip->getFromName('Thumbnails/thumbnail.png');
		$zip->close();
		if (!$im_string) {
			return new DataResponse([], Http::STATUS_OK); // STATUS_NOT_FOUND
		}
		$im = imagecreatefromstring($im_string);

		$instance = 'appdata_' . \OC::$server->getConfig()->getSystemValue('instanceid', null);
		$previewFolder = \OC::$server->getRootFolder()->get($instance)->get('preview/');
		try {
			$fileIdFolder = $previewFolder->newFolder((string)$fileId);
		} catch (\Exception $e) {
			$fileIdFolder = $previewFolder->get('/' . (string)$fileId);
		}

		$preview = new OCPImage();
		$preview->loadFromData($im_string);
		if (!$preview->valid()) {
			return new \InvalidArgumentException('Failed to generate preview, failed to load image');
		}

		$width = 170;
		$height = 240;
		if ($height !== $preview->height() && $width !== $preview->width()) {
			//Resize
			$widthR = $preview->width() / $width;
			$heightR = $preview->height() / $height;

			if ($widthR > $heightR) {
				$scaleH = $height;
				$scaleW = $width / $heightR;
			} else {
				$scaleH = $height / $widthR;
				$scaleW = $width;
			}
			$preview->preciseResize((int)round($scaleW), (int)round($scaleH));
		}
		$cropX = (int)floor(abs($width - $preview->width()) * 0.5);
		$cropY = 0;
		$preview->crop($cropX, $cropY, $width, $height);

		try {
			$path = (string)$width . '-' . (string)$height . '.png';
			$file = $fileIdFolder->newFile($path);
			$file->putContent($preview->data());
		} catch (NotPermittedException $e) {
			return new NotFoundException();
		}

		try {
			$resp = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
			$resp->cacheFor(3600 * 24);
			return $resp;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}
}
