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

namespace OCA\Migration;

use OC\Files\Storage\Wrapper\Jail;
use OC\Hooks\BasicEmitter;
use OCA\Files_Sharing\External\Manager;
use OCA\Migration\APIClient\Share;
use OCA\Migration\Receiver\FederatedShareReceiver;
use OCA\Migration\Receiver\FileReceiver;
use OCP\Federation\ICloudId;
use OCP\Files\Folder;
use OCP\Files\IHomeStorage;
use OCP\Http\Client\IClientService;

class Migrator extends BasicEmitter {
	/** @var  Remote */
	private $remote;
	/** @var Folder */
	private $userFolder;
	/** @var IClientService */
	private $clientService;
	/** @var ICloudId */
	private $targetUser;
	/** @var Manager */
	private $externalShareManager;

	public function __construct(Remote $remote,
								Folder $userFolder,
								IClientService $clientService,
								ICloudId $targetUser,
								Manager $externalShareManager
	) {
		$this->remote = $remote;
		$this->userFolder = $userFolder;
		$this->clientService = $clientService;
		$this->targetUser = $targetUser;
		$this->externalShareManager = $externalShareManager;
	}

	public function migrate() {
		$userStorage = $this->userFolder->getStorage();
		if (!$userStorage->instanceOfStorage(IHomeStorage::class)) {
			throw new \Exception('expected home storage');
		}
		$targetStorage = new Jail([
			'storage' => $userStorage,
			'root' => 'files'
		]);

		$remoteStorage = $this->remote->getRemoteStorage();
		if (!$remoteStorage) {
			throw new \Exception('Invalid remote');
		}
		$fileMigrator = new FileReceiver($remoteStorage, $targetStorage);

		$fileCount = 0;
		$fileMigrator->listen('File', 'copied', function () use (&$fileCount) {
			$fileCount++;
			if (($fileCount % 10) === 0) {
				$this->emit('Migrator', 'progress', ['files', $fileCount]);
			}
		});

		$fileMigrator->copyFiles();

		$shareApiClient = new Share($this->clientService, $this->remote);
		$federatedShareReceiver = new FederatedShareReceiver($this->targetUser, $shareApiClient, $this->clientService, $this->externalShareManager, $this->userFolder);

		$shareCount = 0;
		$federatedShareReceiver->listen('FederatedShare', 'copied', function () use (&$shareCount) {
			$shareCount++;
			$this->emit('Migrator', 'progress', ['federated_shares', $shareCount]);
		});

		$federatedShareReceiver->copyShares();
	}
}
