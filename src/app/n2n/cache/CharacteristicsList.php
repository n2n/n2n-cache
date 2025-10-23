<?php

namespace n2n\cache;

use n2n\util\type\ArgUtils;
use n2n\util\StringUtils;

class CharacteristicsList {

	function __construct(private array $characteristics) {
		ArgUtils::valArray($characteristics, 'scalar');
	}

	function toArray(): array {
		return $this->characteristics;
	}

	static function fromArgs(array $args): CharacteristicsList {
		foreach ($args as $key => $value) {
			if (is_scalar($value)) {
				continue;
			}

			try {
				$args[$key] = StringUtils::strOf($value, false);
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}

		return new CharacteristicsList($args);
	}
}