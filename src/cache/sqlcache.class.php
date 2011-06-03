<?php
/*
 * A note regarding escaping:
 * Escaping is hard because we don't want to rely on an RBDMS specific escaping
 * function.
 * Therefore:
 * All the data and queries are specifically designed such that escaping is
 * unnecessary. String types are either b64 or b16, which means no inconvenient
 * characters, and integer types are, well, integers.
 */
class LuminousSQLCache extends LuminousCache {
  static $table_name = 'luminous_cache';
  static $queries = array(
    
    // FIXME: INSERT IGNORE is MySQL specific.
    // we do need an ignore on duplicate because there's a race condition
    // between reading from the cache and then writing into it if the
    // read failed
    'insert' => 'INSERT IGNORE INTO `%s` (cache_id, output, insert_date, hit_date)
      VALUES("%s", "%s", %d, %d);',
    'update' => 'UPDATE `%s` SET hit_date=%d WHERE cache_id="%s";',
    'select' => 'SELECT output FROM `%s` WHERE cache_id="%s";',
    'purge' => 'DELETE FROM `%s` WHERE hit_date <= %d;',
  );

  private $sql_function = null;

  public function set_sql_function($func) {
    $this->sql_function = $func;
  }

  private function _query($sql) {
    if (!is_callable($this->sql_function)) {
      throw new Exception('LuminousSQLCache does not have a callable SQL function');
    }
    else return call_user_func($this->sql_function, $sql);
  }

  protected function _create(&$s) {
    try {
      $r = $this->_query(file_get_contents(dirname(__FILE__) . '/sql/cache.mysql'));
      if ($r === false)
        throw new Exception('Creation of cache table failed (query returned false)');
    } catch(Exception $e) {
      $s = $e->getMessage();
      return false;
    }
    return true;
  }

  protected function _update() {
    $this->_query(
      sprintf(self::$queries['update'], self::$table_name, time(), $this->id)
    );
  }

  protected function _read() {
    $ret = $this->_query(sprintf(self::$queries['select'],
      self::$table_name, $this->id));
    if (!empty($ret) && isset($ret[0]) && isset($ret[0]['output'])) {
      return base64_decode($ret[0]['output']);
    }
    return false;
  }

  protected function _write($data) {
    $data = base64_encode($data);
    $time = time();
    $this->_query(sprintf(self::$queries['insert'], self::$table_name,
      $this->id, $data, $time, $time));
  }

  protected function _purge() {
    if ($this->timeout <= 0) return;
    $this->_query(
      sprintf(self::$queries['purge'],
              self::$table_name, time() - $this->timeout));

  }
}
