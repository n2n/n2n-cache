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

    public function store(string $name, CharacteristicsList $characteristicsList, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {
		$this->remove($name, $characteristicsList);
		$this->nsStore($name)[] = new CacheItem($name, $characteristicsList, $data);
    }

	/**
	 * Gets a CacheItem with matching characteristics.
	 * Returns null if none is found.
	 * @param string $name
	 * @param CharacteristicsList $characteristicsList
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem|null
	 */
    public function get(string $name, CharacteristicsList $characteristicsList, ?\DateTimeInterface $now = null): ?CacheItem {
		foreach ($this->nsStore($name) as $cacheItem) {
			if ($cacheItem->matchesCharacteristics($characteristicsList)) {
				return $cacheItem;
			}
		}

		return null;
    }

	/**
	 * Removes a CacheItem with matching characteristics if it exists.
	 * @param string $name
	 * @param CharacteristicsList $characteristicsList
	 * @return void
	 */
    public function remove(string $name, CharacteristicsList $characteristicsList): void {
        foreach ($this->nsStore($name) as $i => $cacheItem) {
			if ($cacheItem->matchesCharacteristics($characteristicsList)) {
				unset($this->cacheItems[$name][$i]);
				return;
			}
		}
    }

	/**
	 * @param string $name
	 * @param CharacteristicsList|null $characteristicNeedlesList
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem[]
	 */
    public function findAll(string $name, ?CharacteristicsList $characteristicNeedlesList = null, ?\DateTimeInterface $now = null): array {
        $found = [];
		$cacheItems = $this->nsStore($name);

		if (null === $characteristicNeedlesList) {
			return $cacheItems;
		}

		foreach ($cacheItems as $cacheItem) {
			if (!$cacheItem->containsCharacteristics($characteristicNeedlesList)) {
				continue;
			}

			$found[] = $cacheItem;
		}

		return $found;
    }

    public function removeAll(?string $name, ?CharacteristicsList $characteristicNeedlesList = null): void {
		if ($name === null && $characteristicNeedlesList === null) {
			$this->clear();
			return;
		}

		if ($characteristicNeedlesList === null) {
			unset($this->cacheItems[$name]);
			return;
		}

		if ($name === null) {
			foreach ($this->cacheItems as $namespace => $cacheItems) {
				$this->removeAllContainingCharacteristics($namespace, $characteristicNeedlesList);
			}
		} else {
			$this->removeAllContainingCharacteristics($name, $characteristicNeedlesList);
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