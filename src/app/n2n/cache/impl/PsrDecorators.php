<?php

namespace n2n\cache\impl;

use n2n\cache\CacheStore;
use n2n\cache\impl\psr\Psr6Decorator;
use n2n\cache\impl\psr\Psr16Decorator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

class PsrDecorators {

	static function psr6(CacheStore $cacheStore): CacheItemPoolInterface {
		return new Psr6Decorator($cacheStore);
	}

	static function psr16(CacheStore $cacheStore): CacheInterface {
		return new Psr16Decorator($cacheStore);
	}
}