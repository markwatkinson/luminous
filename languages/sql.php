<?php

global $luminous_sql_keywords;
global $luminous_sql_types;

$luminous_sql_keywords = array(
  'ABORT', 'ACTION', 'ADD', 'AFTER', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS',
  'ASC', 'ATTACH', 'AUTOINCREMENT', 'AUTO_INCREMENT', 'BEFORE', 'BEGIN',
  'BETWEEN', 'BY', 'CASCADE', 'CASE', 'CAST', 'CHECK', 'COLLATE', 'COLUMN',
  'COMMIT', 'CONFLICT', 'CONSTRAINT', 'CREATE', 'CROSS', 'CURRENT_DATE',
  'CURRENT_TIME', 'CURRENT_TIMESTAMP','DATABASE', 'DEFAULT', 'DEFERRABLE',
  'DEFERRED', 'DELETE', 'DESC', 'DETACH', 'DISTINCT', 'DROP',  'EACH', 'ELSE',
  'END', 'ESCAPE', 'EXCEPT', 'EXCLUSIVE', 'EXISTS', 'EXPLAIN', 'FAIL', 'FOR',
  'FOREIGN', 'FROM', 'FULL', 'GLOB', 'GROUP', 'HAVING', 'IF', 'IGNORE',
  'IMMEDIATE', 'IN', 'INDEX', 'INDEXED', 'INITITIALLY', 'INNER', 'INSERT',
  'INSTEAD', 'INTERSECT', 'INTO', 'IS', 'ISNULL', 'JOIN', 'KEY', 'LEFT', 'LIKE',
  'LIMIT', 'MATCH', 'NATURAL', 'NO', 'NOT', 'NOTNULL', 'NULL', 'OF', 'OFFSET',
  'ON', 'OR', 'ORDER', 'OUTER', 'PLAN', 'PRAGMA', 'PRIMARY', 'QUERY', 'RAISE',
  'REFERENCES', 'REGEXP', 'REINDEX', 'RELEASE', 'RENAME', 'REPLACE', 'RESTRICT',
  'RIGHT', 'ROLLBACK', 'ROW', 'SAVEPOINT', 'SELECT', 'SET', 'TABLE', 'TEMP',
  'TEMPORARY', 'THEN', 'TO', 'TRANSACTION', 'TRIGGER', 'UNION', 'UNIQUE',
  'UPDATE', 'USING', 'VACUUM', 'VALUES', 'VIEW', 'VIRTUAL', 'WHEN', 'WHERE'
);

$luminous_sql_types = array(
  'BLOB', 'BIGINT', 'BIGINTEGER', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL',
  'DOUBLE', 'FLOAT', 'INT', 'INTEGER', 'MEDIUMINT', 'MEDIUMINTEGER',
  'SMALLINT', 'SMALLINTEGER', 'TIME', 'TIMESTAMP', 'TINYINT', 'TINYINTEGER',
  'VARCHAR'
);


class LuminousSQLScanner extends LuminousSimpleScanner {

  public function init() {
    $this->case_sensitive = false;
    $this->remove_stream_filter('oo-syntax');
    $this->remove_filter('comment-to-doc');
    $this->remove_filter('constant');
    $this->add_identifier_mapping('KEYWORD', $GLOBALS['luminous_sql_keywords']);
    $this->add_identifier_mapping('TYPE', $GLOBALS['luminous_sql_types']);

    $this->add_pattern('IDENT', '/[a-zA-Z_]+\w*/');
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    // # is for MySQL.
    $this->add_pattern('COMMENT', '/(?:\#|--).*/');
    $this->add_pattern('STRING', LuminousTokenPresets::$SQL_SINGLE_STR_BSLASH);
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    $this->add_pattern('STRING', '/ ` (?: [^\\\\`]+ | \\\\. )* (?: `|$)/x');
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
  }

  public static function guess_language($src, $info) {
    // we have to be careful not to assign too much weighting to 
    // generic SQL keywords, which will often appear in other languages
    // when those languages are executing SQL statements
    //
    // All in all, SQL is pretty hard to recognise because generally speaking,
    // an SQL dump will probably contain only a tiny fraction of SQL keywords
    // with the majority of the text just being data. 
    $p = 0.0;
    // if we're lucky, the top line will be a comment containing the phrase
    // 'SQL' or 'dump'
    if (strpos($info['trimmed'], '--') === 0 && isset($info['lines'][0])
      && (
        stripos($info['lines'][0], 'sql') !== false)
        || stripos($info['lines'][0], 'dump' !== false)
      )
      $p = 0.5;
    

    foreach(array('SELECT', 'CREATE TABLE', 'INSERT INTO', 'DROP TABLE',
      'INNER JOIN', 'OUTER JOIN') as $str) 
    {
      if (strpos($src, $str) !== false) $p += 0.01;
    }
    // single line comments --
    if (preg_match_all('/^--/m', $src, $m) > 5)
      $p += 0.05;
    if (preg_match('/VARCHAR\(\d+\)/', $src)) $p += 0.05;
    return $p;
  }
}
