<?php

namespace n2n\cache\impl\psr;

use Psr\Cache\CacheItemInterface;
use n2n\util\ex\ExUtils;

class Psr6CacheItem implements CacheItemInterface {

	private int|null|\DateInterval $ttl = null;
	private ?\DateTimeInterface $expiresAt = null;
	function __construct(private string $key, private mixed $data, private bool $hit) {

	}

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return $this->key;
	}

	/**
	 * @inheritDoc
	 */
	public function get(): mixed {
		return $this->data;
	}

	/**
	 * @inheritDoc
	 */
	public function isHit(): bool {
		return $this->hit;
	}

	/**
	 * @inheritDoc
	 */
	public function set(mixed $value): static {
		$this->data = $value;
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function expiresAt(?\DateTimeInterface $expiration): static {
		$this->expiresAt = $expiration;
		$this->ttl = null;
		return $this;
	}

	public function getExpiresAt(): ?\DateTimeInterface {
		return $this->expiresAt;
	}

	/**
	 * @inheritDoc
	 */
	public function expiresAfter(\DateInterval|int|null $ttl): static {
		$ttlDateInterval = $ttl;
		if (is_int($ttl)) {
			$ttlDateInterval = ExUtils::try(fn() => new \DateInterval('PT' . abs($ttl) . 'S'));
			$ttlDateInterval->invert = $ttl < 0 ? 1 : 0; //invert can not be set by constructor
		}

		$this->ttl = $ttlDateInterval;
		$this->expiresAt = null;
		return $this;
	}

	public function getExpiresAfter(): \DateInterval|null {
		return $this->ttl;
	}

	function calcTtl(\DateTimeInterface $now): ?\DateInterval {
		if ($this->ttl !== null) {
			return $this->ttl;
		}

		if ($this->expiresAt !== null) {
			return $now->diff($this->expiresAt);
		}

		return null;
	}
}