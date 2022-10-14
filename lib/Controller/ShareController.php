<?php

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Richdocuments\Controller;

require __DIR__ . '/../../vendor/autoload.php';

use Exception;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCA\Richdocuments\Db\WopiMapper;
use OCP\Share\IShare;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ShareController extends Controller {

	private const QR_OUTPUT_imagick = true;

    /** @var LoggerInterface */
	private $logger;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IURLGenerator */
	private  $urlGenerator;

	/** @var IShareManager */
	private $shareManager;

	/** @var WopiMapper */
	private $wopiMapper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param WopiMapper $wopiMapper
	 * @param IShareManager $shareManager
	 *
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		LoggerInterface $logger,
		IRootFolder $rootFolder,
		IURLGenerator $urlGenerator,
		IShareManager $shareManager,
		WopiMapper $wopiMapper
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->shareManager = $shareManager;
		$this->wopiMapper = $wopiMapper;
	}

	/**
	 * 取得一筆外部分享連結
	 *
	 * @param string $access_token
	 * @throws Exception
	 * @return string|null
	 */
	private function getShareLink(string $access_token) {
		try {
			$wopi = $this->wopiMapper->getWopiForToken($access_token);
			if ($wopi->getEditorUid() !== $wopi->getOwnerUid()) {
				throw new NotFoundException('Not file owner');
			}

			$uid = $wopi->getEditorUid();
			$fileid = $wopi->getFileid();
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$nodes = $userFolder->getById($fileid);
			if ($nodes === []) {
				throw new NotFoundException('File not found or failed to access the file');
			}

			$node = $nodes[0];
			if (!($node instanceof File)) {
				throw new NotFoundException('No valid file');
			}

			$shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $node);

			if ($shares === []) {
				return null;
			}

			// TODO 篩選分享連結的條件, 暫定以第一筆為主
			$share = $shares[0];
			$shareUrl = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);
			return $shareUrl;

		} catch (NotFoundException $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'richdocuments', 'exception' => $e]);
		} catch (DoesNotExistException $e) {
			$this->logger->debug($e->getMessage(), ['app' => 'richdocuments', 'exception' => $e]);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['app' => 'richdocuments', 'exception' => $e]);
		}
		throw new \Exception('');
	}

	/**
	 * 回傳外部分享連結URL
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $fileId
	 * @param string $access_token
	 * @return JSONResponse
	 */
	public function getUrl(string $fileId, string $access_token) {
		try {
			$url = $this->getShareLink($access_token);
		}  catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($url === null) {
			return new JSONResponse([], HTTP::STATUS_NO_CONTENT);
		}
		return new JSONResponse([$url]);
	}

	/**
	 * 回傳外部分享連結 QR code png 圖片
	 *
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @param string $fileId
	 * @param string $access_token
	 * @return JSONResponse|DataDisplayResponse
	 */
	public function getQrCode($fileId, $access_token) {

		try {
			$url = $this->getShareLink($access_token);
		}  catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($url === null) {
			return new JSONResponse([], HTTP::STATUS_NO_CONTENT);
		}

		try {
			if (!class_exists(QRCode::class) || !class_exists(QROptions::class)) {
				throw new \Exception('Class (QRCode, QROptions) not exists');
			}

			$opt = [
				'eccLevel' => QRCode::ECC_L,
				'outputType' => QRCode::OUTPUT_IMAGE_PNG, // [data-URI]
				'version' => 5,
			];
			if (self::QR_OUTPUT_imagick) {
				if (!extension_loaded('imagick')) {
					throw new \Exception('imagick extension not loaded');
				}
				$opt['outputType'] = QRCode::OUTPUT_IMAGICK; // [image/png]
			}
			$options  = new QROptions($opt);
			$qrcode = new QRCode($options);
			$image = $qrcode->render($url);

		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['app' => 'richdocuments', 'exception' => $e]);
			return new JSONResponse(['Failed to generate qrcode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$response = new DataDisplayResponse($image, Http::STATUS_OK);
		if (self::QR_OUTPUT_imagick) {
			$response->addHeader('Content-Type', 'image/png');
		}
		return $response;
	}

	/**
	 * test QR code
	 *
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse|DataDisplayResponse
	 */
	public function testQR() {

		if (!class_exists(QRCode::class) || !class_exists(QROptions::class)) {
			return new JSONResponse(['Class not exists'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$url = $this->urlGenerator->getAbsoluteURL('/');
		try {
			$options  = new QROptions([
				'eccLevel' => QRCode::ECC_L,
				'outputType' => QRCode::OUTPUT_IMAGE_PNG,
				'version' => 5,
			]);
			$qrcode = new QRCode($options);
			$image = $qrcode->render($url);
		} catch (\Exception $e) {
			return new JSONResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$response = new DataDisplayResponse($image, Http::STATUS_OK);
		return $response;
	}
}
