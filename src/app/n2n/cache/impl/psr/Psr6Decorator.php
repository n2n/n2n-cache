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
use n2n\util\StringUtils;

/**
 * If any operation failed due to CacheStore related errors, a CacheStoreOperationFailedException should be thrown.
 */
class Psr6Decorator implements CacheItemPoolInterface {
	private CacheStore $cacheStore;
	/**
	 * @var CacheItemInterface[]
	 */
	private array $deferredItems = [];

	public function __construct(CacheStore $cacheStore) {
		$this->cacheStore = $cacheStore;
	}

	function __destruct() {
		$this->commit();
	}

	/**
	 * @throws Psr6InvalidArgumentException
	 */
	private function valKey(mixed $key): void {
		$invalidCharacters = '{}()/\@:'; //psr-6 define this chars as invalid "{}()/\@:"

		//psr-6 expect a string with at least one char
		if (!is_string($key) || 1 === preg_match('#[' . preg_quote($invalidCharacters) . ']#', $key) || $key === ''
				|| $key !== StringUtils::convertNonPrintables($key)) {
			throw new Psr6InvalidArgumentException('The provided key is not valid: '
					. StringUtils::strOf($key, true));
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getItem(string $key): CacheItemInterface {
		$this->valKey($key);
		if (array_key_exists($key, $this->deferredItems)) {
			if ($this->deferredItems[$key]->getExpiresAt() === null || $this->deferredItems[$key]->getExpiresAt() > new \DateTimeImmutable()) {
				return new Psr6CacheItem($key, $this->deferredItems[$key]->get(), true);
			}
		}
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
		array_walk($keys, fn ($key) => $this->valKey($key));
		$cacheItems = [];
		foreach ($keys as $key) {
			$cacheItems[$key] = $this->getItem($key);
		}

		return $cacheItems;
	}

	/**
	 * @inheritDoc
	 */
	public function hasItem(string $key): bool {
		$this->valKey($key);
		if (!isset($this->deferredItems[$key])) {
			return $this->cacheStore->get($key, []) !== null;
		}

		return ($this->deferredItems[$key]->getExpiresAt() === null
				|| $this->deferredItems[$key]->getExpiresAt() > new \DateTimeImmutable());
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): bool {
		try {
			$this->deferredItems = [];
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
			return $this->deleteItems([$key]);
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteItems(array $keys): bool {
		array_walk($keys, fn ($key) => $this->valKey($key));
		try {
			foreach ($keys as $key) {
				$this->valKey($key);
				$this->cacheStore->remove($key, []);
				unset($this->deferredItems[$key]);
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
			$this->cacheStore->store($item->getKey(), [], $item->get(), $item->calcTtl(), $now);
//			$item->setHit(true);
			return true;
		} catch (UnsupportedCacheStoreOperationException) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function saveDeferred(CacheItemInterface $item): bool {
		$this->deferredItems[$item->getKey()] = $item;
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