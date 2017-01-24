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

namespace OCA\Migration\Tests\Receiver;

use OC\Files\Storage\Storage;
use OCA\Migration\Receiver\EtagStorageWrapper;
use OCP\Files\Storage\IStorage;
use Test\TestCase;

\OC_App::loadApp('migration');

class EtagStorageWrapperTest extends TestCase {
	/** @var  IStorage|\PHPUnit_Framework_MockObject_MockObject */
	private $sourceStorage;
	/** @var  IStorage|\PHPUnit_Framework_MockObject_MockObject */
	private $etagStorage;

	/** @var  EtagStorageWrapper */
	private $storage;

	public function setUp() {
		parent::setUp();
		$this->sourceStorage = $this->createMock(Storage::class);
		$this->etagStorage = $this->createMock(Storage::class);
		$this->storage = new EtagStorageWrapper([
			'storage' => $this->sourceStorage,
			'etag_storage' => $this->etagStorage
		]);
	}

	public function testGetEtag() {
		$this->sourceStorage->expects($this->never())
			->method('getETag');
		$this->etagStorage->expects($this->once())
			->method('getETag')
			->willReturn('foo');

		$this->assertEquals('foo', $this->storage->getETag(''));
	}

	public function testGetMetadata() {
		$this->sourceStorage->expects($this->once())
			->method('getMetaData')
			->willReturn([
				'etag' => 'random',
				'size' => 100
			]);
		$this->etagStorage->expects($this->once())
			->method('getETag')
			->willReturn('foo');

		$this->assertEquals([
			'etag' => 'foo',
			'size' => 100
		], $this->storage->getMetaData(''));
	}
}