<?php
namespace n2n\cache\impl\ephemeral;

use PHPUnit\Framework\TestCase;
use n2n\cache\impl\CacheStorePools;

class EphemeralCacheStorePoolTest extends TestCase {

	function setUp(): void {
	}

	function testLookupAndClear() {
		$pool = CacheStorePools::ephemeral();
		$pool->lookupCacheStore('ns')->store('name', [], 'val');
		$this->assertEquals('val', $pool->lookupCacheStore('ns')->get('name', [])->getData());

		$pool->clear();

		$this->assertNull($pool->lookupCacheStore('ns')->get('name', []));
	}
}