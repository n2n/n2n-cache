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
use n2n\util\BinaryUtils;
use n2n\spec\dbo\err\DboException;

class DboCacheEngine {
	const NAME_COLUMN = 'name';
	const CHARACTERISTICS_COLUMN = 'characteristics';
	const CHARACTERISTIC_COLUMN = 'characteristic';
	const DATA_COLUMN = 'data';
	const EXPIRES_AT_COLUMN = 'expires_at';
	const MAX_LENGTH = 255;
	const MAX_TEXT_SIZE = 134217720;
	private ?string $dataSelectSql = null;
	private ?string $dataUpsertSql = null;
	private ?string $dataDeleteSql = null;
	private ?string $characteristicSelectSql = null;
	private ?string $characteristicInsertSql = null;
	private ?string $characteristicDeleteSql = null;

	function __construct(private readonly Dbo $dbo, private readonly string $dataTableName,
			private readonly string $characteristicTableName, private readonly DboCacheDataSize $pdoCacheDataSize,
			private readonly bool $igbinaryEnabled) {
	}

	private function dataSelectSql(bool $nameIncluded, bool $characteristicsIncluded, bool $expiredByTimeIncluded): string {
		if ($this->dataSelectSql !== null && $nameIncluded && $characteristicsIncluded && !$expiredByTimeIncluded) {
			return $this->dataSelectSql;
		}

		$builder = $this->dbo->createSelectStatementBuilder();
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

		if ($expiredByTimeIncluded) {
			$comparator->andMatch(QueryItems::column(self::EXPIRES_AT_COLUMN), '<=',
					QueryItems::placeMarker(self::EXPIRES_AT_COLUMN));
		}

		if ($nameIncluded && $characteristicsIncluded && !$expiredByTimeIncluded) {
			return $this->dataSelectSql = $builder->toSqlString();
		}
		return $builder->toSqlString();
	}

	private function dataUpsertSql(): string {
		if ($this->dataUpsertSql !== null) {
			return $this->dataUpsertSql;
		}

		$builder = $this->dbo->createInsertStatementBuilder();
		$builder->setTable($this->dataTableName);
		$builder->addColumn(QueryItems::column(self::NAME_COLUMN, 'd'),
				QueryItems::placeMarker(self::NAME_COLUMN));
		$builder->addColumn(QueryItems::column(self::CHARACTERISTICS_COLUMN, 'd'),
				QueryItems::placeMarker(self::CHARACTERISTICS_COLUMN));
		$builder->addColumn(QueryItems::column(self::DATA_COLUMN, 'd'),
				QueryItems::placeMarker(self::DATA_COLUMN));
		$builder->addColumn(QueryItems::column(self::EXPIRES_AT_COLUMN),
				QueryItems::placeMarker(self::EXPIRES_AT_COLUMN));
		$builder->setUpsertUniqueColumns([QueryItems::column(self::NAME_COLUMN, 'd'),
				QueryItems::column(self::CHARACTERISTICS_COLUMN, 'd')]);

		return $this->dataUpsertSql = $builder->toSqlString();
	}

