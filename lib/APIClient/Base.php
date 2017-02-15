<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Migration\APIClient;

use OCA\Migration\Remote;
use OCP\Http\Client\IClientService;

class Base {
	/** @var IClientService */
	private $clientService;

	/** @var Remote */
	private $remote;

	/**
	 * Base constructor.
	 *
	 * @param IClientService $clientService
	 * @param Remote $remote
	 */
	public function __construct(IClientService $clientService, Remote $remote) {
		$this->clientService = $clientService;
		$this->remote = $remote;
	}

	protected function getHttpClient() {
		return $this->clientService->newClient();
	}

	protected function get($url, $query = []) {
		$client = $this->getHttpClient();
		$response = $client->get(trim($this->remote->getUrl(), '/') . '/' . $url, [
			'query' => $query,
			'headers' => [
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json'
			],
			'auth' => [$this->remote->getUsername(), $this->remote->getPassword()]
		]);
		return $response->getBody();
	}
}
