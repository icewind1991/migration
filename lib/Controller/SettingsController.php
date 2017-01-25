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

use OCA\Migration\Migrator;
use OCA\Migration\Remote;
use OCP\AppFramework\Controller;
use OCP\Files\Folder;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {
	private $clientService;
	private $userFolder;
	private $l10n;

	public function __construct($appName, IRequest $request, IClientService $clientService, Folder $userFolder, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->clientService = $clientService;
		$this->userFolder = $userFolder;
		$this->l10n = $l10n;
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @return array|null
	 */
	public function checkRemote($remoteCloudId) {
		$remote = new Remote($remoteCloudId, '', $this->clientService);
		return $remote->getRemoteStatus();
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @param string $remotePassword
	 * @return bool
	 */
	public function checkCredentials($remoteCloudId, $remotePassword) {
		$remote = new Remote($remoteCloudId, $remotePassword, $this->clientService);
		return $remote->checkCredentials();
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $remoteCloudId
	 * @param string $remotePassword
	 */
	public function migrate($remoteCloudId, $remotePassword) {
		$remote = new Remote($remoteCloudId, $remotePassword, $this->clientService);
		$migrator = new Migrator($remote, $this->userFolder);
		$eventSource = \OC::$server->createEventSource();

		$eventSource->send('progress', [
			'step' => 'files',
			'progress' => 0,
			'description' => $this->l10n->t('Copying files...')
		]);

		$migrator->listen('Migrator', 'progress', function ($step, $progress) use ($eventSource) {
			$eventSource->send('progress', [
				'step' => $step,
				'progress' => $progress,
				'description' => $this->l10n->t('Copying files... (%d files copied)', [$progress])
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
}