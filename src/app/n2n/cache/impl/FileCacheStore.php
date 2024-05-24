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
namespace n2n\cache\impl;

use n2n\util\cache\CacheStore;
use n2n\util\io\fs\FsPath;
use n2n\util\io\IoUtils;
use n2n\util\ex\IllegalStateException;
use n2n\util\HashUtils;
use n2n\util\cache\CacheItem;
use n2n\util\StringUtils;
use n2n\util\UnserializationFailedException;
use n2n\util\cache\CorruptedCacheStoreException;
use n2n\util\io\IoException;
use n2n\util\DateUtils;
use n2n\util\io\stream\impl\FileResourceStream;
use n2n\util\io\fs\FileOperationException;
use DateTime;

class FileCacheStore implements CacheStore {
	const CHARACTERISTIC_DELIMITER = '.';
	const CHARACTERISTIC_HASH_LENGTH = 4;
	const CACHE_FILE_SUFFIX = '.cache';
	const LOCK_FILE_SUFFIX = '.lock';

	private FsPath $dirPath;
	private $dirPerm;
	private $filePerm;
	/**
	 * @param mixed $dirPath
	 * @param string $dirPerm
	 * @param string $filePerm
	 */
	public function __construct($dirPath, $dirPerm = null, $filePerm = null) {
		$this->dirPath = new FsPath($dirPath);
		$this->dirPerm = $dirPerm;
		$this->filePerm = $filePerm;
	}
	/**
	 * @return FsPath
	 */
	public function getDirPath() {
		return $this->dirPath;
	}
	/**
	 * @param string $dirPerm
	 */
	public function setDirPerm($dirPerm) {
		$this->dirPerm = $dirPerm;
	}
	/**
	 * @return string
	 */
	public function getDirPerm() {
		return $this->dirPerm;
	}
	/**
	 * @param string $filePerm
	 */
	public function setFilePerm($filePerm) {
		$this->filePerm = $filePerm;
	}
	/**
	 * @return string
	 */
	public function getFilePerm() {
		return $this->filePerm;
	}
	/**
	 * @param string $filePath
	 * @return CacheFileLock|null
	 */
	private function buildReadLock(FsPath $filePath) {
		$lockFilePath = new FsPath($filePath . self::LOCK_FILE_SUFFIX);
		if (!$lockFilePath->exists()) {
			return null;
		}

		return new CacheFileLock(new FileResourceStream($lockFilePath, 'w', LOCK_SH));
	}
	/**
	 * @param string $filePath
	 * @return CacheFileLock
	 */
	private function createWriteLock(string $filePath) {
		IllegalStateException::assertTrue($this->filePerm !== null,
				'Can not create write lock if no file permission is defined for FileCacheStore.');

		$lockFilePath = new FsPath($filePath . self::LOCK_FILE_SUFFIX);
		$lock = new CacheFileLock(new FileResourceStream($lockFilePath, 'w', LOCK_EX));
		$lockFilePath->chmod($this->filePerm);
		return $lock;
	}

	private function buildNameDirPath($name) {
		if (IoUtils::hasSpecialChars($name)) {
			$name = HashUtils::base36Md5Hash($name);
		}

		return $this->dirPath->ext($name);
	}

	private function buildFileName(array $characteristics) {
		ksort($characteristics);

		$fileName = HashUtils::base36Md5Hash(serialize($characteristics));
		foreach ($characteristics as $key => $value) {
			$fileName .= self::CHARACTERISTIC_DELIMITER . HashUtils::base36Md5Hash(
							serialize(array($key, $value)), self::CHARACTERISTIC_HASH_LENGTH);
		}

		return $fileName . self::CACHE_FILE_SUFFIX;
	}

