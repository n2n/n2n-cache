<?php

namespace n2n\cache\impl\psr;

use Psr\Cache\CacheItemInterface;

class Psr6CacheItem implements CacheItemInterface {

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
		return /*$this->isHit() ?*/ $this->data /*: null*/;
	}

	/**
	 * @inheritDoc
	 */
	public function isHit(): bool {
		return $this->hit;
//		if (!$this->hit) {
//			return false;
//		}
//
//		if ($this->expiresAt === null) {
//			return true;
//		}
//
//		return $this->currentTime() < $this->expiresAt;
	}

	public function setHit(bool $hit): void {
		$this->hit = $hit;
	}

	/**
	 * @inheritDoc
	 */
	public function set(mixed $value): static {
		$this->data = $value;
		$this->hit = true;
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function expiresAt(?\DateTimeInterface $expiration): static {
		$this->expiresAt = $expiration;
		return $this;
	}

	public function getExpiresAt(): ?\DateTimeInterface {
		return $this->expiresAt;
	}

	/**
	 * @inheritDoc
	 */
	public function expiresAfter(\DateInterval|int|null $ttl): static {
		if ($ttl === null) {
			$this->expiresAt = null;
			return $this;
		}
		$ttlDateInterval = PsrUtils::toDateIntervalOrNull($ttl);
		$this->expiresAt = $this->currentTime()->add($ttlDateInterval);
		return $this;
	}

	function calcTtl(): ?\DateInterval {
		if ($this->expiresAt !== null) {
			return $this->currentTime()->diff($this->expiresAt);
		}
		return null;
	}

	protected function currentTime(): \DateTime {
		return new \DateTime('now');
	}
}