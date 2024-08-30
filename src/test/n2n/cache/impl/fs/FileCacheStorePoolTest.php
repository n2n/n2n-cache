<?php

namespace n2n\cache\impl\fs;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\cache\impl\CacheStorePools;

class FileCacheStorePoolTest extends TestCase {
	private FsPath $tempDirFsPath;

	function setUp(): void {
		$tempfile = tempnam(sys_get_temp_dir(),'');
		if (file_exists($tempfile)) { unlink($tempfile); }
		mkdir($tempfile);

		$this->tempDirFsPath = new FsPath($tempfile);
	}

	function testLookup() {
		$pool = CacheStorePools::file($this->tempDirFsPath, 0777, 0777);

		$pool->lookupCacheStore('ns\\ns1')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');
		$pool->lookupCacheStore('ns\\ns2')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');

		$this->assertTrue($this->tempDirFsPath->ext('ns-ns1')->exists());
		$this->assertCount(1, $this->tempDirFsPath->ext('ns-ns1')->getChildren());
		$this->assertTrue($this->tempDirFsPath->ext('ns-ns2')->exists());
		$this->assertCount(1, $this->tempDirFsPath->ext('ns-ns2')->getChildren());
	}


	function testClear() {
		$pool = CacheStorePools::file($this->tempDirFsPath, 0777, 0777);

		$pool->lookupCacheStore('ns\\ns1')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');
		$pool->lookupCacheStore('ns\\ns2')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');

		$this->assertCount(2, $this->tempDirFsPath->getChildren());

		$pool->clear();

		$this->assertCount(0, $this->tempDirFsPath->getChildren());
	}

}