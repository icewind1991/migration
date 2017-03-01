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

use OC\Files\Storage\DAV;
use OCA\Migration\Storage\RemoteCloudStorage;
use OCP\Federation\ICloudId;
use OCP\Http\Client\IClientService;

class Remote {
	private $cloudId;

	private $password;

	private $clientService;

	private $status = false;

	/**
	 * @param ICloudId $cloudId
	 * @param string $password
	 * @param IClientService $clientService
	 */
	public function __construct(ICloudId $cloudId, $password, IClientService $clientService) {
		$this->cloudId = $cloudId;
		$this->password = $password;
		$this->clientService = $clientService;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return str_replace('https://', '', str_replace('http://', '', $this->cloudId->getRemote()));
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->cloudId->getUser();
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @return array|null
	 */
	public function getRemoteStatus() {
		if ($this->status === false) {
			try {
				$response = $this->downloadStatus('https://' . $this->getUrl() . '/status.php');
				$protocol = 'https';
				if (!$response) {
					$response = $this->downloadStatus('http://' . $this->getUrl() . '/status.php');
					$protocol = 'http';
				}
				$status = json_decode($response, true);
				if ($status) {
					$status['protocol'] = $protocol;
				}
				$this->status = $status;
			} catch (\Exception $e) {
				$this->status = null;
			}
		}
		return $this->status;
	}

	private function downloadStatus($url) {
		try {
			$request = $this->clientService->newClient()->get($url);
			return $request->getBody();
		} catch (\Exception $e) {
			return false;
		}
	}

	public function checkCredentials() {
		$storage = $this->getRemoteStorage();
		if (!$storage) {
			return false;
		} else {
			return $storage->test();
		}
	}

	public function getRemoteStorage() {
		$status = $this->getRemoteStatus();
		if (!$status) {
			return null;
		}
		return new RemoteCloudStorage([
			'host' => $this->getUrl() . '/remote.php/files',
			'secure' => ($status['protocol'] === 'https'),
			'user' => $this->getUsername(),
			'password' => $this->getPassword()
		]);
	}

	/**
	 * @return ICloudId
	 */
	public function getCloudId() {
		return $this->cloudId;
	}
}
