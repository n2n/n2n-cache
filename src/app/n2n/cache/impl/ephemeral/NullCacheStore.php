<?php

namespace n2n\cache\impl\ephemeral;

use n2n\cache\CacheStore;
use n2n\cache\CacheItem;

class NullCacheStore implements CacheStore {

	public function store(string $name, array $characteristics, mixed $data, ?\DateInterval $ttl = null, ?\DateTimeInterface $now = null): void {
	}

	public function get(string $name, array $characteristics, ?\DateTimeInterface $now = null): ?CacheItem {
		return null;
	}

	public function remove(string $name, array $characteristics): void {
	}

	public function findAll(string $name, ?array $characteristicNeedles = null, ?\DateTimeInterface $now = null): array {
		return [];
	}

	public function removeAll(?string $name, ?array $characteristicNeedles = null): void {
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
	}

	public function clear(): void {
	}
}