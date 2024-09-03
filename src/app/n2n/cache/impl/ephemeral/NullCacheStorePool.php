<?php

namespace n2n\cache\impl\ephemeral;

use n2n\cache\CacheStorePool;
use n2n\cache\CacheStore;

class NullCacheStorePool implements CacheStorePool{

	function lookupCacheStore(string $namespace): CacheStore {
		return new NullCacheStore();
	}

	function clear(): void {
		$this->cacheStores = [];
	}
}