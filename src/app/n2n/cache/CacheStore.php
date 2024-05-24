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
namespace n2n\cache;

/**
 * If any operation failed due to CacheStore related errors, a CacheStoreOperationFailedException should be thrown.
 */
interface CacheStore {
	/**
	 * Note: If tll was provided but is not supported by the given CacheStore, a
	 * {@link UnsupportedCacheStoreOperationException} must be thrown.
	 *
	 * @param string $name
	 * @param string[] $characteristics e. g. <code>['category' => 'news', 'mode' => 'some-mode']</code>
	 * @param mixed $data
	 * @param \DateTimeInterface|null $now
	 * @param \DateInterval|null $ttl
	 */
	public function store(string $name, array $characteristics, mixed $data, \DateInterval $ttl = null,
			\DateTimeInterface $now = null): void;

	/**
	 * Returns the CacheItem which has been stored with exactly these params (name and characteristics).
	 *
	 * @param string $name
	 * @param string[] $characteristics
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem|null null if item does not exist
	 * @throws CorruptedCacheStoreException
	 */
	public function get(string $name, array $characteristics, \DateTimeInterface $now = null): ?CacheItem;

	/**
	 * Remove the data which has been stored with exactly these params (name and characteristics).
	 *
	 * @param string $name
	 * @param string[] $characteristics
	 */
	public function remove(string $name, array $characteristics);

	/**
	 * Returns the CacheItems which has been stored with exactly this name and contains all the passed characteristicNeedles.
	 * For example if the data was stored with the characteristics <code>['category' => 'news', 'mode' => 'some-mode']</code>
	 * the characteristicNeedles ['category' => 'news'] would match, but characteristicNeedles
	 * ['category' => 'news', 'mode' => 'some-other-mode] not.
	 *
	 * @param string $name
	 * @param string[] $characteristicNeedles
	 * @param \DateTimeInterface|null $now
	 * @return CacheItem[]
	 */
	public function findAll(string $name, array $characteristicNeedles = null, \DateTimeInterface $now = null): array;

	/**
	 * Returns the CacheItems which has been stored with exactly this name (if provided) and contains all the passed
	 * characteristicNeedles (see {@link CacheStore::findAll()} to understand the concept of characteristicNeedles).
	 * @param string|null $name
	 * @param string[] $characteristicNeedles
	 */
	public function removeAll(?string $name, array $characteristicNeedles = null);

	/**
	 * Remove all CacheItems that are considered old by the ttl parameter provided when stored (see {@link self::store()})
	 * or by the individual specification of the given CacheStore.
	 *
	 * If the maxLifetime parameter is not null, it also removes all CacheItems older than this maxLifetime regardless
	 * of the ttl parameter specified when stored ({@link self::store()}).
	 *
	 * Note: If maxLifetime was provided but is not supported by the given CacheStore, a
	 * {@link UnsupportedCacheStoreOperationException} must be thrown.
	 *
	 * @param \DateInterval|null $maxLifetime
	 * @param \DateTimeInterface|null $now
	 * @return void
	 */
	public function garbageCollect(\DateInterval $maxLifetime = null, \DateTimeInterface $now = null): void;

	/**
	 * Removes all stored CacheItems.
	 */
	public function clear();
}
