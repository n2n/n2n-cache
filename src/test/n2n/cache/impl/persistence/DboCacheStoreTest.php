<?php

namespace n2n\cache\impl\persistence;

use PHPUnit\Framework\TestCase;
use n2n\persistence\Pdo;
use n2n\impl\persistence\meta\sqlite\SqliteDialect;
use n2n\core\config\PersistenceUnitConfig;
use n2n\test\DbTestPdoUtil;

class DboCacheStoreTest extends TestCase {
	private Pdo $pdo;
	private DbTestPdoUtil $pdoUtil;

	function setUp(): void {
		$config = new PersistenceUnitConfig('holeradio', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, SqliteDialect::class);
		$this->pdo = new Pdo('holeradio', new SqliteDialect($config));
		$this->pdoUtil = new DbTestPdoUtil($this->pdo);
	}

	function testWrite(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value1'], 'data1');

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2', 'o-key' => 'o-value'], 'data2');

		$this->assertCount(2, $this->pdoUtil->select('cached_data', null));
		$this->assertCount(2, $this->pdoUtil->select('cached_characteristic', null));
	}

	function testRead(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$this->assertNull($store->get('holeradio', ['key' => 'value1']));

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2', 'o-key' => 'o-value'], 'data2');
		$this->assertEquals('data2', $store->get('holeradio', ['key' => 'value2', 'o-key' => 'o-value'])
				->getData());
	}

	function testFindAll(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$this->assertEmpty($store->findAll('holeradio', ['key' => 'value1']));

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2'], 'data2');
		$this->assertEquals('data2', $store->findAll('holeradio', ['key' => 'value2'])[0]->getData());
	}

	function testRemove(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->remove('holeradio', ['key' => 'value1']);

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2'], 'data2');
		$this->assertCount(1, $this->pdoUtil->select('cached_data', null));

		$store->remove('holeradio', ['key' => 'value2']);
		$this->assertCount(0, $this->pdoUtil->select('cached_data', null));
	}

	function testRemoveAll(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->removeAll('holeradio', ['key' => 'value1']);

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2'], 'data2');
		$this->assertCount(1, $this->pdoUtil->select('cached_data', null));

		$store->removeAll('holeradio', ['key' => 'value2']);
		$this->assertCount(0, $this->pdoUtil->select('cached_data', null));
	}

	function testClear(): void {
		$store = (new DboCacheStore($this->pdo))->setPdoCacheDataSize(DboCacheDataSize::STRING);

		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->clear();

		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_data'));
		$this->assertTrue($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('cached_characteristic'));

		$store->store('holeradio', ['key' => 'value2'], 'data2');
		$this->assertCount(1, $this->pdoUtil->select('cached_data', null));

		$store->clear();
		$this->assertCount(0, $this->pdoUtil->select('cached_data', null));
	}


//
//	function testTableCreate() {
//		$prepareCalls = 0;
//
//		$pdoMock = $this->createMock(Pdo::class);
//		$pdoMock->expects($this->once())
//				->method('prepare')
//				->willReturnCallback(function () use (&$prepareCalls) {
//					if ($prepareCalls === 0) {
//						throw new PdoException(new \PDOException('custom expection'));
//					}
//
//					return $this->createMock(PdoStatement::class);
//				});
//		$metaDataMock = $this->createMock(MetaData::class);
//		$pdoMock->expects($this->once())->method('getMetaData')->willReturn($metaDataMock);
//
//		$databaseMock = $this->createMock(Database::class);
//		$metaDataMock->expects($this->once())->method('getDatabase')->willReturn($databaseMock);
//
//		$
//		$databaseMock->expects($this->once())->method('createMetaEntityFactory');
//		$pdoMock->getMetaData()->getMetaManager()->createDatabase();
//	}

}