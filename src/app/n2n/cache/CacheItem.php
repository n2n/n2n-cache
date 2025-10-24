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


	/**
	 * @param string $name
	 * @param CharacteristicsList $characteristicsList
	 * @param mixed $data
	 */
	public function __construct(private string $name, private CharacteristicsList $characteristicsList, public mixed $data) {
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
	 * @return CharacteristicsList
	 */
	public function getCharacteristicsList(): CharacteristicsList {
		return $this->characteristicsList;
	}

	public function setCharacteristicsList(CharacteristicsList $characteristicsList): void {
		$this->characteristicsList = $characteristicsList;
	}

	/**
	 * @param CharacteristicsList $characteristics
	 * @return bool
	 */
	function matchesCharacteristics(CharacteristicsList $characteristics): bool {
		return $this->characteristicsList->equals($characteristics);
	}

	/**
	 * @param CharacteristicsList $characteristicNeedlesList
	 * @return bool
	 */
	function containsCharacteristics(CharacteristicsList $characteristicNeedlesList): bool {
		return $this->characteristicsList->contains($characteristicNeedlesList);
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

}
