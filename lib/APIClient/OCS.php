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

class OCS extends Base {
	private $baseUrl;

	public function __construct(IClientService $clientService, Remote $remote, $baseUrl) {
		parent::__construct($clientService, $remote);
		$this->baseUrl = 'ocs/v1.php/' . trim($baseUrl, '/');
	}

	protected function get($url, array $query = []) {
		return parent::get($this->baseUrl . '/' . $url, $query);
	}

	protected function delete($url, array $query = []) {
		return parent::delete($this->baseUrl . '/' . $url, $query);
	}
}
