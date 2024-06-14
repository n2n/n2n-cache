<?php

namespace n2n\cache\impl\psr;

use DateInterval;
use n2n\util\ex\ExUtils;
use n2n\util\StringUtils;

class PsrUtils {

	static function toDateIntervalOrNull(DateInterval|int|null $ttl = null): DateInterval|null {
		if (!is_int($ttl)) {
			return $ttl;
		}

		$ttlDateInterval = ExUtils::try(fn() => new DateInterval('PT' . abs($ttl) . 'S'));
		$ttlDateInterval->invert = $ttl < 0 ? 1 : 0; //invert can not be set by constructor
		return $ttlDateInterval;
	}

	static function isValKey(mixed $key): bool {
		$invalidCharacters = '{}()/\@:'; //psr-6 and psr-16 define this chars as invalid "{}()/\@:"

		//psr-6 and psr-16 expect a string with at least one char
		if (!is_string($key) || 1 === preg_match('#[' . preg_quote($invalidCharacters) . ']#', $key) || $key === ''
				|| $key !== StringUtils::convertNonPrintables($key)) {
			return false;
		}
		return true;
	}
}