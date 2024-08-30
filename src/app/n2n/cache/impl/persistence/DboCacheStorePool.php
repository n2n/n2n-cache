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

class DboCacheStorePool implements CacheStorePool{

	const DEFAULT_TABLE_PREFIX = 'n2n_cache_pool';
	const TABLE_DATA_SUFFIX = '_data';
	const TABLE_CHARACTERISTICS_SUFFIX = '_characteristics';
	const SEPARATOR = '_';


	private string $tablePrefix = self::DEFAULT_TABLE_PREFIX;

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

	function lookupCacheStore(string $namespace): CacheStore {
		$tableName = mb_strtolower(TypeUtils::encodeNamespace($namespace, self::SEPARATOR));

		if (null !== ($cacheStore = $this->weakCacheStores[$tableName]?->get())) {
			return $cacheStore;
		}

		$cacheStore = new DboCacheStore($this->dbo);
		$cacheStore->setDataTableName($this->tablePrefix . $tableName . self::TABLE_DATA_SUFFIX);
		$cacheStore->setCharacteristicTableName($this->tablePrefix . $tableName
				. self::TABLE_CHARACTERISTICS_SUFFIX);
		$this->weakCacheStores[$tableName] = new \WeakReference($cacheStore);
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