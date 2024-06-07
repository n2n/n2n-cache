<?php

namespace n2n\cache\impl;

use n2n\cache\impl\persistence\DboCacheStore;
use n2n\spec\dbo\Dbo;
use n2n\util\io\fs\FsPath;
use n2n\cache\impl\fs\FileCacheStore;
use n2n\cache\impl\ephemeral\EphemeralCacheStore;

class CacheStores {

	static function dbo(Dbo $dbo): DboCacheStore {
		return new DboCacheStore($dbo);
	}

	static function file(FsPath $dirPath, string|int $dirPerm = null, string|int $filePerm = null): FileCacheStore {
		return new FileCacheStore($dirPath, $dirPerm, $filePerm);
	}

	static function ephemeral(): EphemeralCacheStore {
		return new EphemeralCacheStore();
	}

}