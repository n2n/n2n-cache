<?php

namespace n2n\cache\impl\psr;

use PHPUnit\Framework\TestCase;
use n2n\util\io\fs\FsPath;
use n2n\cache\impl\fs\FileCacheStore;
use n2n\cache\impl\PsrDecorators;
use Psr\SimpleCache\InvalidArgumentException;
use n2n\cache\CacheStore;
use n2n\cache\impl\persistence\DboCacheStore;
use n2n\core\config\PersistenceUnitConfig;
use n2n\impl\persistence\meta\sqlite\SqliteDialect;
use n2n\persistence\Pdo;
use n2n\test\DbTestPdoUtil;
use n2n\cache\impl\persistence\DboCacheDataSize;

class Psr16DecoratorTest extends TestCase {
	private FsPath $tempDirFsPath;
	private Pdo $pdo;
	private DbTestPdoUtil $pdoUtil;

	function setUp(): void {
		$tempfile = tempnam(sys_get_temp_dir(), '');
		if (file_exists($tempfile)) {
			unlink($tempfile);
		}
		mkdir($tempfile);

		$this->tempDirFsPath = new FsPath($tempfile);

		$config = new PersistenceUnitConfig('holeradio', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, SqliteDialect::class);
		$this->pdo = new Pdo('holeradio', new SqliteDialect($config));
		$this->pdoUtil = new DbTestPdoUtil($this->pdo);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testDelete() {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1']);

		$this->assertEquals(['k1' => 'v1'], $store->get('test.test', 'ValueIfNotFound'));

		$store->delete('test.test');
		$this->assertNull($store->get('test.test', null));
		$this->assertEquals('ValueIfNotFound', $store->get('test.test', 'ValueIfNotFound'));
	}


	/**
	 * @throws InvalidArgumentException
	 */
	function testDeleteMultiple() {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1', 'k2' => 'v2']);
		$store->set('test.test', ['k3' => 'v1', 'k4' => 'v2']);
		$store->set('test.test2', ['k3' => 'v1', 'k4' => 'v2']);

		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $store->get('test.test', 'ValueIfNotFound'));
		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $store->get('test.test2', 'ValueIfNotFound'));

		$store->deleteMultiple(['test.test', 'test.test2']);

		$this->assertNull($store->get('test.test', null));
		$this->assertNull($store->get('test.test2', null));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testGetMultiple() {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1']);
		$store->set('test.test2', ['k3' => 'v1', 'k4' => 'v2']);

		$getMultiple = $store->getMultiple(['test.test', 'test.test2', 'test.test3'], 'ValueIfNotFound');
		$this->assertEquals(['k1' => 'v1'], $getMultiple[0]);
		$this->assertEquals(['k3' => 'v1', 'k4' => 'v2'], $getMultiple[1]);
		$this->assertEquals('ValueIfNotFound', $getMultiple[2]);

	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testClear() {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));

		$store->set('test.test', ['k1' => 'v1', 'k2' => 'v2']);
		$store->set('test.test1', ['k1' => 'v1', 'k3' => 'v3']);
		$store->set('test.test2', ['k1' => 'v2', 'k4' => 'v4']);

		$store->clear();

		$this->assertNull($store->get('test.test', null));
		$this->assertNull($store->get('test.test1', null));
		$this->assertNull($store->get('test.test2', null));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testHas(): void {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));
		$store->set('test.test', ['k1' => 'v1', 'k2' => 'v2']);
		$this->assertTrue($store->has('test.test'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testFileCacheHasNoTtlSupport(): void {
		$store = PsrDecorators::psr16(new FileCacheStore($this->tempDirFsPath, 0777, 0777));
		$ttl = new \DateInterval('PT300S');
		$ttlNeg = new \DateInterval('PT12H');
		$ttlNeg->invert = true;

		$store->set('key-no-ttl', 'value', null);
		$store->set('key-int-ttl', 'value', 300);
		$store->set('key-interval-ttl', 'value', $ttl);
		$store->set('key-neg-interval-ttl', 'value', $ttlNeg);
		$this->assertNotNull($store->get('key-no-ttl'));
		$this->assertNotNull($store->get('key-int-ttl'));
		$this->assertNotNull($store->get('key-interval-ttl'));
		$this->assertNotNull($store->get('key-neg-interval-ttl'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testExpiresWithHelpOfDboCacheStoreReadCheckIfRowIsExpired(): void {
		$DboStore = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);
		$store = PsrDecorators::psr16($DboStore);
		$ttl = new \DateInterval('PT300S');
		$ttlNeg = new \DateInterval('PT12H');
		$ttlNeg->invert = true;

		$store->set('key-no-ttl', 'value', null);
		$store->set('key-int-ttl', 'value', 300);
		$store->set('key-neg-int-ttl', 'value', -300);
		$store->set('key-interval-ttl', 'value', $ttl);
		$store->set('key-neg-interval-ttl', 'value', $ttlNeg);
		$this->assertNotNull($store->get('key-no-ttl'));
		$this->assertNotNull($store->get('key-int-ttl'));
		$this->assertNull($store->get('key-neg-int-ttl'));
		$this->assertNotNull($store->get('key-interval-ttl'));
		$this->assertNull($store->get('key-neg-interval-ttl'));
	}
}