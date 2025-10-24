<?php

namespace n2n\cache\impl\ephemeral;

use n2n\cache\CacheStore;
use n2n\cache\CacheItem;
use n2n\cache\CharacteristicsList;

class NullCacheStore implements CacheStore {

	public function store(string $name, CharacteristicsList $characteristicsList, mixed $data, ?\DateInterval $ttl = null, ?\DateTimeInterface $now = null): void {
	}

	public function get(string $name, CharacteristicsList $characteristicsList, ?\DateTimeInterface $now = null): ?CacheItem {
		return null;
	}

	public function remove(string $name, CharacteristicsList $characteristicsList): void {
	}

	public function findAll(string $name, ?CharacteristicsList $characteristicNeedlesList = null, ?\DateTimeInterface $now = null): array {
		return [];
	}

	public function removeAll(?string $name, ?CharacteristicsList $characteristicNeedlesList = null): void {
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
	}

	public function clear(): void {
	}
}