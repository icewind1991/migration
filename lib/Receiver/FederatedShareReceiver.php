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
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\Files_Sharing\External\Manager as ExternalManager;
use OCA\Migration\APIClient\Share;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Folder;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;

class FederatedShareReceiver extends BasicEmitter {
	/** @var Share */
	private $shareApiClient;

	/**
	 * @var ICloudId
	 */
	private $targetUser;

	/** @var IClientService */
	private $clientService;

	/** @var ExternalManager */
	private $externalShareManager;

	/** @var Folder */
	private $userFolder;

	/** @var IDBConnection */
	private $connection;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/**
	 * @param ICloudId $targetUser
	 * @param Share $shareApiClient
	 * @param IClientService $clientService
	 * @param ExternalManager $externalShareManager
	 * @param Folder $userFolder
	 * @param IDBConnection $connection
	 * @param ICloudIdManager $cloudIdManager
	 */
	public function __construct(ICloudId $targetUser,
								Share $shareApiClient,
								IClientService $clientService,
								ExternalManager $externalShareManager,
								Folder $userFolder,
								IDBConnection $connection,
								ICloudIdManager $cloudIdManager
	) {
		$this->shareApiClient = $shareApiClient;
		$this->targetUser = $targetUser;
		$this->clientService = $clientService;
		$this->externalShareManager = $externalShareManager;
		$this->userFolder = $userFolder;
		$this->connection = $connection;
		$this->cloudIdManager = $cloudIdManager;
	}

	public function copyShares() {
		$shares = $this->shareApiClient->listIncomingFederatedShares();
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
				$this->emit('FederatedShare', 'copied');
			} catch (RequestException $e) {
				$this->emit('FederatedShare', 'error', [
					'remote' => $share['remote'],
					'name' => $share['name']
				]);
			}
		}

		$keys = array_map(function ($share) {
			return $share['name'] . '::' . $share['remote'];
		}, $shares);
		$mountPoints = array_map(function ($share) {
			return $share['mountpoint'];
		}, $shares);

		$mountPointMap = array_combine($keys, $mountPoints);

		$openShares = $this->externalShareManager->getOpenShares();
		foreach ($openShares as $openShare) {
			$key = $openShare['name'] . '::' . $openShare['remote'];
			// check if it's a migrated share
			if (isset($mountPointMap[$key])) {
				$this->externalShareManager->acceptShare($openShare['id']);
			}
		}

		$acceptedShares = $this->externalShareManager->getAcceptedShares();

		foreach ($acceptedShares as $acceptedShare) {
			$key = $acceptedShare['name'] . '::' . $acceptedShare['remote'];
			if (isset($mountPointMap[$key])) {
				$targetMountPoint = $mountPointMap[$key];
				if ($targetMountPoint !== $acceptedShare['mountpoint']) {
					$this->externalShareManager->setMountPoint(
						$this->userFolder->getFullPath($acceptedShare['mountpoint']),
						$this->userFolder->getFullPath($targetMountPoint)
					);
				}
			}
		}

		$outgoingShares = $this->shareApiClient->listOutgoingFederatedShares();

		foreach ($outgoingShares as $outgoingShare) {
			try {
				// first we recreate the local share data in the db
				$sourceId = $this->userFolder->get($outgoingShare['path'])->getId();
				$shareId = $this->addShareToDB(
					$sourceId,
					$outgoingShare['item_type'],
					$outgoingShare['share_with'],
					$this->targetUser->getUser(),
					$this->targetUser->getUser(),
					$outgoingShare['permissions'],
					$outgoingShare['token']
				);

				$remoteCloudId = $this->cloudIdManager->resolveCloudId($outgoingShare['share_with']);

				// then we notify the receiving end that we are the new owner
				$httpClient = $this->clientService->newClient();
				$httpClient->post(trim($remoteCloudId->getRemote(), '/') . '/ocs/v1.php/cloud/shares/' . $outgoingShare['id'] . '/modify',
					[
						'body' => [
							'token' => $outgoingShare['token'],
							'remote' => $this->targetUser->getId(),
							'remote_id' => $shareId
						],
						'headers' => [
							'OCS-APIREQUEST' => 'true'
						],
						'connect_timeout' => 10,
					]
				);

				// finally we tell the migration source that it's no longer the owner of the share
				$this->shareApiClient->revokeShare($outgoingShare['id'], $outgoingShare['token']);

				$this->emit('FederatedShare', 'copied');
			} catch (RequestException $e) {
				$this->emit('FederatedShare', 'error', [
					'remote' => $outgoingShare['remote'],
					'name' => $outgoingShare['name']
				]);
			}
		}
	}

	private function addShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $uidOwner, $permissions, $token) {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(FederatedShareProvider::SHARE_TYPE_REMOTE))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_owner', $qb->createNamedParameter($uidOwner))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('stime', $qb->createNamedParameter(time()));

		/*
		 * Added to fix https://github.com/owncloud/core/issues/22215
		 * Can be removed once we get rid of ajax/share.php
		 */
		$qb->setValue('file_target', $qb->createNamedParameter(''));

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}
}
