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
namespace n2n\cache\impl\fs;

use n2n\cache\CacheStore;
use n2n\util\io\fs\FsPath;
use n2n\util\io\IoUtils;
use n2n\util\ex\IllegalStateException;
use n2n\util\HashUtils;
use n2n\cache\CacheItem;
use n2n\util\StringUtils;
use n2n\util\UnserializationFailedException;
use n2n\cache\CorruptedCacheStoreException;
use n2n\util\io\IoException;
use n2n\util\io\fs\FileOperationException;
use n2n\util\ex\UnsupportedOperationException;
use n2n\util\ex\ExUtils;
use n2n\concurrency\sync\impl\Sync;
use n2n\concurrency\sync\LockMode;
use n2n\concurrency\sync\Lock;
use n2n\cache\CacheStoreOperationFailedException;
use n2n\cache\CharacteristicsList;
use n2n\util\io\fs\FsPerm;

class FileCacheStore implements CacheStore {
	const CHARACTERISTIC_DELIMITER = '.';
	const CHARACTERISTIC_HASH_LENGTH = 4;
	const CACHE_FILE_SUFFIX = '.cache';
	const LOCK_FILE_SUFFIX = '.filelock';
	const LOCK_FOLDER = 'lock';
	const DATA_FOLDER = 'data';

	private FsPath $dirFsPath;
	private ?FsPerm $dirPerm;
	private ?FsPerm $filePerm;

	public function __construct(mixed $dirPath, FsPerm|string|int|null $dirPerm = null, FsPerm|string|int|null $filePerm = null) {

		$this->dirFsPath = new FsPath($dirPath);
		$this->dirPerm = FsPerm::build($dirPerm);
		$this->filePerm = FsPerm::build($filePerm);
	}

	public function getDirFsPath(): FsPath {
		return $this->dirFsPath;
	}

	public function setDirPerm(FsPerm|string|int|null $dirPerm): void {
		$this->dirPerm = FsPerm::build($dirPerm);
	}

	public function getDirPerm(): ?FsPerm {
		return $this->dirPerm;
	}

	public function setFilePerm(FsPerm|string|int|null $filePerm): void {
		$this->filePerm = FsPerm::build($filePerm);
	}

	public function getFilePerm(): ?FsPerm {
		return $this->filePerm;
	}

	private function createLockFsPath(FsPath $fileFsPath): FsPath {
		return $this->getLockDirFsPath()->ext(HashUtils::base36Sha256Hash((string) $fileFsPath) . FileCacheStore::LOCK_FILE_SUFFIX);
	}

	private function createReadLock(FsPath $fileFsPath): Lock {
		$lockFilePath = $this->createLockFsPath($fileFsPath);
		$this->mkLockDir();

		$lock = Sync::byFileLock($lockFilePath);
		ExUtils::try(fn () => $lock->acquire(LockMode::SHARED));
		if ($this->filePerm !== null) {
			ExUtils::try(fn () => $lockFilePath->chmod($this->filePerm));
		}
		return $lock;
	}

	private function createWriteLock(FsPath $fileFsPath): Lock {
		$lockFilePath = $this->createLockFsPath($fileFsPath);
		$this->mkLockDir();

		$lock = Sync::byFileLock($lockFilePath);
		ExUtils::try(fn () => $lock->acquire(LockMode::EXCLUSIVE));
		if ($this->filePerm !== null) {
			ExUtils::try(fn () => $lockFilePath->chmod($this->filePerm));
		}
		return $lock;
	}

	private function createNameDirFsPath($name): FsPath {
		if (IoUtils::hasSpecialChars($name)) {
			$name = HashUtils::base36Md5Hash($name);
		}
		return $this->getDataDirFsPath()->ext($name);
	}

	private function createFileName(CharacteristicsList $characteristicsList): string {
		$characteristics = $characteristicsList->toArray();
		ksort($characteristics);

		$fileName = HashUtils::base36Md5Hash(serialize($characteristics));
		foreach ($characteristics as $key => $value) {
			$fileName .= self::CHARACTERISTIC_DELIMITER . HashUtils::base36Md5Hash(
							serialize(array($key, $value)), self::CHARACTERISTIC_HASH_LENGTH);
		}

		return $fileName . self::CACHE_FILE_SUFFIX;
	}

