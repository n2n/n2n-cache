<?php

namespace n2n\cache\impl;

use n2n\cache\impl\persistence\DboCacheStore;
use n2n\spec\dbo\Dbo;
use n2n\util\io\fs\FsPath;
use n2n\cache\CacheStore;
use n2n\cache\impl\psr\Psr16CacheStore;
use n2n\cache\impl\psr\Psr6CacheStore;

class CacheStores {


	static function dbo(Dbo $dbo): DboCacheStore {
		return new DboCacheStore($dbo);
	}

	static function file(FsPath $dirPath, string|int $dirPerm = null, string|int $filePerm = null): FileCacheStore {
		return new FileCacheStore($dirPath, $dirPerm, $filePerm);
	}

	static function psr6(CacheStore $cacheStore): Psr6CacheStore {
		return new Psr6CacheStore($cacheStore);
	}

	static function psr16(CacheStore $cacheStore): Psr16CacheStore {
		return new Psr16CacheStore($cacheStore);
	}
}