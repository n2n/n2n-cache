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

	static function fromArg(array|CharacteristicsList $arg): CharacteristicsList {
		if ($arg instanceof CharacteristicsList) {
			return $arg;
		}

		foreach ($arg as $key => $value) {
			if (is_scalar($value)) {
				continue;
			}

			try {
				$arg[$key] = StringUtils::strOf($value, false);
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}

		return new CharacteristicsList($arg);
	}
}