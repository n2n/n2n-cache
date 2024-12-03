<?php
namespace n2n\cache\impl\persistence;

use n2n\cache\CacheStore;
use n2n\cache\CacheItem;
use n2n\spec\dbo\err\DboException;
use n2n\cache\CacheStoreOperationFailedException;
use n2n\spec\dbo\Dbo;

class DboCacheStore implements CacheStore {
	private string $dataTableName = 'cached_data';
	private string $characteristicTableName = 'cached_characteristic';
	private DboCacheDataSize $dboCacheDataSize = DboCacheDataSize::TEXT;
	private bool $igbinaryEnabled = false;
	private bool $tableAutoCreated = true;
	private ?DboCacheEngine $pdoCacheEngine = null;

	function __construct(private Dbo $dbo) {
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

	public function setDboCacheDataSize(DboCacheDataSize $dboCacheDataSize): DboCacheStore {
		$this->dboCacheDataSize = $dboCacheDataSize;
		$this->pdoCacheEngine = null;
		return $this;
	}

	public function getDboCacheDataSize(): DboCacheDataSize {
		return $this->dboCacheDataSize;
	}

	public function isTableAutoCreated(): bool {
		return $this->tableAutoCreated;
	}

	public function setTableAutoCreated(bool $tableAutoCreated): DboCacheStore {
		$this->tableAutoCreated = $tableAutoCreated;
		return $this;
	}

	public function isIgbinaryEnabled(): bool {
		return $this->igbinaryEnabled;
	}

	/**
	 * If true {@link \igbinary_serialize()} will be used.
	 *
	 * @param bool $igbinaryEnabled
	 * @return $this
	 */
	public function setIgbinaryEnabled(bool $igbinaryEnabled): static {
		$this->igbinaryEnabled = $igbinaryEnabled;
		$this->pdoCacheEngine = null;
		return $this;
	}

	private function tableCheckedCall(\Closure $closure): mixed {
		if ($this->pdoCacheEngine === null) {
			$this->pdoCacheEngine = new DboCacheEngine($this->dbo, $this->dataTableName, $this->characteristicTableName,
					$this->dboCacheDataSize, $this->igbinaryEnabled);
		}

		try {
			return $closure();
		} catch (DboException $e) {
			if (!$this->tableAutoCreated || !$this->checkTables()) {
				throw new CacheStoreOperationFailedException($e->getMessage(), previous: $e);
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

	public function store(string $name, array $characteristics, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {
		$now ??= new \DateTime();
		$createdAt = $now->getTimestamp();
		$expiresAt = null;
		if ($ttl !== null) {
			$expiresAt = $now->add($ttl)->getTimestamp();
		}

		$this->tableCheckedCall(/** @throws DboException */ function ()
					use (&$name, &$characteristics, &$data, &$createdAt, &$expiresAt) {
				$this->pdoCacheEngine->write($name, $characteristics, $data, $createdAt, $expiresAt);
			});
	}

	public function get(string $name, array $characteristics, ?\DateTimeInterface $now = null): ?CacheItem {
		$expiredByTime = ($now ?? new \DateTime())->getTimestamp();

		$result = $this->tableCheckedCall(/** @throws DboException */ function () use (&$name, &$characteristics, &$expiredByTime) {
			return $this->pdoCacheEngine->read($name, $characteristics, $expiredByTime);
		});

		return self::parseCacheItem($result);
	}

	private static function parseCacheItem(?array $result): ?CacheItem {
		if ($result === null) {
			return null;
		}

		return new CacheItem($result[DboCacheEngine::NAME_COLUMN], $result[DboCacheEngine::CHARACTERISTICS_COLUMN],
				$result[DboCacheEngine::DATA_COLUMN]);
	}

	public function remove(string $name, array $characteristics): void {
		$this->tableCheckedCall(/** @throws DboException */ function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->delete($name, $characteristics);
		});
	}

	public function findAll(string $name, ?array $characteristicNeedles = null, ?\DateTimeInterface $now = null): array {
		$expiredByTime = ($now ?? new \DateTime())->getTimestamp();

		$results = $this->tableCheckedCall(/** @throws DboException */ function () use (&$name, &$characteristics,
				&$expiredByTime) {
			return $this->pdoCacheEngine->findBy($name, $characteristics, $expiredByTime);
		});

		return array_map(fn ($result) => self::parseCacheItem($result), $results);
	}

	public function removeAll(?string $name, ?array $characteristicNeedles = null): void {
		$this->tableCheckedCall(/** @throws DboException */ function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->deleteBy($name, $characteristics);
		});
	}

	public function clear(): void {
		$this->tableCheckedCall(/** @throws DboException */ function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->clear();
		});
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
		$now ??= new \DateTime();
		$this->tableCheckedCall(/** @throws DboException */ function() use (&$now) {
			$this->pdoCacheEngine->deleteExpiredByTime($now->getTimestamp());
		});

		if ($maxLifetime === null) {
			return;
		}

		$createdBy = $now->sub($maxLifetime);
		$this->tableCheckedCall(/** @throws DboException */ function() use (&$createdBy) {
			$this->pdoCacheEngine->deleteCreatedByTime($createdBy->getTimestamp());
		});
	}
}

