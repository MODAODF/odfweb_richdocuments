<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2015 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments;

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
use OCP\Files\FileInfo;

class ConvertApi {

    private const API_URL = "/lool/convert-to/";

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

    public function __construct(
        AppConfig $appConfig,
        IRequest $request,
        ILogger $logger,
        View $fileview,
        IClientService $clientService,
        CapabilitiesService $capabilitiesService,
        IUserSession $userSession
    ) {
        $this->appConfig = $appConfig;
        $this->logger = $logger;
        $this->fileview = $fileview;
		$this->clientService = $clientService;
        $this->capabilitiesService = $capabilitiesService;
        $this->userSession = $userSession;

        $this->respStatus = false;
        $this->respBody = null;
    }

	/**
	 * Check convert-to is available and enabled
     *
     * @NoAdminRequired
     *
	 * @return bool
	 */
	public function isAvailable():bool {
		if (!$this->capabilitiesService->isConvertAvailable()) {
            return false;
        }
		$allowConvert = $this->appConfig->getAppValue('allowConvert');
		return $allowConvert === 'yes';
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
     * @return string|NULL
     */
    private function getApiUrl($type) {
        // TODO check type is vaild from API
        if ($type === null) return null;
        return $this->domainOnly($this->appConfig->getAppValue('public_wopi_url')) . self::API_URL . $type;
    }

    /**
     * 送出轉檔
     *
     * @param FileInfo $file source file
     * @param string $type File type to convert to
     * @return DataDisplayResponse
     */
    public function convert($fileInfo, $type) {
        $stream = $this->fileview->fopen($fileInfo->getPath(), 'r');

        $client = $this->clientService->newClient();
		$options = ['timeout' => 10];
        $options['multipart'] = [['name' => $fileInfo->getName(), 'contents' => $stream]];
        if ($this->appConfig->getAppValue('disable_certificate_verification') === 'yes') {
			$options['verify'] = false;
		}

		try {
			$resp = $client->post($this->getApiUrl($type), $options);
            if ($resp->getStatusCode() != 200) {
                throw new \Exception();
            }

            // Check converted file
            $respBody = $resp->getBody();
            $tmpFile = tmpfile();
            fwrite($tmpFile, $respBody);
            $metaDatas = stream_get_meta_data($tmpFile);
            $tmpFilename = $metaDatas['uri'];
            $size = filesize($tmpFilename);
            // $mime = mime_content_type($tmpFilename);
            fclose($tmpFile);
            if ($size > 0) {
                $this->respStatus = true;
                $this->respBody = $respBody;
            } else {
                throw new \Exception();
            }
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => 'Failed to convert file:' . $e->getMessage(),
				'level' => ILogger::INFO,
				'app' => 'richdocuments',
			]);
		}
    }

    /**
     * 取得狀態
     * @return bool
     */
    public function isSuccess() {
        return $this->respStatus;
    }

    /**
     * 取得結果
     * @return string|resource|null
     */
    public function getResponse() {
        return $this->respBody;
    }
}
