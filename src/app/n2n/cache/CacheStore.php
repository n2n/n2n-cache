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

interface CacheStore {
	/**
	 * @param string $name
	 * @param string[] $characteristics e. g. <code>['category' => 'news', 'mode' => 'some-mode']</code>
	 * @param mixed $data
	 */
	public function store(string $name, array $characteristics, mixed $data, \DateTime $lastMod = null);

	/**
	 * Returns the CacheItem which has been stored with exactly these params (name and characteristics).
	 *
	 * @param string $name
	 * @param string[] $characteristics
	 * @return CacheItem|null null if item does not exist
	 * @throws CorruptedCacheStoreException
	 */
	public function get(string $name, array $characteristics): ?CacheItem;

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
	 * @return CacheItem[]
	 */
	public function findAll(string $name, array $characteristicNeedles = null): array;

	/**
	 * Returns the CacheItems which has been stored with exactly this name (if provided) and contains all the passed
	 * characteristicNeedles (see {@link CacheStore::findAll()} to understand the concept of characteristicNeedles).
	 * @param string|null $name
	 * @param string[] $characteristicNeedles
	 */
	public function removeAll(?string $name, array $characteristicNeedles = null);

	/**
	 * Removes all stored CacheItems.
	 */
	public function clear();
}
