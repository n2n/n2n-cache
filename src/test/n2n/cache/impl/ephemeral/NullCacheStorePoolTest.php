<?php
namespace n2n\cache\impl\ephemeral;

use PHPUnit\Framework\TestCase;
use n2n\cache\impl\CacheStorePools;

class NullCacheStorePoolTest extends TestCase {

	function setUp(): void {
	}

	function testLookupAndClear() {
		$pool = CacheStorePools::null();
		$store = $pool->lookupCacheStore('ns');
		$store->store('name', [], 'val');

		$this->assertNull($store->get('name', []));
		$this->assertNull($pool->lookupCacheStore('ns')->get('name', []));

		$pool->clear();

		$this->assertNull($pool->lookupCacheStore('ns')->get('name', []));
	}
}