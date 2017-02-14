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
use OCP\Http\Client\IClientService;

class Remote {
	private $url;

	private $username;

	private $password;

	private $clientService;

	/**
	 * @param string $cloudId
	 * @param string $password
	 */
	public function __construct($cloudId, $password, IClientService $clientService) {
		//TODO better way to resolve cloud ids
		list($username, $url) = explode('@', $cloudId);
		$this->url = $url;
		$this->username = $username;
		$this->password = $password;
		$this->clientService = $clientService;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
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
		try {
			$response = $this->downloadStatus('https://' . $this->url . '/status.php');
			$protocol = 'https';
			if (!$response) {
				$response = $this->downloadStatus('http://' . $this->url . '/status.php');
				$protocol = 'http';
			}
			$status = json_decode($response, true);
			if ($status) {
				$status['protocol'] = $protocol;
			}
			return $status;
		} catch (\Exception $e) {
			return null;
		}
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
		return new DAV([
			'host' => $this->getUrl() . '/remote.php/files',
			'secure' => ($status['protocol'] === 'https'),
			'user' => $this->getUsername(),
			'password' => $this->getPassword()
		]);
	}
}
