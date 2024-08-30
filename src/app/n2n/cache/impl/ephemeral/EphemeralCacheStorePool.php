<?php

namespace n2n\cache\impl\ephemeral;

use n2n\cache\CacheStorePool;
use n2n\cache\CacheStore;

class EphemeralCacheStorePool implements CacheStorePool{

	private array $cacheStores = [];

	function lookupCacheStore(string $namespace): CacheStore {
		return $this->cacheStores[$namespace] ?? $this->cacheStores[$namespace] = new EphemeralCacheStore();
	}

	function clear(): void {
		$this->cacheStores = [];
	}
}