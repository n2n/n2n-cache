<?php

namespace n2n\cache\impl\persistence;

use PHPUnit\Framework\TestCase;
use n2n\persistence\Pdo;
use n2n\impl\persistence\meta\sqlite\SqliteDialect;
use n2n\core\config\PersistenceUnitConfig;
use n2n\test\DbTestPdoUtil;
use n2n\persistence\meta\structure\DuplicateMetaElementException;
use n2n\spec\dbo\meta\structure\StringColumn;
use n2n\spec\dbo\meta\structure\IndexType;
use n2n\spec\dbo\meta\structure\Table;

class DboCacheEngineTest extends TestCase {
	private Pdo $pdo;
	private DbTestPdoUtil $pdoUtil;

	function setUp(): void {
		$config = new PersistenceUnitConfig('holeradio', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, SqliteDialect::class);
		$this->pdo = new Pdo('holeradio', new SqliteDialect($config));
		$this->pdoUtil = new DbTestPdoUtil($this->pdo);
	}

	private function createEngine(DboCacheDataSize $pdoCacheDataSize = DboCacheDataSize::STRING): DboCacheEngine {
		return new DboCacheEngine($this->pdo, 'data', 'characteristic', $pdoCacheDataSize);
	}

	function testCreateDataTable(): void {
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('characteristic'));

		$engine = $this->createEngine();
		$this->assertFalse($engine->doesDataTableExist());
		$engine->createDataTable();
		$this->assertTrue($engine->doesDataTableExist());

		$database = $this->pdo->getMetaData()->getDatabase();
		$this->assertTrue($database->containsMetaEntityName('data'));
		$this->assertFalse($database->containsMetaEntityName('characteristic'));

