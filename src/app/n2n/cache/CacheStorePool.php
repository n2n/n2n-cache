<?php

namespace n2n\cache;

interface CacheStorePool {


	function lookupCacheStore(string $namespace): CacheStore;

	function clear(): void;
}