	private function buildFileGlobPattern(array $characteristicNeedles): string {
		ksort($characteristicNeedles);

		$fileName = '';
		foreach ($characteristicNeedles as $key => $value) {
			$fileName .= '*' . self::CHARACTERISTIC_DELIMITER . HashUtils::base36Md5Hash(
							serialize(array($key, $value)), self::CHARACTERISTIC_HASH_LENGTH);
		}

		return $fileName . '*' . self::CACHE_FILE_SUFFIX;
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::store()
	 */
	public function store(string $name, array $characteristics, mixed $data, DateTime $lastMod = null) {
		$nameDirPath = $this->buildNameDirPath($name);
		if (!$nameDirPath->isDir()) {
			if ($this->dirPerm === null) {
				throw new IllegalStateException('No directory permission set for FileCacheStore.');
			}

			$parentDirPath = $nameDirPath->getParent();
			if (!$parentDirPath->isDir()) {
				$parentDirPath->mkdirs($this->dirPerm);
				// chmod after mkdirs because of possible umask restrictions.
				$parentDirPath->chmod($this->dirPerm);
			}

			$nameDirPath->mkdirs($this->dirPerm);
			// chmod after mkdirs because of possible umask restrictions.
			$nameDirPath->chmod($this->dirPerm);
		}

		if ($this->filePerm === null) {
			throw new IllegalStateException('No file permission set for FileCacheStore.');
		}

		if ($lastMod === null) {
			$lastMod = new DateTime();
		}

		$filePath = $nameDirPath->ext($this->buildFileName($characteristics));

		$lock = $this->createWriteLock((string) $filePath);
		IoUtils::putContentsSafe($filePath->__toString(), serialize(array('characteristics' => $characteristics,
				'data' => $data, 'lastMod' => $lastMod->getTimestamp())));


		$filePath->chmod($this->filePerm);
		$lock->release();
	}
	/**
	 * @param $name
	 * @param FsPath $filePath
	 * @return CacheItem null, if filePath no longer available.
	 * @throws CorruptedCacheStoreException
	 */
	private function read($name, FsPath $filePath) {
		if (!$filePath->exists()) return null;

		$lock = $this->buildReadLock($filePath);
		if ($lock === null) {
			$filePath->delete();
			return null;
		}

		if (!$filePath->exists()) {
			$lock->release(true);
			return null;
		}

		$contents = null;
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

		if (!isset($attrs['characteristics']) || !is_array($attrs['characteristics']) || !isset($attrs['data'])
				|| !isset($attrs['lastMod']) || !is_numeric($attrs['lastMod'])) {
			throw new CorruptedCacheStoreException('Corrupted cache file: ' . $filePath);
		}


		$ci = new CacheItem($name, $attrs['characteristics'], null,
				DateUtils::createDateTimeFromTimestamp($attrs['lastMod']));
		$ci->data = &$attrs['data'];
		return $ci;
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::get()
	 */
	public function get(string $name, array $characteristics): ?CacheItem {
		$nameDirPath = $this->buildNameDirPath($name);
		if (!$nameDirPath->exists()) return null;
		return $this->read($name, $nameDirPath->ext($this->buildFileName($characteristics)));
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::remove()
	 */
	public function remove(string $name, array $characteristics): void {
		$nameDirPath = $this->buildNameDirPath($name);
		if (!$nameDirPath->exists()) return;

		$filePath = $nameDirPath->ext($this->buildFileName($characteristics));
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
				throw $e;
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
	 * @param array $characteristicNeedles
	 * @param array $characteristics
	 * @return boolean
	 */
	private function inCharacteristics(array $characteristicNeedles, array $characteristics): bool {
		foreach ($characteristicNeedles as $key => $value) {
			if (!array_key_exists($key, $characteristics)
					|| $value !== $characteristics[$key]) return false;
		}

		return true;
	}

	/**
	 * @param string|null $name
	 * @param array|null $characteristicNeedles
	 * @return FsPath[]
	 */
	private function findFilePaths(?string $name, array $characteristicNeedles = null): array {
		$fileGlobPattern = $this->buildFileGlobPattern((array) $characteristicNeedles);

		if ($name === null) {
			return $this->dirPath->getChildren('*' . DIRECTORY_SEPARATOR . $fileGlobPattern);
		}

		$nameDirPath = $this->buildNameDirPath($name);
		if (!$nameDirPath->exists()) {
			return [];
		}

		return $nameDirPath->getChildren($fileGlobPattern);

	}

	public function findAll(string $name, array $characteristicNeedles = null): array {
		$cacheItems = array();

		foreach ($this->findFilePaths($name, $characteristicNeedles) as $filePath) {
			$cacheItem = $this->read($name, $filePath);
			if ($cacheItem === null) continue;

			if ($characteristicNeedles === null
					// hash collision detection
					|| $this->inCharacteristics($characteristicNeedles, $cacheItem->getCharacteristics())) {
				$cacheItems[] = $cacheItem;
			}
		}

		return $cacheItems;
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::removeAll()
	 */
	public function removeAll(?string $name, array $characteristicNeedles = null): void {
		foreach ($this->findFilePaths($name, $characteristicNeedles) as $filePath) {
			$this->unlink($filePath);
		}
	}
	/* (non-PHPdoc)
	 * @see \n2n\cache\CacheStore::clear()
	 */
	public function clear(): void {
		foreach ($this->dirPath->getChildDirectories() as $nameDirPath) {
			$this->removeAll($nameDirPath->getName());
		}
	}
}
