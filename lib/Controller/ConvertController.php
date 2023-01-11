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

use OCP\IUser;
use OCP\IUserSession;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use \OCA\Richdocuments\AppConfig;
use \OCA\Richdocuments\ConvertApi;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use \OC\Files\Node\File;
use \OC\Files\Node\Folder;
use \OC\Files\FileInfo;

class ConvertController extends Controller {

    /** @var AppConfig */
    private $appConfig;
    /** @var ILogger */
	private $logger;
    /** @var IUserSession */
	private $userSession;
    /** @var ConvertApi */
	private $convertApi;
	/** @var IRootFolder */
	private $rootFolder;

    private $fileInfo = null;
    private $targetFolder = null;

    public function __construct(
        string $AppName,
        AppConfig $appConfig,
        IRequest $request,
        ILogger $logger,
        IUserSession $userSession,
        ConvertApi $convertApi,
        IRootFolder $rootFolder
    ) {
        parent::__construct($AppName, $request);
        $this->appConfig = $appConfig;
        $this->logger = $logger;
        $this->userSession = $userSession;
		$this->convertApi = $convertApi;
        $this->rootFolder = $rootFolder;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * @return DataDisplayResponse
     */
    public function checkStatus() {
        if (!$this->convertApi->isAvailable()) {
            return new DataDisplayResponse('The [convet-to] is not working or unavailable, please contact the system administrator', HTTP::STATUS_NOT_FOUND);
        }
        return new DataDisplayResponse();
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * @param string $type
     * @param string $fileid
     * @param string $destination
     * @param string|null $sharingToken
     * @return DataDisplayResponse
     */
    public function convertFile($type, $fileid, $destination = '/', $sharingToken = null) {
        $file = null;
        $targetFolder = null;
        try {
            $user = $this->userSession->getUser();
            if (!is_null($sharingToken)) {
                $share = \OC::$server->getShareManager()->getShareByToken($sharingToken);
                $shareNode = $share->getNode();
                $file = $shareNode->getById($fileid);
                $file = count($file) > 0 ? $file[0] : null;
                $targetFolder = $shareNode->get($destination);
            } else if ($user instanceof IUser) {
                $uesrFolder = $this->rootFolder->getUserFolder($user->getUid());
                $file = $uesrFolder->getById($fileid);
                $file = count($file) > 0 ? $file[0] : null;
                $targetFolder = $uesrFolder->get($destination);
            }
            if(!($file instanceof File) || !($targetFolder instanceof Folder)) {
                throw new \Exception();
            }

            $fileInfo = $file->getFileInfo();
            if (!($fileInfo instanceof FileInfo) || $fileInfo->getSize() === 0) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            return new DataDisplayResponse('Unable to get file', HTTP::STATUS_NOT_FOUND);
        }

        $this->fileInfo = $fileInfo;
        $this->targetFolder = $targetFolder;
        if ($type === 'pdf') return $this->toPDF();
        if ($type === 'odf') return $this->toODF();
        return new DataDisplayResponse('Error: [convert-to] type is not set or unsupported.', HTTP::STATUS_BAD_REQUEST);
    }

    /**
     * 轉存 PDF
     * @return DataDisplayResponse
     */
    private function toPDF() {
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
        if (!in_array($this->fileInfo->getMimetype(), $supportMimes)) {
            return new DataDisplayResponse('Error: conversion of this file is not supported.', HTTP::STATUS_BAD_REQUEST);
        }

        try {
            $this->convertApi->convert($this->fileInfo, 'pdf');
            if (!$this->convertApi->isSuccess()) throw new \Exception();

            $newContent = $this->convertApi->getResponse();
            $uniqueName = $this->_getUniqueName($this->fileInfo->getName(), $this->targetFolder, 'pdf');
            $this->targetFolder->newFile($uniqueName, $newContent);
        } catch (NotPermittedException $e) {
            $failMsg = 'Could not create file';
        } catch (\Exception $e) {
        }

        if (isset($e)) {
            $this->logger->logException($e, [
                'message' => $e->getMessage(),
                'level' => ILogger::ERROR,
                'app' => 'richdocuments',
            ]);
            $failMsg = $failMsg ?? 'Failed to convert file';
            return new DataDisplayResponse($failMsg, HTTP::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataDisplayResponse($uniqueName, Http::STATUS_OK);
    }

    /**
     * 轉存 ODF
     * @return DataDisplayResponse
     */
    private function toODF() {
        $type = $this->_getOdfType($this->fileInfo->getMimetype());
        if (!$type) {
            return new DataDisplayResponse('Error: conversion of this file is not supported.', HTTP::STATUS_BAD_REQUEST);
        }

        try {
            $this->convertApi->convert($this->fileInfo, $type);
            if (!$this->convertApi->isSuccess()) throw new \Exception();

            $newContent = $this->convertApi->getResponse();
            $uniqueName = $this->_getUniqueName($this->fileInfo->getName(), $this->targetFolder, $type);
            $this->targetFolder->newFile($uniqueName, $newContent);
        } catch (NotPermittedException $e) {
			$failMsg = 'Could not create file';
        } catch (\Exception $e) {
        }

        if (isset($e)) {
            $this->logger->logException($e, [
				'message' => $e->getMessage(),
				'level' => ILogger::ERROR,
				'app' => 'richdocuments',
			]);
            $failMsg = $failMsg ?? 'Failed to convert file';
            return new DataDisplayResponse($failMsg, HTTP::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataDisplayResponse($uniqueName, Http::STATUS_OK);
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

    /**
     * @param string $filename
     * @param Folder $folder
     * @param string $extType
     * @return string
     */
    private function _getUniqueName($filename, $folder, $extType) {
        $arr = explode('.', $filename);
        array_pop($arr);
        array_push($arr, $extType);
        $name = implode('.', $arr);
        $name = $folder->getNonExistingName($name);
        return $name;
    }
}
