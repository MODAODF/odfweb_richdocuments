<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Frank Karlitschek
 * @copyright 2013-2014 Frank Karlitschek karlitschek@kde.org
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Richdocuments\AppInfo;

use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Federation\TrustedServers;
use OCA\Richdocuments\PermissionManager;
use OCA\Richdocuments\Service\FederationService;

$currentUser = \OC::$server->getUserSession()->getUser();
if($currentUser !== null) {
	/** @var PermissionManager $permissionManager */
	$permissionManager = \OC::$server->query(PermissionManager::class);
	if(!$permissionManager->isEnabledForUser($currentUser)) {
		return;
	}
}

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener(
	'OCA\Files::loadAdditionalScripts',
	function() {
		\OCP\Util::addScript('richdocuments', 'viewer');
		\OCP\Util::addStyle('richdocuments', 'viewer');
	}
);
$eventDispatcher->addListener(
	'OCA\Files_Sharing::loadAdditionalScripts',
	function() {
		\OCP\Util::addScript('richdocuments', 'viewer');
		\OCP\Util::addStyle('richdocuments', 'viewer');
	}
);

if (class_exists('\OC\Files\Type\TemplateManager')) {
	$manager = \OC_Helper::getFileTemplateManager();

	$manager->registerTemplate('application/vnd.openxmlformats-officedocument.wordprocessingml.document', dirname(__DIR__) . '/assets/docxtemplate.docx');
	$manager->registerTemplate('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', dirname(__DIR__) . '/assets/xlsxtemplate.xlsx');
	$manager->registerTemplate('application/vnd.openxmlformats-officedocument.presentationml.presentation', dirname(__DIR__) . '/assets/pptxtemplate.pptx');
	$manager->registerTemplate('application/vnd.oasis.opendocument.presentation', dirname(__DIR__) . '/assets/template.odp');
	$manager->registerTemplate('application/vnd.oasis.opendocument.text', dirname(__DIR__) . '/assets/template.odt');
	$manager->registerTemplate('application/vnd.oasis.opendocument.spreadsheet', dirname(__DIR__) . '/assets/template.ods');

}

// Whitelist the public wopi URL for iframes, required for Firefox
$publicWopiUrl = \OC::$server->getConfig()->getAppValue('richdocuments', 'public_wopi_url', '');
$publicWopiUrl = $publicWopiUrl === '' ? \OC::$server->getConfig()->getAppValue('richdocuments', 'wopi_url') : $publicWopiUrl;
if ($publicWopiUrl !== '') {
	$manager = \OC::$server->getContentSecurityPolicyManager();
	$policy = new ContentSecurityPolicy();
	$policy->addAllowedFrameDomain($publicWopiUrl);
	if (method_exists($policy, 'addAllowedFormActionDomain')) {
		$policy->addAllowedFormActionDomain($publicWopiUrl);
	}
	// TODO: remove this once figured out how to allow redirects with a frame-src nonce
	$policy->addAllowedFrameDomain('https://nextcloud2.local.dev.bitgrid.net');
	$manager->addDefaultPolicy($policy);
}

$path = '';
try {
	$path = \OC::$server->getRequest()->getPathInfo();
} catch (\Exception $e) {}
if ($path === '/apps/files/') {
	/** @var FederationService $federationService */
	$federationService = \OC::$server->query(FederationService::class);
	$remoteAccess = \OC::$server->getRequest()->getParam('richdocuments_remote_access');
	/** @var TrustedServers $trustedServers */
	$trustedServers = \OC::$server->query(TrustedServers::class);

	/*
	 * if ($remoteAccess && $trustedServers->isTrustedServer($remoteAccess)) {
		$remoteCollabora = $federationService->getRemoteCollaboraURL($remoteAccess);
		$policy->addAllowedFrameDomain($remoteAccess);
		$policy->addAllowedFrameDomain($remoteCollabora);
	}

	// TODO remove as this doesn't scale
	// better try to reload with csp set
	foreach ($trustedServers->getServers() as $server) {
		$remoteCollabora = $federationService->getRemoteCollaboraURL($server['url']);
		if ($remoteCollabora !== '') {
			$policy->addAllowedFrameDomain($server['url']);
			$policy->addAllowedFrameDomain($remoteCollabora);
		}
	}
	$manager->addDefaultPolicy($policy);
	*/
}

$app = new Application();
$app->registerProvider();
