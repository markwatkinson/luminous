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
}