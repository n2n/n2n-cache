<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */

namespace n2n\cache\impl\psr;
use n2n\cache\UnsupportedCacheStoreOperationException;
use n2n\cache\CacheStore;
use Psr\SimpleCache\CacheInterface;
use n2n\cache\CacheStoreOperationFailedException;
use n2n\util\StringUtils;

/**
 * If any operation failed due to CacheStore related errors, a CacheStoreOperationFailedException should be thrown.
 */
class Psr16Decorator implements CacheInterface {
	private CacheStore $cacheStore;

	public function __construct(CacheStore $cacheStore) {
		$this->cacheStore = $cacheStore;
	}

	/**
	 * @throws Psr16InvalidArgumentException
	 */
	private function valKey(mixed $key): void {
		if (!PsrUtils::isValKey($key)) {
			throw new Psr16InvalidArgumentException('The provided key is not valid: ' . StringUtils::strOf($key, true));
		}
	}

	/**
	 * @inheritDoc
	 */
	public function get(string $key, mixed $default = null): mixed {
		$this->valKey($key);
		return $this->cacheStore->get($key, [])?->getData() ?? $default;
	}

	/**
	 * @inheritDoc
	 */
	public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool {
		$this->valKey($key);
		$ttlDateInterval = PsrUtils::toDateIntervalOrNull($ttl);
		try {
			$this->cacheStore->store($key, [], $value, $ttlDateInterval, new \DateTimeImmutable('now'));
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key): bool {
		$this->valKey($key);
		try {
			$this->cacheStore->remove($key, []);
			return true;
		} catch (CacheStoreOperationFailedException $e) {
			return false;
		}

	}

	/**
	 * @inheritDoc
	 */
	public function clear(): bool {
		try {
			$this->cacheStore->clear();
			return true;
		} catch (CacheStoreOperationFailedException $e) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getMultiple(iterable $keys, mixed $default = null): iterable {
		array_walk($keys, fn ($key) => $this->valKey($key));
		$items = [];
		foreach ($keys as $key) {
			$items[$key] = $this->get($key, $default);
		}
		return $items;
	}

	/**
	 * @inheritDoc
	 */
	public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool {
		try {
			foreach ($values as $key => $value) {
				$this->set($key, $value, $ttl);
			}
			return true;
		} catch (CacheStoreOperationFailedException $e) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteMultiple(iterable $keys): bool {
		array_walk($keys, fn ($key) => $this->valKey($key));
		try {
			foreach ($keys as $key) {
				$this->cacheStore->remove($key, []);
			}
			return true;
		} catch (CacheStoreOperationFailedException $e) {
			return false;
		}

	}

	/**
	 * @inheritDoc
	 */
	public function has(string $key): bool {
		$this->valKey($key);
		return ($this->cacheStore->get($key, []) !== null);
	}
}
