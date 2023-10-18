<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */



namespace OCA\Richdocuments\Listener;

use OCA\Richdocuments\PermissionManager;
use OCA\Richdocuments\Service\InitialStateService;
use OCA\Viewer\Event\LoadViewer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCA\Richdocuments\AppInfo\Application;

class LoadViewerListener implements IEventListener {
	/** @var PermissionManager */
	private $permissionManager;
	/** @var InitialStateService */
	private $initialStateService;

	private ?string $userId = null;
	/** @var Application */
	private $application;

	public function __construct(PermissionManager $permissionManager, InitialStateService $initialStateService, ?string $userId, Application $application) {
		$this->permissionManager = $permissionManager;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
		$this->application = $application;
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadViewer) {
			return;
		}
		if ($this->permissionManager->isEnabledForUser() && $this->userId !== null) {
			$this->initialStateService->provideCapabilities();
			Util::addScript('richdocuments', 'richdocuments-viewer', 'viewer');

			$currentUser = \OC::$server->getUserSession()->getUser();
			if ($currentUser !== null && $this->application->checkConvert()) {
				\OCP\Util::addScript('richdocuments', 'richdocuments-pdforganizeplugin');
				\OCP\Util::addScript('richdocuments', 'richdocuments-odfconvert');
			}
		}
	}
}
