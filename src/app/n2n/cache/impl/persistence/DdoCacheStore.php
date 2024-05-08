<?php
namespace n2n\cache\impl\persistence;

use n2n\util\cache\CacheStore;
use n2n\util\cache\CacheItem;
use n2n\persistence\Pdo;
use n2n\persistence\PdoException;

class DdoCacheStore implements CacheStore {
	private string $dataTableName = 'cached_data';
	private string $characteristicTableName = 'cached_characteristic';
	private DdoCacheDataSize $pdoCacheDataSize = DdoCacheDataSize::TEXT;
	private bool $tableAutoCreated = true;
	private ?DdoCacheEngine $pdoCacheEngine = null;

	function __construct(private Pdo $pdo) {
	}

	function setDataTableName(string $dataTableName): static {
		$this->dataTableName = $dataTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	function getDataTableName(): string {
		return $this->dataTableName;
	}

	public function setCharacteristicTableName(string $characteristicTableName): static {
		$this->characteristicTableName = $characteristicTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	public function getCharacteristicTableName(): string {
		return $this->characteristicTableName;
	}

	public function setPdoCacheDataSize(DdoCacheDataSize $pdoCacheDataSize): DdoCacheStore {
		$this->pdoCacheDataSize = $pdoCacheDataSize;
		return $this;
	}

	public function getPdoCacheDataSize(): DdoCacheDataSize {
		return $this->pdoCacheDataSize;
	}

	public function isTableAutoCreated(): bool {
		return $this->tableAutoCreated;
	}

	public function setTableAutoCreated(bool $tableAutoCreated): DdoCacheStore {
		$this->tableAutoCreated = $tableAutoCreated;
		return $this;
	}

	private function tableCheckedCall(\Closure $closure): mixed {
		if ($this->pdoCacheEngine === null) {
			$this->pdoCacheEngine = new DdoCacheEngine($this->pdo, $this->dataTableName, $this->characteristicTableName,
					$this->pdoCacheDataSize);
		}

		try {
			return $closure();
		} catch (PdoException $e) {
			if (!$this->tableAutoCreated || !$this->checkTables()) {
				throw $e;
			}

			return $closure();
		}
	}

	private function checkTables(): bool {
		$tablesCreated = false;

		if (!$this->pdoCacheEngine->doesDataTableExist()) {
			$this->pdoCacheEngine->createDataTable();
			$tablesCreated = true;
		}

		if (!$this->pdoCacheEngine->doesCharacteristicTableExist()) {
			$this->pdoCacheEngine->createCharacteristicTable();
			$tablesCreated = true;
		}

		return $tablesCreated;
	}

	public function store(string $name, array $characteristics, mixed $data, \DateTime $created = null, \DateInterval $ttl = null): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics, &$data) {
			$this->pdoCacheEngine->write($name, $characteristics, $data);
		});
	}

	public function get(string $name, array $characteristics): ?CacheItem {
		$result = $this->tableCheckedCall(function () use (&$name, &$characteristics) {
			return $this->pdoCacheEngine->read($name, $characteristics);
		});

		return self::parseCacheItem($result);
	}

	private static function parseCacheItem(?array $result): ?CacheItem {
		if ($result === null) {
			return null;
		}

		return new CacheItem($result[DdoCacheEngine::NAME_COLUMN], $result[DdoCacheEngine::CHARACTERISTICS_COLUMN],
				$result[DdoCacheEngine::DATA_COLUMN]);
	}

	public function remove(string $name, array $characteristics): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->delete($name, $characteristics);
		});
	}

	public function findAll(string $name, array $characteristicNeedles = null): array {
		$results = $this->tableCheckedCall(function () use (&$name, &$characteristics) {
			return $this->pdoCacheEngine->findBy($name, $characteristics);
		});

		return array_map(fn ($result) => self::parseCacheItem($result), $results);
	}

	public function removeAll(?string $name, array $characteristicNeedles = null): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->deleteBy($name, $characteristics);
		});
	}

	public function clear(): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->clear();
		});
	}

	public function garbageCollect(\DateInterval $ttl = null): void {
	}
}

