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

class Share extends OCS {
	public function __construct(IClientService $clientService, Remote $remote) {
		parent::__construct($clientService, $remote, 'apps/files_sharing/api/v1');
	}

	public function listIncomingFederatedShares() {
		$result = json_decode($this->get('remote_shares'), true);
		if (!$result || !isset($result['ocs']) || !isset($result['ocs']['meta']) || $result['ocs']['meta']['status'] !== 'ok') {
			throw new \Exception('Failed to list federated shares');
		}
		return $result['ocs']['data'];
	}

	public function listOutgoingFederatedShares() {
		$result = json_decode($this->get('shares'), true);
		if (!$result || !isset($result['ocs']) || !isset($result['ocs']['meta']) || $result['ocs']['meta']['status'] !== 'ok') {
			throw new \Exception('Failed to list federated shares');
		}
		return array_filter($result['ocs']['data'], function($share) {
			return $share['share_type'] === 6;
		});
	}

	public function revokeShare($id, $token) {
		$httpClient = $this->getHttpClient();
		$httpClient->post(trim($this->remote->getCloudId()->getRemote(), '/') . '/ocs/v1.php/cloud/shares/' . $id . '/revoke',
			[
				'body' => [
					'token' => $token
				],
				'headers' => [
					'OCS-APIREQUEST' => 'true'
				],
				'connect_timeout' => 10,
			]
		);
	}
}
