<?php

namespace Luminous\Scanners\Keywords;

class SqlKeywords
{
    // TODO: These are MySQL specific

    const KEYWORDS = array(
        'ABORT',
        'ACTION',
        'ADD',
        'AFTER',
        'ALL',
        'ALTER',
        'ANALYZE',
        'AS',
        'ASC',
        'ATTACH',
        'AUTOINCREMENT',
        'AUTO_INCREMENT',
        'BEFORE',
        'BEGIN',
        'BY',
        'CASCADE',
        'CAST',
        'CHECK',
        'COLLATE',
        'COLUMN',
        'COMMIT',
        'CONFLICT',
        'CONSTRAINT',
        'CREATE',
        'CROSS',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'DATABASE',
        'DEFAULT',
        'DEFERRABLE',
        'DEFERRED',
        'DELETE',
        'DESC',
        'DETACH',
        'DISTINCT',
        'DROP',
        'EACH',
        'ELSE',
        'END',
        'ESCAPE',
        'EXCEPT',
        'EXCLUSIVE',
        'EXISTS',
        'EXPLAIN',
        'FAIL',
        'FOR',
        'FOREIGN',
        'FROM',
        'FULL',
        'GLOB',
        'GROUP',
        'HAVING',
        'IF',
        'IGNORE',
        'IMMEDIATE',
        'IN',
        'INDEX',
        'INDEXED',
        'INITITIALLY',
        'INNER',
        'INSERT',
        'INSTEAD',
        'INTERSECT',
        'INTO',
        'ISNULL',
        'JOIN',
        'KEY',
        'LEFT',
        'LIKE',
        'LIMIT',
        'MATCH',
        'NATURAL',
        'NO',
        'NOTNULL',
        'OF',
        'OFFSET',
        'ON',
        'OR',
        'ORDER',
        'OUTER',
        'PLAN',
        'PRAGMA',
        'PRIMARY',
        'QUERY',
        'RAISE',
        'REFERENCES',
        'REGEXP',
        'REINDEX',
        'RELEASE',
        'RENAME',
        'REPLACE',
        'RESTRICT',
        'RIGHT',
        'ROLLBACK',
        'ROW',
        'SAVEPOINT',
        'SELECT',
        'SET',
        'TABLE',
        'TEMP',
        'TEMPORARY',
        'THEN',
        'TO',
        'TRANSACTION',
        'TRIGGER',
        'UNION',
        'UNIQUE',
        'UPDATE',
        'USING',
        'VACUUM',
        'VALUES',
        'VIEW',
        'VIRTUAL',
        'WHEN',
        'WHERE',
        'WITH',

        // type qualifier stuff
        'SIGNED',
        'UNSIGNED',
        'ZEROFILL',

        // seem to be missing these, probably not standard
        'MINVALUE',
        'MAXVALUE',
        'START'
    );
    const TYPES = array(
        'BINARY',
        'BIT',
        'BIGINT',
        'BIGINTEGER',
        'BLOB',
        'CHAR',
        'CLOB',
        'DATE',
        'DATETIME',
        'DEC',
        'DECIMAL',
        'DOUBLE',
        'DOUBLE_PRECISION',
        'ENUM',
        'FIXED',
        'FLOAT',
        'INT',
        'INTEGER',
        'MEDIUMINT',
        'MEDIUMINTEGER',
        'NUMERIC',
        'REAL',
        'SMALLINT',
        'SMALLINTEGER',
        'SET',
        'TEXT',
        'TIME',
        'TIMESTAMP',
        'TINYINT',
        'TINYINTEGER',
        'VARBINARY',
        'VARCHAR',
        'YEAR',
        'ZONE' // for time zone
    );
    const VALUES = array('NULL');
    // http://dev.mysql.com/doc/refman/5.0/en/func-op-summary-ref.html
    const OPERATORS = array(
        'AND',
        'BETWEEN',
        'BINARY',
        'CASE',
        'DIV',
        'IS',
        'LIKE',
        'NOT',
        'SOUNDS',
        'XOR'
    );
    const FUNCTIONS = array(
        'ABS',
        'ACOS',
        'ADDDATE',
        'ADDTIME',
        'AES_DECRYPT',
        'AES_ENCRYPT',
        'ASCII',
        'ASIN',
        'ATAN2',
        'ATAN',
        'AVG',
        'BENCHMARK',
        'BIN',
        'BIT_AND',
        'BIT_COUNT',
        'BIT_LENGTH',
        'BIT_OR',
        'BIT_XOR',
        'CAST',
        'CEIL',
        'CEILING',
        'CHAR_LENGTH',
        'CHAR',
        'CHARACTER_LENGTH',
        'CHARSET',
        'COALESCE',
        'COERCIBILITY',
        'COLLATION',
        'COMPRESS',
        'CONCAT_WS',
        'CONCAT',
        'CONNECTION_ID',
        'CONV',
        'CONVERT_TZ',
        'Convert',
        'COS',
        'COT',
        'COUNT',
        'COUNT',
        'CRC32',
        'CURDATE',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'CURRENT_USER',
        'CURTIME',
        'DATABASE',
        'DATE_ADD',
        'DATE_FORMAT',
        'DATE_SUB',
        'DATE',
        'DATEDIFF',
        'DAY',
        'DAYNAME',
        'DAYOFMONTH',
        'DAYOFWEEK',
        'DAYOFYEAR',
        'DECODE',
        'DEFAULT',
        'DEGREES',
        'DES_DECRYPT',
        'DES_ENCRYPT',
        'ELT',
        'ENCODE',
        'ENCRYPT',
        'EXP',
        'EXPORT_SET',
        'EXTRACT',
        'FIELD',
        'FIND_IN_SET',
        'FLOOR',
        'FORMAT',
        'FOUND_ROWS',
        'FROM_DAYS',
        'FROM_UNIXTIME',
        'GET_FORMAT',
        'GET_LOCK',
        'GREATEST',
        'GROUP_CONCAT',
        'HEX',
        'HOUR',
        'IF',
        'IFNULL',
        'IN',
        'INET_ATON',
        'INET_NTOA',
        'INSERT',
        'INSTR',
        'INTERVAL',
        'IS_FREE_LOCK',
        'IS_USED_LOCK',
        'ISNULL',
        'LAST_DAY',
        'LAST_INSERT_ID',
        'LCASE',
        'LEAST',
        'LEFT',
        'LENGTH',
        'LN',
        'LOAD_FILE',
        'LOCALTIME',
        'LOCALTIMESTAMP,',
        'LOCATE',
        'LOG10',
        'LOG2',
        'LOG',
        'LOWER',
        'LPAD',
        'LTRIM',
        'MAKE_SET',
        'MAKEDATE',
        'MAKETIME',
        'MASTER_POS_WAIT',
        'MATCH',
        'MAX',
        'MD5',
        'MICROSECOND',
        'MID',
        'MIN',
        'MINUTE',
        'MOD',
        'MONTH',
        'MONTHNAME',
        'NAME_CONST',
        'NOW',
        'NULLIF',
        'OCT',
        'OCTET_LENGTH',
        'OLD_PASSWORD',
        'ORD',
        'PASSWORD',
        'PERIOD_ADD',
        'PERIOD_DIFF',
        'PI',
        'POSITION',
        'POW',
        'POWER',
        'ANALYSE',
        'QUARTER',
        'QUOTE',
        'RADIANS',
        'RAND',
        'REGEXP',
        'RELEASE_LOCK',
        'REPEAT',
        'REPLACE',
        'REVERSE',
        'RIGHT',
        'RLIKE',
        'ROUND',
        'ROW_COUNT',
        'RPAD',
        'RTRIM',
        'SCHEMA',
        'SEC_TO_TIME',
        'SECOND',
        'SESSION_USER',
        'SHA1',
        'SIGN',
        'SIN',
        'SLEEP',
        'SOUNDEX',
        'SPACE',
        'SQRT',
        'STD',
        'STDDEV_POP',
        'STDDEV_SAMP',
        'STDDEV',
        'STR_TO_DATE',
        'STRCMP',
        'SUBDATE',
        'SUBSTR',
        'SUBSTRING_INDEX',
        'SUBSTRING',
        'SUBTIME',
        'SUM',
        'SYSDATE',
        'SYSTEM_USER',
        'TAN',
        'TIME_FORMAT',
        'TIME_TO_SEC',
        'TIME',
        'TIMEDIFF',
        'TIMESTAMP',
        'TIMESTAMPADD',
        'TIMESTAMPDIFF',
        'TO_DAYS',
        'TRIM',
        'TRUNCATE',
        'UCASE',
        'UNCOMPRESS',
        'UNCOMPRESSED_LENGTH',
        'UNHEX',
        'UNIX_TIMESTAMP',
        'UPPER',
        'USER',
        'UTC_DATE',
        'UTC_TIME',
        'UTC_TIMESTAMP',
        'UUID',
        'VALUES',
        'VAR_POP',
        'VAR_SAMP',
        'VARIANCE',
        'VERSION',
        'WEEK',
        'WEEKDAY',
        'WEEKOFYEAR',
        'YEAR',
        'YEARWEEK'
    );
}
