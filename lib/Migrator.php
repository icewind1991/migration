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
use OCA\Migration\Receiver\FileReceiver;
use OCP\Files\Folder;
use OCP\Files\IHomeStorage;

class Migrator extends BasicEmitter {
	/** @var  Remote */
	private $remote;
	/** @var Folder */
	private $userFolder;

	public function __construct(Remote $remote, Folder $userFolder) {
		$this->remote = $remote;
		$this->userFolder = $userFolder;
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
	}
}