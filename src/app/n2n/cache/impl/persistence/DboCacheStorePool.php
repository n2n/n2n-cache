<?php

namespace n2n\cache\impl\persistence;

use n2n\util\StringUtils;
use n2n\util\ex\ExUtils;
use n2n\spec\dbo\Dbo;
use n2n\cache\CacheStore;
use n2n\util\type\TypeUtils;
use n2n\cache\CacheStorePool;
use WeakReference;
use ArrayObject;

class DboCacheStorePool implements CacheStorePool {

	const DEFAULT_TABLE_PREFIX = 'n2n_cache_pool_';
	const TABLE_DATA_SUFFIX = '_data';
	const TABLE_CHARACTERISTICS_SUFFIX = '_characteristic';
	const SEPARATOR = '_';


	private string $tablePrefix = self::DEFAULT_TABLE_PREFIX;
	private DboCacheDataSize $dboCacheDataSize = DboCacheDataSize::TEXT;
	private bool $igbinaryEnabled = false;

	/**
	 * @var WeakReference[]
	 */
	private array $weakCacheStores = [];

	function __construct(private readonly Dbo $dbo) {
	}

	function getTablePrefix(): string {
		return $this->tablePrefix;
	}

	function setTablePrefix(string $tablePrefix): static {
		$this->tablePrefix = $tablePrefix;
		return $this;
	}

	function setDboCacheDataSize(DboCacheDataSize $pdoCacheDataSize): DboCacheStorePool {
		$this->dboCacheDataSize = $pdoCacheDataSize;
		return $this;
	}

	function getDboCacheDataSize(): DboCacheDataSize {
		return $this->dboCacheDataSize;
	}

	public function isIgbinaryEnabled(): bool {
		return $this->igbinaryEnabled;
	}

	/**
	 * @see DboCacheStore::setIgbinaryEnabled()
	 */
	public function setIgbinaryEnabled(bool $igbinaryEnabled): static {
		$this->igbinaryEnabled = $igbinaryEnabled;
		return $this;
	}


	function lookupCacheStore(string $namespace): CacheStore {
		$tableName = mb_strtolower(TypeUtils::encodeNamespace($namespace, self::SEPARATOR));

		if (isset($this->weakCacheStores[$tableName])
				&& null !== ($cacheStore = $this->weakCacheStores[$tableName]?->get())) {
			return $cacheStore;
		}
		$cacheStore = new DboCacheStore($this->dbo);
		$cacheStore->setDataTableName($this->tablePrefix . $tableName . self::TABLE_DATA_SUFFIX);
		$cacheStore->setCharacteristicTableName($this->tablePrefix . $tableName
				. self::TABLE_CHARACTERISTICS_SUFFIX);
		$cacheStore->setDboCacheDataSize($this->dboCacheDataSize);
		$cacheStore->setIgbinaryEnabled($this->igbinaryEnabled);
		$this->weakCacheStores[$tableName] = \WeakReference::create($cacheStore);
		return $cacheStore;
	}

	function clear(): void {
		$metaManager = $this->dbo->createMetaManager();
		$database = $metaManager->createDatabase();
		foreach ($database->getMetaEntities() as $metaEntity) {
			$tableName = $metaEntity->getName();
			if (StringUtils::startsWith($this->tablePrefix, $tableName)) {
				$database->removeMetaEntityByName($tableName);
			}
		}
		$metaManager->flush();
	}
}