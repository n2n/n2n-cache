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

class CacheItem {
	private string $name;
	private array $characteristics;
	public mixed $data;
	private \DateTimeInterface $createdAt;
	private ?\DateTimeInterface $expiresAt;

	/**
	 * @param string $name
	 * @param string[] $characteristics
	 * @param mixed $data
	 */
	public function __construct(string $name, array $characteristics, mixed $data) {
		$this->name = $name;
		$this->setCharacteristics($characteristics);
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void {
		$this->name = $name;
	}

	/**
	 * @return array
	 */
	public function getCharacteristics(): array {
		return $this->characteristics;
	}

	/**
	 * @param array $characteristics
	 */
	public function setCharacteristics(array $characteristics): void {
//		ArgUtils::valArray($characteristics, 'string');
		$this->characteristics = $characteristics;
	}

	/**
	 * @param array $characteristics
	 * @return bool
	 */
	function matchesCharacteristics(array $characteristics): bool {
		return $this->characteristics === $characteristics;
	}

	/**
	 * @param array $characteristicNeedles
	 * @return bool
	 */
	function containsCharacteristics(array $characteristicNeedles): bool {
		foreach ($characteristicNeedles as $key => $value) {
			if (!array_key_exists($key, $this->characteristics)
					|| $value !== $this->characteristics[$key]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return mixed
	 */
	public function getData(): mixed {
		return $this->data;
	}

	/**
	 * @param mixed $data
	 */
	public function setData(mixed $data): void {
		$this->data = $data;
	}

	// TODO: DboCacheEngine::CREATED_AT_COLUMN and DboCacheEngine::EXPIRES_AT_COLUMN (int seconds) in DateTimeImmutable/DateInterval umwandeln und übergeben. or remove
	function getCreatedAt(): ?\DateTimeInterface {
		return $this->createdAt;
	}

	function setCreatedAt(\DateTimeInterface $createdAt): static {
		$this->createdAt = $createdAt;
		return $this;
	}

	function getExpiresAt(): ?\DateTimeInterface {
		return $this->expiresAt;
	}

	public function setExpiresAt(?\DateTimeInterface $expiresAt): static {
		$this->expiresAt = $expiresAt;
		return $this;
	}

}
