<?php

namespace n2n\cache\impl\ephemeral;

use n2n\cache\CacheItem;
use n2n\cache\CacheStore;
use n2n\cache\CharacteristicsList;

class EphemeralCacheStore implements CacheStore {
	/**
	 * 
	 * @var CacheItem[][] $cacheItems
	 */
    private $cacheItems = [];

    public function store(string $name, CharacteristicsList $characteristics, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {
		$this->remove($name, $characteristics);
		$this->nsStore($name)[] = new CacheItem($name, $characteristics, $data);
    }

	/**
	 * Gets a CacheItem with matching characteristics.
	 * Returns null if none is found.
	 * @param string $name
	 * @param CharacteristicsList $characteristics
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem|null
	 */
    public function get(string $name, CharacteristicsList $characteristics, ?\DateTimeInterface $now = null): ?CacheItem {
		foreach ($this->nsStore($name) as $cacheItem) {
			if ($cacheItem->matchesCharacteristics($characteristics)) {
				return $cacheItem;
			}
		}

		return null;
    }

	/**
	 * Removes a CacheItem with matching characteristics if it exists.
	 * @param string $name
	 * @param CharacteristicsList $characteristics
	 * @return void
	 */
    public function remove(string $name, CharacteristicsList $characteristics): void {
        foreach ($this->nsStore($name) as $i => $cacheItem) {
			if ($cacheItem->matchesCharacteristics($characteristics)) {
				unset($this->cacheItems[$name][$i]);
				return;
			}
		}
    }

	/**
	 * @param string $name
	 * @param CharacteristicsList|null $characteristicNeedles
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem[]
	 */
    public function findAll(string $name, ?CharacteristicsList $characteristicNeedles = null, ?\DateTimeInterface $now = null): array {
        $found = [];
		$cacheItems = $this->nsStore($name);

		if (null === $characteristicNeedles) {
			return $cacheItems;
		}

		foreach ($cacheItems as $cacheItem) {
			if (!$cacheItem->containsCharacteristics($characteristicNeedles)) {
				continue;
			}

			$found[] = $cacheItem;
		}

		return $found;
    }

    public function removeAll(?string $name, ?CharacteristicsList $characteristicNeedles = null): void {
		if ($name === null && $characteristicNeedles === null) {
			$this->clear();
			return;
		}

		if ($characteristicNeedles === null) {
			unset($this->cacheItems[$name]);
			return;
		}

		if ($name === null) {
			foreach ($this->cacheItems as $namespace => $cacheItems) {
				$this->removeAllContainingCharacteristics($namespace, $characteristicNeedles);
			}
		} else {
			$this->removeAllContainingCharacteristics($name, $characteristicNeedles);
		}
    }

	public function removeAllContainingCharacteristics(string $namespace, CharacteristicsList $characteristicNeedles): void {
		foreach ($this->nsStore($namespace) as $i => $cacheItem) {
			if ($cacheItem->containsCharacteristics($characteristicNeedles)) {
				unset($this->cacheItems[$namespace][$i]);
			}
		}
	}

    public function clear(): void {
        $this->cacheItems = [];
    }

	private function &nsStore(string $namespace): array {
		if (!array_key_exists($namespace, $this->cacheItems)) {
			$this->cacheItems[$namespace] = [];
		}

		return $this->cacheItems[$namespace];
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {

	}
}