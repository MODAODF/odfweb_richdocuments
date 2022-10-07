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
use \OCA\Richdocuments\AppConfig;
use \OCA\Richdocuments\ConvertApi;
use OCP\Files\Folder;

class ConvertController extends Controller {

    /** @var AppConfig */
    private $appConfig;
    /** @var View */
    private $fileview;
    /** @var ILogger */
	private $logger;
    /** @var IUserSession */
	private $userSession;
    /** @var ConvertApi */
	private $convertApi;
    /** @var Folder */
	private $userFolder;

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
        IUserSession $userSession,
        ConvertApi $convertApi,
        Folder $userFolder
    ) {
        parent::__construct($AppName, $request);
        $this->appConfig = $appConfig;
        $this->logger = $logger;
        $this->fileview = $fileview;
        $this->userSession = $userSession;
		$this->convertApi = $convertApi;
        $this->userFolder = $userFolder;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return DataDisplayResponse
     */
    public function checkStatus() {
        $user = $this->userSession->getUser();
		if (!$user || (!$user instanceof IUser)) {
            return new DataDisplayResponse('無操作權限', HTTP::STATUS_FORBIDDEN);
        }
        if (!$this->convertApi->isAvailable()) {
            return new DataDisplayResponse('未開放轉檔或伺服器無法連接，請聯絡系統管理員', HTTP::STATUS_NOT_FOUND);
        }
        return new DataDisplayResponse();
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $type
     * @param string $file
     * @param string|null $destination
     * @return DataDisplayResponse
     */
    public function convertFile($type, $file, $destination = null) {
        $filePath = explode("webdav", $file)[1];
        $fileInfo = $this->userFolder->get($filePath)->getFileInfo();
		if (!$fileInfo || $fileInfo->getSize() === 0) {
            return new DataDisplayResponse('無法取得檔案', HTTP::STATUS_NOT_FOUND);
		}

        if ($type === 'pdf') return $this->toPDF($fileInfo);
        if ($type === 'odf') return $this->toODF($fileInfo, $destination);
        return new DataDisplayResponse('轉檔類型錯誤', HTTP::STATUS_BAD_REQUEST);
    }

    /**
     * 轉存 PDF
     * @return DataDisplayResponse
     */
    private function toPDF($fileInfo) {
        $supportMimes = [
            'application/vnd.oasis.opendocument.text',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-word.document.macroEnabled.12',

            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroEnabled.12',

            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12'
        ];
        if (!in_array($fileInfo->getMimetype(), $supportMimes)) {
            return new DataDisplayResponse('此文件不支援轉為 PDF', HTTP::STATUS_BAD_REQUEST);
        }

        $this->convertApi->convert($fileInfo, 'pdf');
        if ($this->convertApi->isSuccess()) {
            $netContent = $this->convertApi->getResponse();
            return new DataDisplayResponse($netContent); // 由前端回存新檔案
        }
        return new DataDisplayResponse('轉存 PDF 失敗', HTTP::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * 轉存 ODF
     * @return DataDisplayResponse
     */
    private function toODF($fileInfo, $destination) {
        $type = $this->_getOdfType($fileInfo->getMimetype());
        if (!$type) {
            return new DataDisplayResponse('此文件不支援轉為 ODF', HTTP::STATUS_BAD_REQUEST);
        }

        if ($destination !== null && !$this->userFolder->nodeExists($destination)) {
            return new DataDisplayResponse('無法存取目標資料夾', HTTP::STATUS_NOT_FOUND);
        }

        // new filename
        $name = $fileInfo->getName();
        $nameArr = explode('.', $name);
        array_pop($nameArr); // remove ext
        $newName = implode('.', $nameArr);
        $newName .= '.' . $type;

        try {
            $this->convertApi->convert($fileInfo, $type);
            if (!$this->convertApi->isSuccess()) {
                throw '';
            }

            // Create new file
            $newContent = $this->convertApi->getResponse();
            $path = $destination . '/' . $newName;
            $this->userFolder->newFile($path, $newContent);
        } catch (\Exception $e) {
            if (!$e->getMessage()) $msg = "轉存 ODF 失敗";
            return new DataDisplayResponse($msg, HTTP::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataDisplayResponse('檔案已儲存 '.$path);
    }

    private function _getOdfType($mime) {
        $supportMimes = [
            'odt' => [
                'application/vnd.oasis.opendocument.text',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-word.document.macroEnabled.12'
            ],
            'ods' => [
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel.sheet.macroEnabled.12'
            ],
            'odp' => [
                'application/vnd.oasis.opendocument.presentation',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-powerpoint.presentation.macroEnabled.12'
            ]
        ];
        foreach ($supportMimes as $key => $m) {
            if (in_array($mime, $m)) return $key;
        }
    }
}
