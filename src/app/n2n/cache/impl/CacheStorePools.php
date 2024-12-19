<?php

namespace n2n\cache\impl;

use n2n\spec\dbo\Dbo;
use n2n\cache\impl\persistence\DboCacheStorePool;
use n2n\util\io\fs\FsPath;
use n2n\cache\impl\fs\FileCacheStorePool;
use n2n\cache\impl\ephemeral\EphemeralCacheStorePool;
use n2n\cache\impl\ephemeral\NullCacheStorePool;

class CacheStorePools {

	static function dbo(Dbo $dbo, string $tablePrefix = DboCacheStorePool::DEFAULT_TABLE_PREFIX): DboCacheStorePool {
		$pool = new DboCacheStorePool($dbo);
		$pool->setTablePrefix($tablePrefix);
		return $pool;
	}

	static function file(FsPath $dirPath, string|int $dirPerm = null, string|int|null $filePerm = null): FileCacheStorePool {
		return new FileCacheStorePool($dirPath, $dirPerm, $filePerm);
	}

	static function ephemeral(): EphemeralCacheStorePool {
		return new EphemeralCacheStorePool();
	}

	static function null(): NullCacheStorePool {
		return new NullCacheStorePool();
	}

}