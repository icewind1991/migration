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

namespace OCA\Migration\Receiver;

use Guzzle\Http\Exception\RequestException;
use OC\Hooks\BasicEmitter;
use OCA\Migration\APIClient\Share;
use OCP\Federation\ICloudId;
use OCP\Http\Client\IClientService;

class FederatedShareReceiver extends BasicEmitter {
	/** @var Share */
	private $shareApiClient;

	/**
	 * @var ICloudId
	 */
	private $targetUser;

	/** @var IClientService */
	private $clientService;

	/**
	 * @param ICloudId $targetUser
	 * @param Share $shareApiClient
	 * @param IClientService $clientService
	 */
	public function __construct(ICloudId $targetUser, Share $shareApiClient, IClientService $clientService) {
		$this->shareApiClient = $shareApiClient;
		$this->targetUser = $targetUser;
		$this->clientService = $clientService;
	}

	public function copyShares() {
		$shares = $this->shareApiClient->listIncomingFederatedShares();
		\OC::$server->getLogger()->info($shares);
		foreach ($shares as $share) {
			try {
				$httpClient = $this->clientService->newClient();
				$httpClient->post(trim($share['remote'], '/') . '/index.php/apps/federatedfilesharing/createFederatedShare',
					[
						'body' =>
							[
								'token' => $share['share_token'],
								'shareWith' => $this->targetUser->getId()
							],
						'connect_timeout' => 10,
					]
				);
				// todo changed mountpoints, check for existence
				$this->emit('FederatedShare', 'copied');
			} catch (RequestException $e) {
				$this->emit('FederatedShare', 'error', [
					'remote' => $share['remote'],
					'name' => $share['name']
				]);
			}
		}
	}
}