		$table = $database->getMetaEntityByName('data');
		assert($table instanceof Table);
		$this->assertCount(3, $table->getColumns());
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('name'));
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('characteristics'));
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('data'));

		$indexes = $table->getIndexes();
		$this->assertCount(2, $indexes);
		$this->assertEquals(IndexType::PRIMARY, $indexes[0]->getType());
		$this->assertEquals(['name', 'characteristics'], array_map(fn ($c) => $c->getName(), $indexes[0]->getColumns()));
		$this->assertEquals(IndexType::INDEX, $indexes[1]->getType());
		$this->assertEquals(['characteristics'], array_map(fn ($c) => $c->getName(), $indexes[1]->getColumns()));

		$this->assertCount(0, $this->pdoUtil->select('data', null));
	}

	function testCreateCharacteristicTable(): void {
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('data'));
		$this->assertFalse($this->pdo->getMetaData()->getDatabase()->containsMetaEntityName('characteristic'));

		$engine = $this->createEngine();
		$this->assertFalse($engine->doesCharacteristicTableExist());
		$engine->createCharacteristicTable();
		$this->assertTrue($engine->doesCharacteristicTableExist());

		$database = $this->pdo->getMetaData()->getDatabase();
		$this->assertFalse($database->containsMetaEntityName('data'));
		$this->assertTrue($database->containsMetaEntityName('characteristic'));

		$table = $database->getMetaEntityByName('characteristic');
		assert($table instanceof Table);
		$this->assertCount(3, $table->getColumns());
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('name'));
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('characteristics'));
		$this->assertInstanceOf(StringColumn::class, $table->getColumnByName('characteristic'));

		$indexes = $table->getIndexes();
		$this->assertCount(2, $indexes);
		$this->assertEquals(IndexType::PRIMARY, $indexes[0]->getType());
		$this->assertEquals(['name', 'characteristics', 'characteristic'], array_map(fn ($c) => $c->getName(), $indexes[0]->getColumns()));
		$this->assertEquals(IndexType::INDEX, $indexes[1]->getType());
		$this->assertEquals(['characteristic', 'name'], array_map(fn ($c) => $c->getName(), $indexes[1]->getColumns()));

		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));
	}

	function testWriteSingleCharacteristic(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value1'], 'data1');
		$engine->write('holeradio', ['key' => 'value2'], 'data2');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1']), 'data' => serialize('data1')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2']), 'data' => serialize('data2')],
				$rows[1]);
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));

		$engine->write('holeradio', ['key' => 'value1'], 'data11');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2']), 'data' => serialize('data2')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1']), 'data' => serialize('data11')],
				$rows[1]);
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));
	}

	function testDeleteSingleCharacteristic(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value1'], 'data1');
		$engine->write('holeradio', ['key' => 'value2'], 'data2');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1']), 'data' => serialize('data1')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2']), 'data' => serialize('data2')],
				$rows[1]);
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));

		$engine->write('holeradio', ['key' => 'value1'], 'data11');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2']), 'data' => serialize('data2')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1']), 'data' => serialize('data11')],
				$rows[1]);
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));
	}

	function testWriteMultipleCharacteristics(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value1', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio', ['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2'], 'data2');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1', 'o-key' => 'o-value']),
						'data' => serialize('data1')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'data' => serialize('data2')],
				$rows[1]);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(5, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1', 'o-key' => 'o-value']),
						'characteristic' => serialize(['key' => 'value1'])],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1', 'o-key' => 'o-value']),
						'characteristic' => serialize(['o-key' => 'o-value'])],
				$rows[1]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'characteristic' => serialize(['key' => 'value2'])],
				$rows[2]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'characteristic' => serialize(['o-key' => 'o-value'])],
				$rows[3]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'characteristic' => serialize(['to-key' => 'to-value2'])],
				$rows[4]);

		$engine->write('holeradio', ['key' => 'value1', 'o-key' => 'o-value'], 'data11');

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'data' => serialize('data2')],
				$rows[0]);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value1', 'o-key' => 'o-value']),
						'data' => serialize('data11')],
				$rows[1]);

		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));
	}

	function testDelete(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value0'], 'data0');
		$engine->write('holeradio', ['key' => 'value1', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio', ['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2'], 'data2');

		$this->assertCount(3, $this->pdoUtil->select('data', null));
		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));

		$engine->delete('holeradio', ['key' => 'value0']);

		$this->assertCount(2, $this->pdoUtil->select('data', null));
		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));

		$engine->delete('holeradio', ['key' => 'value1', 'o-key' => 'o-value']);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(1, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'data' => serialize('data2')],
				$rows[0]);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(3, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']),
						'characteristic' => serialize(['key' => 'value2'])],
				$rows[0]);

		$engine->delete('holeradio', ['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value2']);

		$this->assertCount(0, $this->pdoUtil->select('data', null));
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));
	}

	function testFindBy(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value0'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value1', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio2', ['key' => 'value1', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$engine->write('holeradio', ['key' => 'value2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data2');

		$results = $engine->findBy('holeradio', null);
		$this->assertCount(3, $results);
		$this->assertEquals('data0', $results[0]['data']);
		$this->assertEquals('data1', $results[1]['data']);
		$this->assertEquals('data2', $results[2]['data']);

		$results = $engine->findBy(null, ['key' => 'value0']);
		$this->assertCount(2, $results);
		$this->assertEquals('data0', $results[0]['data']);
		$this->assertEquals('data0-2', $results[1]['data']);

		$results = $engine->findBy(null, ['o-key' => 'o-value', 'to-key' => 'to-value']);
		$this->assertCount(2, $results);
		$this->assertEquals('data2', $results[0]['data']);
		$this->assertEquals('data1-2', $results[1]['data']);

		$results = $engine->findBy('holeradio', ['o-key' => 'o-value', 'to-key' => 'to-value']);
		$this->assertCount(1, $results);
		$this->assertEquals('data2', $results[0]['data']);
	}

	function testDeleteByName(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value0'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value1', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio2', ['key' => 'value1', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));

		$engine->deleteBy('holeradio', null);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio2', 'characteristics' => serialize( ['key' => 'value0']),
						'data' => serialize('data0-2')],
				$rows[0]);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(3, $rows);
		$this->assertEquals(
				['name' => 'holeradio2',
						'characteristics' => serialize(['key' => 'value1', 'o-key' => 'o-value', 'to-key' => 'to-value']),
						'characteristic' => serialize(['key' => 'value1'])],
				$rows[0]);
	}

	function testDeleteBySingleCharacteristics(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0-2'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio2', ['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));

		$engine->deleteBy(null, ['key' => 'value']);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio2', 'characteristics' => serialize( ['key' => 'value0-2']),
						'data' => serialize('data0-2')],
				$rows[0]);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(3, $rows);
		$this->assertEquals(
				['name' => 'holeradio2',
						'characteristics' => serialize(['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value']),
						'characteristic' => serialize(['key' => 'value1-2'])],
				$rows[0]);
	}

	function testDeleteByMultipleCharacteristics(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0-2'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1');
		$engine->write('holeradio2', ['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(6, $this->pdoUtil->select('characteristic', null));

		$engine->deleteBy(null, ['o-key' => 'o-value', 'to-key' => 'to-value']);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);
		$this->assertEquals(
				['name' => 'holeradio', 'characteristics' => serialize(['key' => 'value']),
						'data' => serialize('data0')],
				$rows[0]);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(0, $rows);
	}

	function testDeleteByNameAndSingleCharacteristics(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0-2'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value', 'o-key' => 'o-value'], 'data1');
		$engine->write('holeradio2', ['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(5, $this->pdoUtil->select('characteristic', null));

		$engine->deleteBy('holeradio', ['key' => 'value']);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(3, $rows);
	}

	function testDeleteByNameAndMultipleCharacteristics(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0-2'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1');
		$engine->write('holeradio', ['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(6, $this->pdoUtil->select('characteristic', null));

		$engine->deleteBy('holeradio', ['o-key' => 'o-value', 'to-key' => 'to-value']);

		$rows = $this->pdoUtil->select('data', null);
		$this->assertCount(2, $rows);

		$rows = $this->pdoUtil->select('characteristic', null);
		$this->assertCount(0, $rows);
	}

	function testClear(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();
		$engine->createCharacteristicTable();

		$engine->write('holeradio', ['key' => 'value'], 'data0');
		$engine->write('holeradio2', ['key' => 'value0-2'], 'data0-2');

		$engine->write('holeradio', ['key' => 'value', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1');
		$engine->write('holeradio', ['key' => 'value1-2', 'o-key' => 'o-value', 'to-key' => 'to-value'], 'data1-2');

		$this->assertCount(4, $this->pdoUtil->select('data', null));
		$this->assertCount(6, $this->pdoUtil->select('characteristic', null));

		$engine->clear();

		$this->assertCount(0, $this->pdoUtil->select('data', null));
		$this->assertCount(0, $this->pdoUtil->select('characteristic', null));
	}

	function testCreateDataTableExceptionWhenExists(): void {
		$engine = $this->createEngine();

		$engine->createDataTable();

		$this->expectException(DuplicateMetaElementException::class);
		$engine->createDataTable();
	}
}