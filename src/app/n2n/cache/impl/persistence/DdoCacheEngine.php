<?php

namespace n2n\cache\impl\persistence;

use n2n\util\StringUtils;
use n2n\util\UnserializationFailedException;
use n2n\util\cache\CorruptedCacheStoreException;
use n2n\util\ex\IllegalStateException;
use n2n\util\col\ArrayUtils;
use n2n\util\type\ArgUtils;
use n2n\spec\dbo\meta\data\impl\QueryItems;
use n2n\spec\dbo\Dbo;
use n2n\spec\dbo\meta\structure\IndexType;

class DdoCacheEngine {
	const NAME_COLUMN = 'name';
	const CHARACTERISTICS_COLUMN = 'characteristics';
	const CHARACTERISTIC_COLUMN = 'characteristic';
	const DATA_COLUMN = 'data';
	const CREATED_COLUMN = 'created';
	const MAX_LENGTH = 255;
	const MAX_TEXT_SIZE = 134217720;
	private ?string $dataSelectSql = null;
	private ?string $itemInsertSql = null;
	private ?string $itemDeleteSql = null;
	private ?string $characteristicSelectSql = null;
	private ?string $characteristicInsertSql = null;
	private ?string $characteristicDeleteSql = null;

	function __construct(private readonly Dbo $dbo, private readonly string $dataTableName,
			private readonly string $characteristicTableName, private readonly DdoCacheDataSize $pdoCacheDataSize) {
	}

