<?php

namespace n2n\cache\impl;

use n2n\util\io\stream\impl\FileResourceStream;
use n2n\util\io\fs\CouldNotAchieveFlockException;
use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;

class CacheFileLockTest extends TestCase {

	function testCacheFileLock() {
		$lockFileFsPath = new FsPath(tempnam(sys_get_temp_dir(),''));
		$lockFileFsPath->delete();

		$this->assertTrue(!$lockFileFsPath->exists());

		$lock = new CacheFileLock(new FileResourceStream($lockFileFsPath, 'w', LOCK_EX));

		$this->assertTrue($lockFileFsPath->exists());
		$this->assertTrue($lockFileFsPath->isFile());

		try {
			$lock2 = new CacheFileLock(new FileResourceStream($lockFileFsPath, 'w', LOCK_EX | LOCK_NB));
			$this->fail();
		} catch (CouldNotAchieveFlockException $e) {
			$this->assertTrue(true);
		}

		$this->assertTrue($lockFileFsPath->exists());

		$lock->release(true);

		$this->assertTrue(!$lockFileFsPath->exists());
	}


	function testKeepFile() {
		$lockFileFsPath = new FsPath(tempnam(sys_get_temp_dir(),''));
		$lockFileFsPath->delete();

		$this->assertTrue(!$lockFileFsPath->exists());

		$lock = new CacheFileLock(new FileResourceStream($lockFileFsPath, 'w', LOCK_EX));

		$this->assertTrue($lockFileFsPath->exists());
		$this->assertTrue($lockFileFsPath->isFile());

		$lock->release();

		$this->assertTrue($lockFileFsPath->exists());
	}
}