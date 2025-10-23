<?php
namespace n2n\cache\impl\ephemeral;

use PHPUnit\Framework\TestCase;
use n2n\cache\impl\CacheStorePools;
use n2n\cache\CharacteristicsList;

class NullCacheStorePoolTest extends TestCase {

	function setUp(): void {
	}

	function testLookupAndClear() {
		$pool = CacheStorePools::null();
		$store = $pool->lookupCacheStore('ns');
		$store->store('name', CharacteristicsList::fromArg([]), 'val');

		$this->assertNull($store->get('name', CharacteristicsList::fromArg([])));
		$this->assertNull($pool->lookupCacheStore('ns')->get('name', CharacteristicsList::fromArg([])));

		$pool->clear();

		$this->assertNull($pool->lookupCacheStore('ns')->get('name', CharacteristicsList::fromArg([])));
	}
}