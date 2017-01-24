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

use OCA\Migration\Remote;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;
use OCP\IRequest;

class SettingsController extends Controller {
	private $clientService;

	public function __construct($appName, IRequest $request, IClientService $clientService) {
		parent::__construct($appName, $request);
		$this->clientService = $clientService;
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @return array|null
	 */
	public function checkRemote($remoteCloudId) {
		//TODO better way to resolve cloud ids
		list($user, $host) = explode('@', $remoteCloudId);
		$remote = new Remote($host, $user, '', $this->clientService);
		return $remote->getRemoteStatus();
	}

	/**
	 * @NoAdminRequired
	 * @param string $remoteCloudId
	 * @param string $remotePassword
	 * @return bool
	 */
	public function checkCredentials($remoteCloudId, $remotePassword) {
		//TODO better way to resolve cloud ids
		list($user, $host) = explode('@', $remoteCloudId);
		$remote = new Remote($host, $user, $remotePassword, $this->clientService);
		return $remote->checkCredentials();
	}
}