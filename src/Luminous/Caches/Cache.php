<?php
/// @cond ALL

namespace Luminous\Caches;

/**
 * Cache superclass provides a skeleton for implementations using the filesystem
 * or SQL, or anything else.
 */
abstract class Cache
{
    protected $gz = true;
    protected $id = null;
    protected $timeout = 0;
    protected $cacheHit = false;
    private $useCache = true;
    private $creationCheck = false;

    private $errors = array();

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function setPurgeTime($seconds)
    {
        $this->timeout = $seconds;
    }

    private function compress($data)
    {
        return $this->gz ? gzcompress($data) : $data;
    }

    private function decompress($data)
    {
        return $this->gz ? gzuncompress($data) : $data;
    }

    protected function logError($msg)
    {
        $this->errors[] = $msg;
    }

    public function hasErrors()
    {
        return !empty($this->errrors);
    }

    public function errors()
    {
        return $this->errors;
    }

    abstract protected function createInternal();
    abstract protected function readInternal();
    abstract protected function writeInternal($data);
    abstract protected function update();

    abstract protected function purgeInternal();

    private function purge()
    {
        assert($this->creationCheck);
        if ($this->useCache) {
            $this->purgeInternal();
        }
    }

    private function create()
    {
        if ($this->creationCheck) {
            return;
        }
        $this->creationCheck = true;
        if (!$this->createInternal()) {
            $this->useCache = false;
        } else {
            $this->purge();
        }
    }

    /**
     * @brief Reads from the cache
     * @returns the cached string or @c null
     */
    public function read()
    {
        $this->create();
        if (!$this->useCache) {
            return null;
        }

        $contents = $this->readInternal();
        if ($contents !== false) {
            $this->cacheHit = true;
            $contents = $this->decompress($contents);
            $this->update();
            return $contents;
        }
        return null;
    }

    /**
     * @brief Writes into the cache
     * @param $data the data to write
     */
    public function write($data)
    {
        $this->create();
        $this->purge();
        if (!$this->cacheHit && $this->useCache) {
            $this->writeInternal($this->compress($data));
        }
    }
}

/// @endcond
