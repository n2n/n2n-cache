<?php

namespace n2n\cache\impl\fs;

use n2n\util\io\fs\FsPath;
use n2n\cache\CacheStore;
use n2n\util\type\TypeUtils;
use n2n\cache\CacheStorePool;
use n2n\util\ex\ExUtils;

class FileCacheStorePool implements CacheStorePool{

	function __construct(private FsPath $dirFsPath, private string|int|null $dirPerm = null, private string|int|null $filePerm = null) {

	}

	public function lookupCacheStore(string $namespace): CacheStore {
		$dirFsPath = $this->dirFsPath->ext(TypeUtils::encodeNamespace($namespace));
		if (!$dirFsPath->isDir()) {
			ExUtils::try(fn () => $dirFsPath->mkdirs($this->dirPerm));
			if ($this->dirPerm !== null) {
				// chmod after mkdirs because of possible umask restrictions.
				$dirFsPath->chmod($this->dirPerm);
			}
		}

		return new FileCacheStore($dirFsPath, $this->dirPerm, $this->filePerm);
	}

	public function clear(): void {
		$this->dirFsPath->delete();
	}

}