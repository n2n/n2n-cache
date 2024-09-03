<?php

namespace n2n\cache\impl\persistence;

use PHPUnit\Framework\TestCase;
use n2n\persistence\Pdo;
use n2n\impl\persistence\meta\sqlite\SqliteDialect;
use n2n\core\config\PersistenceUnitConfig;
use n2n\test\DbTestPdoUtil;
use n2n\persistence\orm\attribute\DateTime;
use n2n\cache\impl\CacheStorePools;

class DboCacheStorePoolTest extends TestCase {
	private Pdo $pdo;
	private DbTestPdoUtil $pdoUtil;

	function setUp(): void {
		$config = new PersistenceUnitConfig('holeradio', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, SqliteDialect::class);
		$this->pdo = new Pdo('holeradio', new SqliteDialect($config));
		$this->pdoUtil = new DbTestPdoUtil($this->pdo);
	}

	function testLookup(): void {
		$pool = CacheStorePools::dbo($this->pdo, 'holeradio_');
		$pool->setDboCacheDataSize(DboCacheDataSize::STRING);

		$store = $pool->lookupCacheStore('ns\\ns1');
		$store->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');
		$this->assertTrue($store === $pool->lookupCacheStore('ns\\ns1'));

		$pool->lookupCacheStore('ns\\ns2')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('holeradio_ns_ns1_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('holeradio_ns_ns1_characteristic'));

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('holeradio_ns_ns2_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('holeradio_ns_ns2_characteristic'));

		$this->assertCount(1, $this->pdoUtil->select('holeradio_ns_ns1_data', null));
		$this->assertCount(2, $this->pdoUtil->select('holeradio_ns_ns1_characteristic', null));
		$this->assertCount(1, $this->pdoUtil->select('holeradio_ns_ns2_data', null));
		$this->assertCount(2, $this->pdoUtil->select('holeradio_ns_ns2_characteristic', null));
	}

	function testClear(): void {
		$pool = CacheStorePools::dbo($this->pdo, 'holeradio_')
				->setDboCacheDataSize(DboCacheDataSize::STRING);

		$pool->lookupCacheStore('ns\\ns1')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');
		$pool->lookupCacheStore('ns\\ns2')->store('name', ['c1' => 'v1', 'c2' => 'v2'], 'huii');

		$this->assertCount(4, $this->pdo->getMetaData()->getDatabase()->getMetaEntities());

		$pool->clear();

		$this->assertCount(0, $this->pdo->createMetaManager()->createDatabase()->getMetaEntities());
	}


}