	private function dataDeleteSql(bool $nameIncluded, bool $characteristicsIncluded, bool $expiredByTimeIncluded): string {
		if ($this->dataDeleteSql !== null && $nameIncluded && $characteristicsIncluded && !$expiredByTimeIncluded) {
			return $this->dataDeleteSql;
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

		if ($expiredByTimeIncluded) {
			$comparator->andMatch(QueryItems::column(self::EXPIRES_AT_COLUMN), '<=',
					QueryItems::placeMarker(self::EXPIRES_AT_COLUMN));
		}

		if ($nameIncluded && $characteristicsIncluded && !$expiredByTimeIncluded) {
			return $this->dataDeleteSql = $builder->toSqlString();
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
		$builder->addColumn(QueryItems::column(self::EXPIRES_AT_COLUMN),
				QueryItems::placeMarker(self::EXPIRES_AT_COLUMN));

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

	/**
	 * @throws DboException
	 */
	function read(string $name, array $characteristics): ?array {
		$characteristicsStr = $this->serializeCharacteristics($characteristics);
		$rows = $this->selectFromDataTable($name, $characteristicsStr);

		if (empty($rows)) {
			return null;
		}

		return self::unserializeResult($rows[0]);
	}

	private function unserializeResult(array $row): array {
		try {
			$row[self::CHARACTERISTICS_COLUMN] = ($this->igbinaryEnabled
					? BinaryUtils::igbinaryUnserialize($row[self::CHARACTERISTICS_COLUMN])
					: StringUtils::unserialize($row[self::CHARACTERISTICS_COLUMN]));
		} catch (UnserializationFailedException $e) {
			throw new CorruptedCacheStoreException('Could not unserialize characteristics for '
					. $row[self::NAME_COLUMN] . ': ' . $row[self::CHARACTERISTICS_COLUMN], previous: $e);
		}

		$dataStr = $row[self::DATA_COLUMN];
		try {
			$row[self::DATA_COLUMN] = ($this->igbinaryEnabled
					? BinaryUtils::igbinaryUnserialize((string) $dataStr)
					: StringUtils::unserialize((string) $dataStr));
		} catch (UnserializationFailedException $e) {
			throw new CorruptedCacheStoreException('Could not unserialize data for ' . $row[self::NAME_COLUMN]
					. ': ' . StringUtils::reduce($dataStr, 25, '...'), previous: $e);
		}

		return $row;
	}

	private function serializeData(mixed $data): ?string {
		if ($this->igbinaryEnabled) {
			return igbinary_serialize($data);
		}

		return serialize($data);
	}

	private function serializeCharacteristics(?array $characteristics): ?string {
		if ($characteristics === null) {
			return null;
		}

		if ($this->igbinaryEnabled) {
			return igbinary_serialize($characteristics);
		}


		return serialize($characteristics);
	}


	private function splitAndSerializeCharacteristics(?array $characteristicNeedles): ?array {
		if ($characteristicNeedles === null) {
			return null;
		}

		$strs = [];
		foreach ($characteristicNeedles as $key => $value) {
			$strs[] = $this->igbinaryEnabled ? igbinary_serialize([$key => $value]) : serialize([$key => $value]);
		}
		return $strs;
	}

	/**
	 * @throws DboException
	 */
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

	/**
	 * @throws DboException
	 */
	function delete(string $name, array $characteristics): void {
		$characteristicsStr = $this->serializeCharacteristics($characteristics);

		$this->execInTransaction(function () use ($name, $characteristicsStr) {
			$this->deleteFromDataTable($name, $characteristicsStr, null);
			$this->deleteFromCharacteristicTable($name, $characteristicsStr, null);
		}, false);
	}

	/**
	 * @throws DboException
	 */
	function deleteExpiredBy(int $time): void {
		$this->execInTransaction(function () use ($time) {
			$this->deleteFromDataTable(null, null, $time);
			$this->deleteFromCharacteristicTable(null, null, $time);
		}, false);
	}

	/**
	 * @throws DboException
	 */
	function deleteBy(?string $nameNeedle, ?array $characteristicNeedles): void {
		$characteristicsStr = $this->serializeCharacteristics($characteristicNeedles);
		$characteristicNeedleStrs = $this->splitAndSerializeCharacteristics($characteristicNeedles);

		$this->execInTransaction(function () use (&$nameNeedle, &$characteristicsStr, &$characteristicNeedleStrs) {
			$this->deleteFromDataTable($nameNeedle, $characteristicsStr, null);
			$this->deleteFromCharacteristicTable($nameNeedle, $characteristicsStr, null);

			if (empty($characteristicNeedleStrs)) {
				return;
			}

			foreach ($this->selectFromCharacteristicTable($nameNeedle, $characteristicNeedleStrs) as $result) {
				$name = $result[self::NAME_COLUMN];
				$characteristicsStr = $result[self::CHARACTERISTICS_COLUMN];
				$this->deleteFromDataTable($name, $characteristicsStr, null);
				$this->deleteFromCharacteristicTable($name, $characteristicsStr, null);
			}
		}, true);
	}

	/**
	 * @throws DboException
	 */
	function clear(): void {
		$this->deleteFromDataTable(null, null, null);
		$this->deleteFromCharacteristicTable(null, null, null);
	}

	/**
	 * @param string|null $nameNeedle
	 * @param array|null $characteristicNeedles
	 * @return array
	 * @throws DboException
	 */
	function findBy(?string $nameNeedle, ?array $characteristicNeedles): array {
		$characteristicsStr = $this->serializeCharacteristics($characteristicNeedles);
		$characteristicNeedleStrs = $this->splitAndSerializeCharacteristics($characteristicNeedles);

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
				. DboCacheStore::class . ' is already in transaction which indicates it might be managed by a '
				. ' TransactionManager. DdoCacheEngine must be able to manage its PDO on its own.');
	}

	/**
	 * @throws DboException
	 */
	function write(string $name, array $characteristics, mixed $data, ?int $expiresAt): void {
		$characteristicsStr = $this->serializeCharacteristics($characteristics);
		$dataStr = $this->serializeData($data);

		$this->execInTransaction(function () use (&$name, &$characteristicsStr, &$dataStr, &$characteristics, &$expiresAt) {
			$this->upsertIntoDataTable($name, $characteristicsStr, $dataStr, $expiresAt);
			$this->deleteFromCharacteristicTable($name, $characteristicsStr, null);
			if (count($characteristics) > 1) {
				$this->insertIntoCharacteristicTable($name, $characteristicsStr, $characteristics, $expiresAt);
			}
		}, false);
	}

	/**
	 * @throws DboException
	 */
	private function deleteFromDataTable(?string $name, ?string $characteristicsStr, ?int $expiredByTime): void {
		$stmt = $this->dbo->prepare($this->dataDeleteSql($name !== null, $characteristicsStr !== null,
				$expiredByTime !== null));

		$stmt->execute(ArrayUtils::filterNotNull(
				[self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr,
						self::EXPIRES_AT_COLUMN => $expiredByTime]));
	}

	/**
	 * @throws DboException
	 */
	private function upsertIntoDataTable(string $name, string $characteristicsStr, ?string $dataStr, ?int $expiresAt): void {
		$stmt = $this->dbo->prepare($this->dataUpsertSql());

		$stmt->execute([
			self::NAME_COLUMN => $name,
			self::CHARACTERISTICS_COLUMN => $characteristicsStr,
			self::DATA_COLUMN => $dataStr,
			self::EXPIRES_AT_COLUMN => $expiresAt
		]);
	}

	/**
	 * @throws DboException
	 */
	private function selectFromDataTable(?string $name, ?string $characteristicsStr): array {
		$stmt = $this->dbo->prepare($this->dataSelectSql($name !== null, $characteristicsStr !== null, false));
		$stmt->execute(ArrayUtils::filterNotNull([self::NAME_COLUMN => $name,
				self::CHARACTERISTICS_COLUMN => $characteristicsStr]));

		return $stmt->fetchAll();

	}

	/**
	 * @throws DboException
	 */
	private function deleteFromCharacteristicTable(?string $name, ?string $characteristicsStr, ?int $expiredByTime): void {
		$stmt = $this->dbo->prepare($this->characteristicDeleteSql($name !== null,$characteristicsStr !== null,
				$expiredByTime !== null));
		$stmt->execute(ArrayUtils::filterNotNull([self::NAME_COLUMN => $name,
				self::CHARACTERISTICS_COLUMN => $characteristicsStr, self::EXPIRES_AT_COLUMN => $expiredByTime]));
	}

	/**
	 * @throws DboException
	 */
	private function insertIntoCharacteristicTable(string $name, string $characteristicsStr, array $characteristics,
			?int $expiresAt): void {
		$stmt = $this->dbo->prepare($this->characteristicInsertSql());
		foreach ($characteristics as $key => $value) {
			$stmt->execute([self::NAME_COLUMN => $name, self::CHARACTERISTICS_COLUMN => $characteristicsStr,
					self::CHARACTERISTIC_COLUMN => $this->serializeCharacteristics([$key => $value]),
					self::EXPIRES_AT_COLUMN => $expiresAt]);
		}
	}

	/**
	 * @throws DboException
	 */
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
		$columnFactory->createBinaryColumn(self::NAME_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createBinaryColumn(self::CHARACTERISTICS_COLUMN, self::MAX_LENGTH)
				->setNullAllowed(false);
		$columnFactory->createIntegerColumn(self::EXPIRES_AT_COLUMN, 32);

		$dataTable->createIndex(IndexType::PRIMARY, [self::NAME_COLUMN, self::CHARACTERISTICS_COLUMN]);
		$dataTable->createIndex(IndexType::INDEX, [self::CHARACTERISTICS_COLUMN]);
		$dataTable->createIndex(IndexType::INDEX, [self::EXPIRES_AT_COLUMN]);

		switch ($this->pdoCacheDataSize) {
			case DboCacheDataSize::STRING:
				$columnFactory->createBinaryColumn(self::DATA_COLUMN, self::MAX_LENGTH)
						->setNullAllowed(false);
				break;
			case DboCacheDataSize::TEXT:
				$columnFactory->createTextColumn(self::DATA_COLUMN, self::MAX_TEXT_SIZE)
						->setNullAllowed(false);
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
		$columnFactory->createBinaryColumn(self::NAME_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createBinaryColumn(self::CHARACTERISTICS_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createBinaryColumn(self::CHARACTERISTIC_COLUMN, self::MAX_LENGTH)->setNullAllowed(false);
		$columnFactory->createIntegerColumn(self::EXPIRES_AT_COLUMN, 32);

		$characteristicTable->createIndex(IndexType::PRIMARY, [self::NAME_COLUMN, self::CHARACTERISTICS_COLUMN, self::CHARACTERISTIC_COLUMN]);
		$characteristicTable->createIndex(IndexType::INDEX, [self::CHARACTERISTIC_COLUMN, self::NAME_COLUMN]);
		$characteristicTable->createIndex(IndexType::INDEX, [self::EXPIRES_AT_COLUMN]);

		$metaData->getMetaManager()->flush();
	}

}