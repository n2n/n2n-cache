<?php

namespace n2n\cache\impl\psr;

use n2n\util\io\fs\FsPath;
use n2n\cache\impl\PsrDecorators;
use n2n\cache\impl\fs\FileCacheStore;
use Psr\Cache\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use n2n\cache\impl\persistence\DboCacheStore;
use n2n\cache\impl\persistence\DboCacheDataSize;
use n2n\core\config\PersistenceUnitConfig;
use n2n\impl\persistence\meta\sqlite\SqliteDialect;
use n2n\persistence\Pdo;
use n2n\test\DbTestPdoUtil;

class Psr6DecoratorTest extends TestCase {

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
	function testGetItem(): void {
		$pool = PsrDecorators::psr6(new FileCacheStore($this->tempDirFsPath));

		$item = $pool->getItem('key');
		$this->assertFalse($item->isHit());

		$item->set('holeradio');
		$pool->save($item);

		$item = $pool->getItem('key');
		$this->assertTrue($item->isHit());
		$this->assertEquals('holeradio', $item->get());
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testDeleteItems(): void {
		$pool = PsrDecorators::psr6(new FileCacheStore($this->tempDirFsPath));

		$item = $pool->getItem('key');
		$item->set('holeradio');
		$pool->save($item);
		$item2 = $pool->getItem('key2');
		$item2->set('holeradio2');
		$pool->save($item2);

		$item = $pool->getItem('key');
		$this->assertTrue($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertTrue($item2->isHit());

		$pool->deleteItems(['key']);

		$item = $pool->getItem('key');
		$this->assertFalse($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertTrue($item2->isHit());
		$this->assertEquals('holeradio2', $item2->get());
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testClear(): void {
		$pool = PsrDecorators::psr6(new FileCacheStore($this->tempDirFsPath));

		$item = $pool->getItem('key');
		$item->set('holeradio');
		$pool->save($item);
		$item2 = $pool->getItem('key2');
		$item2->set('holeradio2');
		$pool->save($item2);

		$item = $pool->getItem('key');
		$this->assertTrue($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertTrue($item2->isHit());

		$pool->clear();

		$item = $pool->getItem('key');
		$this->assertFalse($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertFalse($item2->isHit());
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testSaveDeferred(): void {
		$pool = PsrDecorators::psr6(new FileCacheStore($this->tempDirFsPath));

		$item = $pool->getItem('key');
		$item->set('holeradio');
		$pool->saveDeferred($item);
		$item2 = $pool->getItem('key2');
		$item2->set('holeradio2');
		$pool->saveDeferred($item2);

		$item = $pool->getItem('key');
		$this->assertFalse($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertFalse($item2->isHit());

		$pool->commit();

		$item = $pool->getItem('key');
		$this->assertTrue($item->isHit());
		$item2 = $pool->getItem('key2');
		$this->assertTrue($item2->isHit());
	}

	/**
	 * @throws InvalidArgumentException
	 */
	function testExpiresWithHelpOfDboCacheStoreReadCheckIfRowIsExpired(): void {
		$DboStore = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);
		$pool = PsrDecorators::psr6($DboStore);
		$ttl = new \DateInterval('PT300S');
		$ttlNeg = new \DateInterval('PT12H');
		$now = new \DateTimeImmutable('now');
		$ttlNeg->invert = true;

		$item = $pool->getItem('key-no-ttl');
		$item->expiresAfter(null);
		$item->set('value');
		$pool->save($item);

		$item2 = $pool->getItem('key-int-ttl');
		$item2->expiresAfter(300);
		$item2->set('value');
		$pool->save($item2);

		$item3 = $pool->getItem('key-neg-int-ttl');
		$item3->expiresAfter(-300);
		$item3->set('value');
		$pool->save($item3);

		$item4 = $pool->getItem('key-interval-ttl');
		$item4->expiresAfter($ttl);
		$item4->set('value');
		$pool->save($item4);

		$item5 = $pool->getItem('key-neg-interval-ttl');
		$item5->expiresAfter($ttlNeg);
		$item5->set('value');
		$pool->save($item5);

		$item6 = $pool->getItem('key-neg-expires');
		$item6->expiresAt($now->add($ttlNeg));
		$item6->set('value');
		$pool->save($item6);

		$item7 = $pool->getItem('key-pos-expires');
		$item7->expiresAt($now->add($ttl));
		$item7->set('value');
		$pool->save($item7);

		$this->assertTrue($pool->getItem('key-no-ttl')->isHit());
		$this->assertTrue($pool->getItem('key-int-ttl')->isHit());
		$this->assertFalse($pool->getItem('key-neg-int-ttl')->isHit());
		$this->assertTrue($pool->getItem('key-interval-ttl')->isHit());
		$this->assertFalse($pool->getItem('key-neg-interval-ttl')->isHit());
		$this->assertFalse($pool->getItem('key-neg-expires')->isHit());
		$this->assertTrue($pool->getItem('key-pos-expires')->isHit());
	}
}