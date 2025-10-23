<?php

namespace n2n\cache\impl\fs;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\cache\CharacteristicsList;

class FileCacheStoreTest extends TestCase {
	private FsPath $tempDirFsPath;

	function setUp(): void {
		$tempfile = tempnam(sys_get_temp_dir(),'');
		if (file_exists($tempfile)) { unlink($tempfile); }
		mkdir($tempfile);

		$this->tempDirFsPath = new FsPath($tempfile);
	}

	function testRemove() {
		$store = new FileCacheStore($this->tempDirFsPath, 0777, 0777);

		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v1']), 'dato');

		$this->assertEquals('dato', $store->get('test.test', CharacteristicsList::fromArg(['k1' => 'v1']))->getData());

		$store->remove('test.test', CharacteristicsList::fromArg(['k1' => 'v1']));

		$this->assertCount(0, $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1'])));
	}


	function testRemoveAll() {
		$store = new FileCacheStore($this->tempDirFsPath, 0777, 0777);

		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v1', 'k2' => 'v2']), 'dato');

		$this->assertEquals('dato', $store->get('test.test', CharacteristicsList::fromArg(['k1' => 'v1', 'k2' => 'v2']))->getData());

		$store->removeAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1']));

		$this->assertCount(0, $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1'])));
	}

	function testClearConflict() {
		$store = new FileCacheStore($this->tempDirFsPath, 0777, 0777);

		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v1']), 'dato');

		$this->assertEquals('dato', $store->get('test.test', CharacteristicsList::fromArg(['k1' => 'v1']))->getData());

		$store->remove('test.test', CharacteristicsList::fromArg(['k1' => 'v1']));

		$this->assertCount(0, $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1'])));
	}

	function testFindAllAndRemoveAll() {
		$store = new FileCacheStore($this->tempDirFsPath, 0777, 0777);

		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v1', 'k2' => 'v2']), 'dato1');
		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v1', 'k3' => 'v3']), 'dato2');
		$store->store('test.test', CharacteristicsList::fromArg(['k1' => 'v2', 'k4' => 'v4']), 'dato3');

		$foundItems = $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1']));

		$this->assertCount(2, $foundItems);

		$this->assertEquals('dato2', $foundItems[0]->getData());
		$this->assertEquals('dato1', $foundItems[1]->getData());


		$store->removeAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1']));

		$this->assertCount(1, $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v2'])));
		$this->assertCount(0, $store->findAll('test.test', CharacteristicsList::fromArg(['k1' => 'v1'])));
	}
}