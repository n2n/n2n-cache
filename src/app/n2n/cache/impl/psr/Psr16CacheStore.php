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
use n2n\cache\CacheItem;
use Psr\SimpleCache\CacheInterface;
use n2n\cache\CacheStoreOperationFailedException;

/**
 * If any operation failed due to CacheStore related errors, a CacheStoreOperationFailedException should be thrown.
 */
class Psr16CacheStore implements CacheInterface {
	private CacheStore $cacheStore;

	public function __construct(CacheStore $cacheStore) {
		$this->cacheStore = $cacheStore;
	}

	/**
	 * @inheritDoc
	 */
	public function get(string $key, mixed $default = null): mixed {
		return $this->cacheStore->get($key, [])?->getData() ?? $default;
	}

	/**
	 * @inheritDoc
	 */
	public function set(string $key, mixed $value, \DateInterval|int $ttl = null): bool {
		try {
			$this->cacheStore->store($key, [], $value, $ttl);
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key): bool {
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
		$items = [];
		foreach ($keys as $key) {
			$items[] = $this->get($key, $default);
		}
		return $items;
	}

	/**
	 * @inheritDoc
	 */
	public function setMultiple(iterable $values, \DateInterval|int $ttl = null): bool {
		try {
			return true;
		} catch (CacheStoreOperationFailedException $e) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteMultiple(iterable $keys): bool {
		foreach ($keys as $key) {
			try {
				$this->cacheStore->remove($key, []);
			} catch (CacheStoreOperationFailedException $e) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function has(string $key): bool {
		return ($this->cacheStore->get($key, []) !== null);
	}
}
