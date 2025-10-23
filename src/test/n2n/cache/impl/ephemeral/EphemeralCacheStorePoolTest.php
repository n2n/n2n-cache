<?php
namespace n2n\cache\impl\ephemeral;

use PHPUnit\Framework\TestCase;
use n2n\cache\impl\CacheStorePools;
use n2n\cache\CharacteristicsList;

class EphemeralCacheStorePoolTest extends TestCase {

	function setUp(): void {
	}

	function testLookupAndClear() {
		$pool = CacheStorePools::ephemeral();
		$pool->lookupCacheStore('ns')->store('name', CharacteristicsList::fromArg([]), 'val');
		$this->assertEquals('val', $pool->lookupCacheStore('ns')->get('name', CharacteristicsList::fromArg([]))->getData());

		$pool->clear();

		$this->assertNull($pool->lookupCacheStore('ns')->get('name', CharacteristicsList::fromArg([])));
	}
}