<?php
/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Migration\Controller;

use OCA\Files_Sharing\External\Manager;
use OCA\Migration\Migrator;
use OCA\Migration\Remote;
use OCP\AppFramework\Controller;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Folder;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class SettingsController extends Controller {
	private $clientService;
	private $userFolder;
	private $l10n;
	private $cloudIdManager;
	private $userSession;
	private $urlGenerator;
	private $externalShareManager;
	private $connection;

	/**
	 * SettingsController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IClientService $clientService
	 * @param Folder $userFolder
	 * @param IL10N $l10n
	 * @param ICloudIdManager $cloudIdManager
	 * @param IUserSession $userSession
	 * @param IURLGenerator $urlGenerator
	 * @param Manager $externalShareManager
	 */
	public function __construct($appName,
								IRequest $request,
								IClientService $clientService,
								Folder $userFolder,
								IL10N $l10n,
								ICloudIdManager $cloudIdManager,
								IUserSession $userSession,
								IURLGenerator $urlGenerator,
								Manager $externalShareManager,
								IDBConnection $connection
	) {
		parent::__construct($appName, $request);
		$this->clientService = $clientService;
		$this->userFolder = $userFolder;
		$this->l10n = $l10n;
		$this->cloudIdManager = $cloudIdManager;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->externalShareManager = $externalShareManager;
		$this->connection = $connection;
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @return array|null
	 */
	public function checkRemote($remoteCloudId) {
		$remote = new Remote($this->cloudIdManager->resolveCloudId($remoteCloudId), '', $this->clientService);
		return $remote->getRemoteStatus();
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @param string $remotePassword
	 * @return bool
	 */
	public function checkCredentials($remoteCloudId, $remotePassword) {
		$remote = new Remote($this->cloudIdManager->resolveCloudId($remoteCloudId), $remotePassword, $this->clientService);
		return $remote->checkCredentials();
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $remoteCloudId
	 * @param string $remotePassword
	 */
	public function migrate($remoteCloudId, $remotePassword) {
		$remote = new Remote($this->cloudIdManager->resolveCloudId($remoteCloudId), $remotePassword, $this->clientService);
		$targetUser = $this->cloudIdManager->getCloudId($this->userSession->getUser()->getUID(), $this->urlGenerator->getAbsoluteURL('/'));
		$migrator = new Migrator($remote, $this->userFolder, $this->clientService, $targetUser, $this->externalShareManager, $this->connection, $this->cloudIdManager);
		$eventSource = \OC::$server->createEventSource();

		$eventSource->send('progress', [
			'step' => 'files',
			'progress' => 0,
			'description' => $this->l10n->t('Copying data...')
		]);

		$migrator->listen('Migrator', 'progress', function ($step, $progress) use ($eventSource) {
			$eventSource->send('progress', [
				'step' => $step,
				'progress' => $progress,
				'description' => $this->formatProgress($step, $progress)
			]);
		});

		try {
			$migrator->migrate();
			$eventSource->send('done', 'done');
		} catch (\Exception $e) {
			$eventSource->send('error', $e->getMessage());
		}

		$eventSource->close();
	}

	private function formatProgress($step, $progress) {
		switch ($step) {
			case 'files':
				return $this->l10n->t('Copying files... (%d files copied)', [$progress]);
			case 'federated_shares':
				return $this->l10n->t('Copying federated shares... (%d shares copied)', [$progress]);
			default:
				return $this->l10n->t('Copying...');
		}
	}
}
