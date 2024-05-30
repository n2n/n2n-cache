<?php

namespace n2n\cache\impl\psr;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\cache\impl\psr\Psr16CacheStore;
use n2n\cache\impl\CacheStores;
use n2n\cache\impl\FileCacheStore;

class Psr16FileCacheStoreTest extends TestCase {
	private FsPath $tempDirFsPath;

	function setUp(): void {
		$tempfile = tempnam(sys_get_temp_dir(), '');
		if (file_exists($tempfile)) {
			unlink($tempfile);
		}
		mkdir($tempfile);

		$this->tempDirFsPath = new FsPath($tempfile);
	}

	function testDelete() {
		$store = CacheStores::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1']);

		$this->assertEquals(['k1' => 'v1'], $store->get('test.test', [])->getData());

		$store->delete('test.test');
		$this->assertNull($store->get('test.test', []));
	}


	function testDeleteMultiple() {
		$store = CacheStores::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1', 'k2' => 'v2']);
		$store->set('test.test', ['k3' => 'v1', 'k4' => 'v2']);
		$store->set('test.test2', ['k3' => 'v1', 'k4' => 'v2']);

		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $store->get('test.test', [])->getData());
		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $store->get('test.test2', [])->getData());

		$store->deleteMultiple(['test.test', 'test.test2']);

		$this->assertNull($store->get('test.test', []));
		$this->assertNull($store->get('test.test2', []));
	}

	function testGetMultiple() {
		$store = CacheStores::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1']);
		$store->set('test.test2', ['k3' => 'v1', 'k4' => 'v2']);

		$getMultiple = $store->getMultiple(['test.test', 'test.test2'], []);
		$this->assertEquals(['k1' => 'v1'], $getMultiple[0]->getData());
		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $getMultiple[1]->getData());

	}

	function testClear() {
		$store = CacheStores::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1', 'k2' => 'v2']);
		$store->set('test.test1', ['k1' => 'v1', 'k3' => 'v3']);
		$store->set('test.test2', ['k1' => 'v2', 'k4' => 'v4']);

		$store->clear();

		$this->assertNull($store->get('test.test', []));
		$this->assertNull($store->get('test.test1', []));
		$this->assertNull($store->get('test.test2', []));
	}
}