	private function createFileGlobPattern(array $characteristicNeedles): string {
		ksort($characteristicNeedles);

		$fileName = '';
		foreach ($characteristicNeedles as $key => $value) {
			$fileName .= '*' . self::CHARACTERISTIC_DELIMITER . HashUtils::base36Md5Hash(
							serialize(array($key, $value)), self::CHARACTERISTIC_HASH_LENGTH);
		}

		return $fileName . '*' . self::CACHE_FILE_SUFFIX;
	}

	private function mkdirsWithUmaskOverwrite(FsPath $fsPath): void {
		if ($fsPath->isDir()) {
			return;
		}

		ExUtils::try(fn () => $fsPath->mkdirs($this->dirPerm));
		if ($this->dirPerm !== null) {
			// chmod after mkdirs because of possible umask restrictions.
			ExUtils::try(fn () => $fsPath->chmod($this->dirPerm));
		}
	}


	private function getDataDirFsPath(): FsPath {
		return $this->dirFsPath->ext([self::DATA_FOLDER]);
	}

	private function getLockDirFsPath(): FsPath {
		return $this->dirFsPath->ext([self::LOCK_FOLDER]);
	}

	private function mkLockDir(): void {
		$lockDirFsPath = $this->getLockDirFsPath();
		if ($lockDirFsPath->isDir()) {
			return;
		}

		$this->mkdirsWithUmaskOverwrite($this->dirFsPath);
		$this->mkdirsWithUmaskOverwrite($this->getLockDirFsPath());
	}