	private function dataSelectSql(bool $nameIncluded, bool $characteristicsIncluded): string {
		if ($this->dataSelectSql !== null && $nameIncluded && $characteristicsIncluded) {
			return $this->dataSelectSql;
		}

		$builder = $this->dbo->createSelectStatementBuilder($this->dbo);
		$builder->addFrom(QueryItems::table($this->dataTableName, 'd'));
		$comparator = $builder->getWhereComparator();

		if ($nameIncluded) {
			$comparator->match(QueryItems::column(self::NAME_COLUMN), '=',
					QueryItems::placeMarker(self::NAME_COLUMN));
		}

		if ($characteristicsIncluded) {
			$comparator->andMatch(QueryItems::column(self::CHARACTERISTICS_COLUMN), '=',
					QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		}

		if ($nameIncluded && $characteristicsIncluded) {
			return $this->dataSelectSql = $builder->toSqlString();
		}
		return $builder->toSqlString();
	}

	private function itemInsertSql(): string {
		if ($this->itemInsertSql !== null) {
			return $this->itemInsertSql;
		}

		$builder = $this->dbo->createInsertStatementBuilder($this->dbo);
		$builder->setTable($this->dataTableName);
		$builder->addColumn(QueryItems::column(self::NAME_COLUMN, 'd'),
				QueryItems::placeMarker(self::NAME_COLUMN));
		$builder->addColumn(QueryItems::column(self::CHARACTERISTICS_COLUMN, 'd'),
				QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		$builder->addColumn(QueryItems::column(self::DATA_COLUMN, 'd'),
				QueryItems::placeMarker(self::DATA_COLUMN));

		return $this->itemInsertSql = $builder->toSqlString();
	}

	private function itemDeleteSql(bool $nameIncluded, bool $characteristicsIncluded): string {
		if ($this->itemDeleteSql !== null && $nameIncluded && $characteristicsIncluded) {
			return $this->itemDeleteSql;
		}

		$builder = $this->dbo->createDeleteStatementBuilder($this->dbo);
		$builder->setTable($this->dataTableName);
		$comparator = $builder->getWhereComparator();

		if ($nameIncluded) {
			$comparator->match(QueryItems::column(self::NAME_COLUMN), '=',
					QueryItems::placeMarker(self::NAME_COLUMN));
		}

		if ($characteristicsIncluded) {
			$comparator->andMatch(QueryItems::column(self::CHARACTERISTICS_COLUMN), '=',
					QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		}

		if ($nameIncluded && $characteristicsIncluded) {
			return $this->itemDeleteSql = $builder->toSqlString();
		}

		return $builder->toSqlString();
	}

	private function characteristicSelectSql(bool $nameIncluded, bool $characteristicIncluded): string {
		if ($this->characteristicSelectSql !== null && $nameIncluded && $characteristicIncluded) {
			return $this->characteristicSelectSql;
		}

		$builder = $this->dbo->createSelectStatementBuilder($this->dbo);
		$builder->addFrom(QueryItems::table($this->characteristicTableName, 'c'));
		$comparator = $builder->getWhereComparator();

		if ($nameIncluded) {
			$comparator->match(QueryItems::column(self::NAME_COLUMN), '=',
					QueryItems::placeMarker(self::NAME_COLUMN));
		}

		if ($characteristicIncluded) {
			$comparator->andMatch(QueryItems::column(self::CHARACTERISTIC_COLUMN), '=',
					QueryItems::placeMarker(self::CHARACTERISTIC_COLUMN));
		}

		$sql = $builder->toSqlString();

		if ($nameIncluded && $characteristicIncluded) {
			return $this->characteristicSelectSql = $sql;
		}

		return $sql;
	}

	private function characteristicInsertSql(): string {
		if ($this->characteristicInsertSql !== null) {
			return $this->characteristicInsertSql;
		}

		$builder = $this->dbo->createInsertStatementBuilder($this->dbo);
		$builder->setTable($this->characteristicTableName);
		$builder->addColumn(QueryItems::column(self::NAME_COLUMN), QueryItems::placeMarker(self::NAME_COLUMN));
		$builder->addColumn(QueryItems::column(self::CHARACTERISTICS_COLUMN),
				QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		$builder->addColumn(QueryItems::column(self::CHARACTERISTIC_COLUMN),
				QueryItems::placeMarker(self::CHARACTERISTIC_COLUMN));

		return $this->characteristicInsertSql = $builder->toSqlString();
	}

	private function characteristicDeleteSql(bool $nameIncluded, bool $characteristicsIncluded): string {
		if ($this->characteristicDeleteSql !== null && $nameIncluded && $characteristicsIncluded) {
			return $this->characteristicDeleteSql;
		}

		$builder = $this->dbo->createDeleteStatementBuilder($this->dbo);
		$builder->setTable($this->characteristicTableName);
		$comparator = $builder->getWhereComparator();

		if ($nameIncluded) {
			$comparator->match(QueryItems::column(self::NAME_COLUMN), '=',
					QueryItems::placeMarker(self::NAME_COLUMN));
		}

		if ($characteristicsIncluded) {
			$comparator->andMatch(QueryItems::column(self::CHARACTERISTICS_COLUMN), '=',
					QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		}

		if ($nameIncluded && $characteristicsIncluded) {
			return $this->characteristicDeleteSql = $builder->toSqlString();
		}

		return $builder->toSqlString();
	}

	function read(string $name, array $characteristics): ?array {
		$characteristicsStr = $this->serializeCharacteristics($characteristics);
		$rows = $this->selectFromDataTable($name, $characteristicsStr);

		if (empty($rows)) {
			return null;
		}

		return self::unserializeResult($rows[0]);
	}

	private static function unserializeResult(array $row): array {
		try {
			$row[self::CHARACTERISTICS_COLUMN] = StringUtils::unserialize($row[self::CHARACTERISTICS_COLUMN]);
		} catch (UnserializationFailedException $e) {
			throw new CorruptedCacheStoreException('Could not unserialize characteristics for '
					. $row[self::NAME_COLUMN] . ': ' . $row[self::CHARACTERISTICS_COLUMN], previous: $e);
		}

		$dataStr = $row[self::DATA_COLUMN];
		try {
			$row[self::DATA_COLUMN] = ($dataStr === null ? [] : StringUtils::unserialize($dataStr));
		} catch (UnserializationFailedException $e) {
			throw new CorruptedCacheStoreException('Could not unserialize data for ' . $row[self::NAME_COLUMN]
					. ': ' . StringUtils::reduce($dataStr, 25, '...'), previous: $e);
		}

		return $row;
	}

	private static function serializeCharacteristics(?array $characteristics): ?string {
		if ($characteristics === null) {
			return null;
		}

		return serialize($characteristics);
	}

	private static function splitAndSerializeCharacteristics(?array $characteristicNeedles): ?array {
		if ($characteristicNeedles === null) {
			return null;
		}

		$strs = [];
		foreach ($characteristicNeedles as $key => $value) {
			$strs[] = serialize([$key => $value]);
		}
		return $strs;
	}

	private function execInTransaction(\Closure $closure, bool $readOnly): void {
		$this->ensureNotInTransaction();
		$this->dbo->beginTransaction($readOnly);
		try {
			$closure();
			$this->dbo->commit();
		} finally {
			if ($this->dbo->inTransaction()) {
				$this->dbo->rollBack();
			}
		}
	}

	function delete(string $name, array $characteristics): void {
		$characteristicsStr = self::serializeCharacteristics($characteristics);

		$this->execInTransaction(function () use ($name, $characteristicsStr) {
			$this->deleteFromDataTable($name, $characteristicsStr);
			$this->deleteFromCharacteristicTable($name, $characteristicsStr);
		}, false);
	}

	function deleteBy(?string $nameNeedle, ?array $characteristicNeedles): void {
		$characteristicsStr = self::serializeCharacteristics($characteristicNeedles);
		$characteristicNeedleStrs = self::splitAndSerializeCharacteristics($characteristicNeedles);

		$this->execInTransaction(function () use (&$nameNeedle, &$characteristicsStr, &$characteristicNeedleStrs) {
			$this->deleteFromDataTable($nameNeedle, $characteristicsStr);
			$this->deleteFromCharacteristicTable($nameNeedle, $characteristicsStr);

			if (empty($characteristicNeedleStrs)) {
				return;
			}

			foreach ($this->selectFromCharacteristicTable($nameNeedle, $characteristicNeedleStrs) as $result) {
				$name = $result[self::NAME_COLUMN];
				$characteristicsStr = $result[self::CHARACTERISTICS_COLUMN];
				$this->deleteFromDataTable($name, $characteristicsStr);
				$this->deleteFromCharacteristicTable($name, $characteristicsStr);
			}
		}, true);
	}

	function clear(): void {
		$this->deleteFromDataTable(null, null);
		$this->deleteFromCharacteristicTable(null, null);
	}

	function findBy(?string $nameNeedle, ?array $characteristicNeedles): array {
		$characteristicsStr = self::serializeCharacteristics($characteristicNeedles);
		$characteristicNeedleStrs = self::splitAndSerializeCharacteristics($characteristicNeedles);

		$rows = [];
		$this->execInTransaction(function ()
				use (&$nameNeedle, &$characteristicsStr, &$rows, &$characteristicNeedleStrs) {
			$rows = $this->selectFromDataTable($nameNeedle, $characteristicsStr);

			if (empty($characteristicNeedleStrs)) {
				return;
			}

			foreach ($this->selectFromCharacteristicTable($nameNeedle, $characteristicNeedleStrs) as $result) {
				array_push($rows,
						...$this->selectFromDataTable($result[self::NAME_COLUMN], $result[self::CHARACTERISTICS_COLUMN]));
			}
		}, true);

		return array_map(fn ($row) => self::unserializeResult($row), $rows);
	}

	private function ensureNotInTransaction(): void {
		if (!$this->dbo->inTransaction()) {
			return;
		}

		throw new IllegalStateException('Pdo "' . $this->dbo->getDataSourceName() . '" supplied to '
				. DdoCacheStore::class . ' is already in transaction which indicates it might be managed by a '
				. ' TransactionManager. DdoCacheEngine must be able to manage its PDO on its own.');
	}

	function write(string $name, array $characteristics, mixed $data): void {
		$characteristicsStr = self::serializeCharacteristics($characteristics);
		$dataStr = serialize($data);

		$this->execInTransaction(function () use (&$name, &$characteristicsStr, &$dataStr, &$characteristics) {
			$this->deleteFromDataTable($name, $characteristicsStr);
			$this->deleteFromCharacteristicTable($name, $characteristicsStr);
			$this->insertIntoDataTable($name, $characteristicsStr, $dataStr);
			if (count($characteristics) > 1) {
				$this->insertIntoCharacteristicTable($name, $characteristicsStr, $characteristics);
			}
		}, false);
	}

	private function deleteFromDataTable(?string $name, ?string $characteristicsStr): void {
		$stmt = $this->dbo->prepare($this->itemDeleteSql($name !== null, $characteristicsStr !== null));

		$stmt->execute(ArrayUtils::filterNotNull(
				[self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr]));
	}

	private function insertIntoDataTable(string $name, string $characteristicsStr, ?string $dataStr): void {
		$stmt = $this->dbo->prepare($this->itemInsertSql());

		$stmt->execute([
			self::NAME_COLUMN => $name,
			self::CHARACTERISTICS_COLUMN => $characteristicsStr,
			self::DATA_COLUMN => $dataStr
		]);
	}

	private function selectFromDataTable(?string $name, ?string $characteristicsStr): array {
		$stmt = $this->dbo->prepare($this->dataSelectSql($name !== null, $characteristicsStr !== null));
		$stmt->execute(ArrayUtils::filterNotNull(
				[self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr]));

		return $stmt->fetchAll();

	}

	private function deleteFromCharacteristicTable(?string $name, ?string $characteristicsStr): void {
		$stmt = $this->dbo->prepare($this->characteristicDeleteSql($name !== null,$characteristicsStr !== null));
		$stmt->execute(ArrayUtils::filterNotNull(
				[self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr]));
	}

	private function insertIntoCharacteristicTable(string $name, string $characteristicsStr, array $characteristics): void {
		$stmt = $this->dbo->prepare($this->characteristicInsertSql());
		foreach ($characteristics as $key => $value) {
			$stmt->execute([self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr,
					self::CHARACTERISTIC_COLUMN => self::serializeCharacteristics([$key => $value])]);
		}
	}

	private function selectFromCharacteristicTable(?string $nameNeedle, array $characteristicNeedleStrs): array {
		$selectSql = $this->characteristicSelectSql($nameNeedle !== null, true);
		$stmt = $this->dbo->prepare($selectSql);

		ArgUtils::assertTrue(!empty($characteristicNeedleStrs));

		$needlesNum = count($characteristicNeedleStrs);
		$resultRows = [];
		$hitMap = [];
		foreach ($characteristicNeedleStrs as $characteristicStr) {
			$stmt->execute(ArrayUtils::filterNotNull(
					[self::NAME_COLUMN => $nameNeedle, self::CHARACTERISTIC_COLUMN => $characteristicStr]));

			while (null !== ($row = $stmt->fetch())) {
				$name = $row[self::NAME_COLUMN];
				if (!isset($hitMap[$name])) {
					$hitMap[$name] = [];
				}

				$characteristicsStr = $row[self::CHARACTERISTICS_COLUMN];
				if (!isset($hitMap[$name][$characteristicsStr])) {
					$hitMap[$name][$characteristicsStr] = 1;
				} else {
					$hitMap[$name][$characteristicsStr]++;
				}

				if ($hitMap[$name][$characteristicsStr] === $needlesNum) {
					$resultRows[] = [self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $row[self::CHARACTERISTICS_COLUMN]];
					continue;
				}

				// should never happen, logic error protection
				IllegalStateException::assertTrue($hitMap[$name][$characteristicsStr] <= $needlesNum);
			}
		}

		return $resultRows;
	}


	function doesDataTableExist(): bool {
		return $this->dbo->getMetaData()->getDatabase()->containsMetaEntityName($this->dataTableName);
	}

	function createDataTable(): void {
		$metaData = $this->dbo->getMetaData();
		$database = $metaData->getDatabase();
		$dataTable = $database->createMetaEntityFactory()->createTable($this->dataTableName);
		$columnFactory = $dataTable->createColumnFactory();
		$columnFactory->createStringColumn(self::NAME_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createStringColumn(self::CHARACTERISTICS_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);

		$dataTable->createIndex(IndexType::PRIMARY, [self::NAME_COLUMN, self::CHARACTERISTICS_COLUMN]);
		$dataTable->createIndex(IndexType::INDEX, [self::CHARACTERISTICS_COLUMN]);

		switch ($this->pdoCacheDataSize) {
			case DdoCacheDataSize::STRING:
				$columnFactory->createStringColumn(self::DATA_COLUMN, self::MAX_LENGTH);
				break;
			case DdoCacheDataSize::TEXT:
				$columnFactory->createTextColumn(self::DATA_COLUMN, self::MAX_TEXT_SIZE);
				break;
		}

		$metaData->getMetaManager()->flush();
	}

	function doesCharacteristicTableExist(): bool {
		return $this->dbo->getMetaData()->getDatabase()->containsMetaEntityName($this->characteristicTableName);
	}

	function createCharacteristicTable(): void {
		$metaData = $this->dbo->getMetaData();
		$database = $metaData->getDatabase();
		$characteristicTable = $database->createMetaEntityFactory()->createTable($this->characteristicTableName);
		$columnFactory = $characteristicTable->createColumnFactory();
		$columnFactory->createStringColumn(self::NAME_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createStringColumn(self::CHARACTERISTICS_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createStringColumn(self::CHARACTERISTIC_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);

		$characteristicTable->createIndex(IndexType::PRIMARY, [self::NAME_COLUMN, self::CHARACTERISTICS_COLUMN, self::CHARACTERISTIC_COLUMN]);
		$characteristicTable->createIndex(IndexType::INDEX, [self::CHARACTERISTIC_COLUMN, self::NAME_COLUMN]);

		$metaData->getMetaManager()->flush();
	}

}