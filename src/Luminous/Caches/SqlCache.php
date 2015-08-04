<?php
/** @cond ALL */

namespace Luminous\Caches;

use Luminous\Exceptions\SqlSafetyException;

/*
 * A note regarding escaping:
 * Escaping is hard because we don't want to rely on an RBDMS specific escaping
 * function.
 * Therefore:
 * All the data and queries are specifically designed such that escaping is
 * unnecessary. String types are either b64 or b16, which means no inconvenient
 * characters, and integer types are, well, integers.
 */
class SqlCache extends Cache
{
    public static $tableName = 'luminous_cache';
    public static $queries = array(
        // FIXME: INSERT IGNORE is MySQL specific.
        // we do need an ignore on duplicate because there's a race condition
        // between reading from the cache and then writing into it if the
        // read failed
        'insert' => 'INSERT IGNORE INTO `%s` (cache_id, output, insert_date, hit_date) VALUES("%s", "%s", %d, %d);',
        'update' => 'UPDATE `%s` SET hit_date=%d WHERE cache_id="%s";',
        'select' => 'SELECT output FROM `%s` WHERE cache_id="%s";',
        'purge' => 'DELETE FROM `%s` WHERE hit_date <= %d AND cache_id != "last_purge";',
        'get_purge_time' => 'SELECT hit_date FROM `%s` WHERE cache_id="last_purge" LIMIT 1;',
        'set_purge_time' => 'UPDATE `%s` SET hit_date = %d WHERE cache_id="last_purge";',
        'set_purge_time_initial' => 'INSERT IGNORE INTO `%s` (cache_id, hit_date) VALUES ("last_purge", %d);'
    );

    private $sqlFunction = null;

    public function setSqlFunction($func)
    {
        $this->sqlFunction = $func;
    }

    private static function safetyCheck($string)
    {
        // we should only be handling very restricted data in queries.
        // http://en.wikipedia.org/wiki/Base64#Variants_summary_table
        if (is_int($string) || (is_string($string) && preg_match('@^[a-zA-Z0-9\-\+=_/\.:!]*$@i', $string))) {
            return $string;
        } else {
            throw new SqlSafetyException();
        }
    }

    private function query($sql)
    {
        return call_user_func($this->sqlFunction, $sql);
    }

    protected function createInternal()
    {
        try {
            if (!is_callable($this->sqlFunction)) {
                throw new Exception('Luminous\\Core\\Caches\\SqlCache does not have a callable SQL function');
            }
            $r = $this->query(file_get_contents(__DIR__ . '/sql/cache.mysql'));
            if ($r === false) {
                throw new Exception('Creation of cache table failed (query returned false)');
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
        return true;
    }

    protected function update()
    {
        $this->query(sprintf(
            self::$queries['update'],
            self::safetyCheck(self::$tableName),
            time(),
            self::safetyCheck($this->id)
        ));
    }

    protected function readInternal()
    {
        $ret = false;
        try {
            $ret = $this->query(sprintf(
                self::$queries['select'],
                self::safetyCheck(self::$tableName),
                self::safetyCheck($this->id)
            ));
            if (!empty($ret) && isset($ret[0]) && isset($ret[0]['output'])) {
                return base64_decode($ret[0]['output']);
            }
        } catch (SqlSafetyException $e) {
        }
        return false;
    }

    protected function writeInternal($data)
    {
        $data = base64_encode($data);
        $time = time();
        // try {
            $this->query(sprintf(
                self::$queries['insert'],
                self::safetyCheck(self::$tableName),
                self::safetyCheck($this->id),
                self::safetyCheck($data),
                self::safetyCheck($time),
                self::safetyCheck($time)
            ));
        // } catch (SqlSafetyException $e) {
        // }
    }

    protected function purgeInternal()
    {
        if ($this->timeout <= 0) {
            return;
        }
        $purgeTime_ = $this->query(sprintf(
            self::$queries['get_purge_time'],
            self::safetyCheck(self::$tableName),
            self::safetyCheck(time())
        ));
        $purgeTime = 0;
        if ($purgeTime_ !== false && !empty($purgeTime_) && isset($purgeTime_[0]['hit_date'])) {
            $purgeTime = $purgeTime_[0]['hit_date'];
        } else {
            // we need to insert the record
            $this->query(sprintf(
                self::$queries['set_purge_time_initial'],
                self::safetyCheck(self::$tableName),
                self::safetyCheck(time())
            ));
        }
        if ($purgeTime < time() - 60 * 60 * 24) {
            // XXX: does this need to be in a try block?
            try {
                $this->query(sprintf(
                    self::$queries['purge'],
                    self::safetyCheck(self::$tableName),
                    self::safetyCheck(time() - $this->timeout)
                ));
            } catch (SqlSafetyException $e) {
            }
            $this->query(sprintf(
                self::$queries['set_purge_time'],
                self::safetyCheck(self::$tableName),
                self::safetyCheck(time())
            ));
        }
    }
}

/** @endcond */
