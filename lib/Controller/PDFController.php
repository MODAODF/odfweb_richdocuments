<?php

declare(strict_types=1);
/**
 * @author Lukas Reschke
 * @copyright 2014 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Richdocuments\Controller;

use OC\Files\View;
use OCP\IUser;
use OCP\IUserSession;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\Http\Client\IClientService;
use \OCA\Richdocuments\AppConfig;
use \OCA\Richdocuments\Service\CapabilitiesService;

class PDFController extends Controller
{

    private const API_CONVERT_PDF = "/lool/convert-to/pdf";

    /** @var AppConfig */
    private $appConfig;
    /** @var View */
    private $fileview;
    /** @var ILogger */
	private $logger;
    /** @var CapabilitiesService */
	private $capabilitiesService;
	/** @var IClientService */
	private $clientService;
    /** @var IUserSession */
	private $userSession;

    /**
     * @param string $AppName
     * @param IRequest $request
     * @param IURLGenerator $urlGenerator
     */
    public function __construct(
        string $AppName,
        AppConfig $appConfig,
        IRequest $request,
        ILogger $logger,
        View $fileview,
        IClientService $clientService,
        CapabilitiesService $capabilitiesService,
        IUserSession $userSession
    ) {
        parent::__construct($AppName, $request);
        $this->appConfig = $appConfig;
        $this->logger = $logger;
        $this->fileview = $fileview;
		$this->clientService = $clientService;
        $this->capabilitiesService = $capabilitiesService;
        $this->userSession = $userSession;
    }

	/**
	 * Check convert-to is available and enabled
     *
     * @NoAdminRequired
     *
	 * @return bool
	 */
	public function checkConvert ():bool {
		if (!$this->capabilitiesService->isConvertAvailable()) {
            return false;
        }
		$allowConvert = $this->appConfig->getAppValue('allowConvert');
		return $allowConvert === 'yes';
	}

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return DataDisplayResponse
     */
    public function checkConnect() {
        if (!$this->userLogin()) {
            return new DataDisplayResponse('無操作權限', HTTP::STATUS_FORBIDDEN);
        }
        if (!$this->checkConvert()) {
            return new DataDisplayResponse('未開放轉檔或伺服器無法連接，請聯絡系統管理員', HTTP::STATUS_NOT_FOUND);
        }
        return new DataDisplayResponse();
    }

    /**
     * Strips the path and query parameters from the URL.
     *
     * @param string $url
     * @return string
     */
    private function domainOnly($url) {
        $parsed_url = parse_url($url);
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host   = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port   = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $path   = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        return "$scheme$host$port$path";
    }

    /**
     * @return bool
     */
    public function userLogin() {
        $user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
            return false;
        }
        return ($user ? true : false);
    }

    /**
     * @return string
     */
    private function getApiUrl() {
        return $this->domainOnly($this->appConfig->getAppValue('public_wopi_url')) . self::API_CONVERT_PDF;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $filename
     * @return DataDisplayResponse
     */
    public function toPDF($file) {
        if (!$file) return new DataDisplayResponse('無法取得檔案', HTTP::STATUS_NOT_FOUND);

        $filePath = explode("webdav", $file)[1];
        $fileNode = \OC::$server->getUserFolder()->get($filePath);
        $fileInfo = $this->fileview->getFileInfo($fileNode->getPath());
		if (!$fileInfo || $fileInfo->getSize() === 0) {
            return new DataDisplayResponse('無法取得檔案', HTTP::STATUS_NOT_FOUND);
		}

        $stream = $this->fileview->fopen($fileNode->getPath(), 'r');

        $client = $this->clientService->newClient();
		$options = ['timeout' => 10];
        $options['multipart'] = [['name' => $fileInfo->getName(), 'contents' => $stream]];
        if ($this->appConfig->getAppValue('disable_certificate_verification') === 'yes') {
			$options['verify'] = false;
		}
		try {
			$resp = $client->post($this->getApiUrl(), $options);
            $respBody = $resp->getBody();
            if ($resp->getStatusCode() != 200) {
                throw new \Exception();
            }

            // Check converted file
            $tmpFile = tmpfile();
            fwrite($tmpFile, $respBody);
            $metaDatas = stream_get_meta_data($tmpFile);
            $tmpFilename = $metaDatas['uri'];
            $size = filesize($tmpFilename);
            $mime = mime_content_type($tmpFilename);
            fclose($tmpFile);
            if ($size == 0 || $mime !== 'application/pdf') {
                throw new \Exception();
            }

		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => 'Failed to convert file to PDF',
				'level' => ILogger::INFO,
				'app' => 'richdocuments',
			]);
			return false;
		}

        $response = new DataDisplayResponse($respBody);
        return $response;
    }
}
