<?php

namespace n2n\cache\impl\psr;

use n2n\cache\CacheItem;
use Psr\Cache\CacheItemInterface;

class Psr6CacheItem implements CacheItemInterface {

	private int|null|\DateInterval $ttl;
	private ?\DateTimeInterface $expiresAt;

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
		$now = new \DateTime();
		$this->expiresAt = $expiration;
		$this->ttl = null;
		return $this;
	}

	function getExpiresAt(): ?\DateTimeInterface {
		return $this->expiresAt;
	}

	/**
	 * @inheritDoc
	 */
	public function expiresAfter(\DateInterval|int|null $ttl): static {
		if (is_int($ttl)) {
			$ttl = new \DateInterval('');
			$ttl->s = $ttl;
		}

		$this->ttl = $ttl;
		$this->expiresAt = null;
		return $this;
	}

	function getExpiresAfter(): \DateInterval|null {
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