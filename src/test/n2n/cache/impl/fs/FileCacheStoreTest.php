<?php

namespace n2n\cache\impl\fs;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\cache\CharacteristicsList;
use n2n\concurrency\sync\impl\fs\FileLock;
use n2n\util\HashUtils;

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
		$store = new FileCacheStore($this->tempDirFsPath, '0777', '0777');

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
		$store = new FileCacheStore($this->tempDirFsPath, '0777', '0777');

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

	/**
	 * @throws \ReflectionException
	 */
	function testClearDoNotRemoveLockFiles() {
		$name = 'holeradio/file.huii';
		$characteristicsList = CharacteristicsList::fromArg(['k1' => 'v1', 'k2' => 'v2']);


		$store = new FileCacheStore($this->tempDirFsPath, '0777', '0777');
		$store->store($name, $characteristicsList, 'dato1');

		$nameDirPath = (new \ReflectionMethod($store, 'createNameDirFsPath'))->invoke($store, $name);
		$fileName = (new \ReflectionMethod($store, 'createFileName'))->invoke($store, $characteristicsList);
		$fileFsPath = $nameDirPath->ext($fileName);
		$lock = (new \ReflectionMethod($store, 'createWriteLock'))->invoke($store, $fileFsPath);
		$this->assertInstanceOf(FileLock::class, $lock);

		$lockFsPath = new FsPath($this->tempDirFsPath->ext([FileCacheStore::LOCK_FOLDER,
				HashUtils::base36Sha256Hash((string) $fileFsPath) . FileCacheStore::LOCK_FILE_SUFFIX]));
		$this->assertTrue($lockFsPath->exists());

		$this->assertCount(2, $this->tempDirFsPath->getChildren());
		$this->assertTrue($this->tempDirFsPath->ext(['data'])->exists());
		$this->assertTrue($this->tempDirFsPath->ext(['lock'])->exists());

		$store->clear();

		$this->assertTrue($lockFsPath->exists());

		$lock->release();

		$this->assertFalse($lockFsPath->exists());

	}

}