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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use n2n\cache\CacheStore;
use n2n\cache\UnsupportedCacheStoreOperationException;
use n2n\util\type\ArgUtils;

/**
 * If any operation failed due to CacheStore related errors, a CacheStoreOperationFailedException should be thrown.
 */
class Psr6CacheStore implements CacheItemPoolInterface {
	private CacheStore $cacheStore;
	/**
	 * @var CacheItemInterface[]
	 */
	private array $deferredItems = [];

	public function __construct(CacheStore $cacheStore) {
		$this->cacheStore = $cacheStore;
	}

	/**
	 * @inheritDoc
	 */
	public function getItem(string $key): CacheItemInterface {
		$cacheItem = $this->cacheStore->get($key, []);

		if ($cacheItem === null) {
			return new Psr6CacheItem($key, null, false);
		}

		return new Psr6CacheItem($key, $cacheItem->getData(), true);
	}

	/**
	 * @inheritDoc
	 */
	public function getItems(array $keys = []): iterable {
		$cacheItems = [];
		foreach ($keys as $key) {
			$cacheItems[] = $this->getItem($key);
		}

		return $cacheItems;
	}

	/**
	 * @inheritDoc
	 */
	public function hasItem(string $key): bool {
		return $this->cacheStore->get($key, []) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): bool {
		try {
			$this->cacheStore->clear();
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteItem(string $key): bool {
		try {
			$this->cacheStore->remove($key, []);
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteItems(array $keys): bool {
		try {
			foreach ($keys as $key) {
				$this->cacheStore->remove($key, []);
			}
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function save(CacheItemInterface $item): bool {
		ArgUtils::assertTrue(assert($item instanceof Psr6CacheItem));

		$now = new \DateTime();
		try {
			$this->cacheStore->store($item->getKey(), [], $item->get(), $item->calcTtl($now), $now);
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function saveDeferred(CacheItemInterface $item): bool {
		$this->deferredItems[] = $item;
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function commit(): bool {
		try {
			while (null !== ($deferredItem = array_shift($this->deferredItems))) {
				$this->save($deferredItem);
			}
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}
}