	private function mkNameDir(FsPath $nameDirFsPath): void {
		if ($nameDirFsPath->isDir()) {
			return;
		}

		$this->mkdirsWithUmaskOverwrite($this->dirFsPath);
		$this->mkdirsWithUmaskOverwrite($this->getDataDirFsPath());
		$this->mkdirsWithUmaskOverwrite($nameDirFsPath);
	}


	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::store()
	 */
	public function store(string $name, CharacteristicsList $characteristicsList, mixed $data, ?\DateInterval $ttl = null,
			?\DateTimeInterface $now = null): void {

		$nameDirPath = $this->createNameDirFsPath($name);
		$this->mkNameDir($nameDirPath);

		$fileFsPath = $nameDirPath->ext($this->createFileName($characteristicsList));

		$lock = $this->createWriteLock($fileFsPath);
		try {
			IoUtils::putContentsSafe($fileFsPath->__toString(), serialize(array(
					'characteristics' => $characteristicsList->toArray(),
					'data' => $data)));
		} catch (IoException $e) {
			throw new CacheStoreOperationFailedException($e->getMessage(), previous: $e);
		}

		if ($this->filePerm !== null) {
			// file cloud be removed by {@link self::clear()} in the meantime despite the active lock.
			ExUtils::try(fn () => $fileFsPath->chmod($this->filePerm, true));
		}
		$lock->release();
	}
	/**
	 * @param $name
	 * @param FsPath $filePath
	 * @return CacheItem|null null, if filePath no longer available.
	 * @throws CorruptedCacheStoreException
	 */
	private function read($name, FsPath $filePath): ?CacheItem {
		if (!$filePath->exists()) return null;

		$lock = $this->createReadLock($filePath);

		if (!$filePath->exists()) {
			$lock->release();
			return null;
		}

		try {
			$contents = IoUtils::getContentsSafe($filePath);
		} catch (IoException $e) {
			$lock->release();
			return null;
		}
		$lock->release();

		// file could be empty due to writing anomalies
		if (empty($contents)) {
			return null;
		}

		$attrs = null;
		try {
			$attrs = StringUtils::unserialize($contents);
		} catch (UnserializationFailedException $e) {
			throw new CorruptedCacheStoreException('Could not retrieve file: ' . $filePath, 0, $e);
		}

		if (!isset($attrs['characteristics']) || !is_array($attrs['characteristics']) || !isset($attrs['data'])) {
			throw new CorruptedCacheStoreException('Corrupted cache file: ' . $filePath);
		}

		try {
			$characteristicsList = new CharacteristicsList($attrs['characteristics']);
		} catch (\InvalidArgumentException $e) {
			throw new CorruptedCacheStoreException('Corrupted cache file: ' . $filePath, previous: $e);
		}

		$ci = new CacheItem($name, $characteristicsList, null);
		$ci->data = &$attrs['data'];
		return $ci;
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::get()
	 */
	public function get(string $name, CharacteristicsList $characteristicsList, ?\DateTimeInterface $now = null): ?CacheItem {
		$nameDirPath = $this->createNameDirFsPath($name);
		if (!$nameDirPath->exists()) return null;
		return $this->read($name, $nameDirPath->ext($this->createFileName($characteristicsList)));
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::remove()
	 */
	public function remove(string $name, CharacteristicsList $characteristicsList): void {
		$nameDirPath = $this->createNameDirFsPath($name);
		if (!$nameDirPath->exists()) return;

		$filePath = $nameDirPath->ext($this->createFileName($characteristicsList));
		$this->unlink($filePath);
	}

	/**
	 * @param FsPath $filePath
	 */
	private function unlink(FsPath $filePath): void {
		if (!$filePath->exists()) return;

		try {
			IoUtils::unlink($filePath->__toString());
		} catch (FileOperationException $e) {
			if ($filePath->exists()) {
				throw new IllegalStateException(self::class . ' was unable to delete data file: ' . $filePath,
						previous: $e);
			}
		}

		// these kind of locks do not work on distributed systems etc.
//		$lock = $this->createWriteLock($filePath);

//		if ($filePath->exists())  {
//			try {
//				IoUtils::unlink($filePath->__toString());
//			} catch (IoException $e) {
//				$lock->release(true);
//				throw $e;
//			}
//		}
//
//		$lock->release(true);
	}

	/**
	 * @param CharacteristicsList $characteristicNeedles
	 * @param CharacteristicsList $characteristics
	 * @return boolean
	 */
	private function inCharacteristics(CharacteristicsList $characteristicNeedles, CharacteristicsList $characteristics): bool {
		return $characteristics->contains($characteristicNeedles);
	}

	/**
	 * @param string|null $name
	 * @param array|null $characteristicNeedles
	 * @return FsPath[]
	 */
	private function findFilePaths(?string $name, ?array $characteristicNeedles = null): array {
		$fileGlobPattern = $this->createFileGlobPattern((array) $characteristicNeedles);

		if ($name === null) {
			return $this->getDataDirFsPath()->getChildren('*' . DIRECTORY_SEPARATOR . $fileGlobPattern);
		}

		$nameDirPath = $this->createNameDirFsPath($name);
		if (!$nameDirPath->exists()) {
			return [];
		}

		return $nameDirPath->getChildren($fileGlobPattern);

	}

	public function findAll(string $name, ?CharacteristicsList $characteristicNeedlesList = null, ?\DateTimeInterface $now = null): array {
		$cacheItems = array();

		foreach ($this->findFilePaths($name, $characteristicNeedlesList?->toArray()) as $filePath) {
			$cacheItem = $this->read($name, $filePath);
			if ($cacheItem === null) continue;

			if ($characteristicNeedlesList === null
					// hash collision detection
					|| $this->inCharacteristics($characteristicNeedlesList, $cacheItem->getCharacteristicsList())) {
				$cacheItems[] = $cacheItem;
			}
		}

		return $cacheItems;
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::removeAll()
	 */
	public function removeAll(?string $name, ?CharacteristicsList $characteristicNeedlesList = null): void {
		foreach ($this->findFilePaths($name, $characteristicNeedlesList?->toArray()) as $filePath) {
			$this->unlink($filePath);
		}
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::clear()
	 */
	public function clear(): void {
		$dataDirFsPath = $this->getDataDirFsPath();
		foreach ($dataDirFsPath->getChildDirectories() as $nameDirPath) {
			$this->removeAll($nameDirPath->getName());
		}
	}

	public function garbageCollect(?\DateInterval $maxLifetime = null, ?\DateTimeInterface $now = null): void {
		throw new UnsupportedOperationException('FileCacheStore does not support garbage collection.');
	}
}
