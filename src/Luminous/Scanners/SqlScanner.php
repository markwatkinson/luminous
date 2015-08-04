<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

class SqlScanner extends SimpleScanner
{
    public function init()
    {
        $this->caseSensitive = false;
        // $this->removeStreamFilter('oo-syntax');
        $this->removeFilter('comment-to-doc');
        $this->removeFilter('constant');
        // TODO: These are MySQL specific
        $this->addIdentifierMapping('KEYWORD', array(
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
        ));
        $this->addIdentifierMapping('TYPE', array(
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
        ));
        $this->addIdentifierMapping('VALUE', array('NULL'));
        // http://dev.mysql.com/doc/refman/5.0/en/func-op-summary-ref.html
        $this->addIdentifierMapping('OPERATOR', array(
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
        ));
        $this->addIdentifierMapping('FUNCTION', array(
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
        ));

        $this->addPattern('IDENT', '/[a-zA-Z_]+\w*/');
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        // # is for MySQL.
        $this->addPattern('COMMENT', '/(?:\#|--).*/');
        $this->addPattern('STRING', TokenPresets::$SQL_SINGLE_STR_BSLASH);
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('STRING', '/ ` (?> [^\\\\`]+ | \\\\. )* (?: `|$)/x');
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);

        $this->addPattern('OPERATOR', '/[¬!%^&*\\-=+~:<>\\|\\/]+/');

        $this->addPattern('KEYWORD', '/\\?/');
    }

    public static function guessLanguage($src, $info)
    {
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
        if (strpos($info['trimmed'], '--') === 0 && isset($info['lines'][0])) {
            if ((stripos($info['lines'][0], 'sql') !== false) || stripos($info['lines'][0], 'dump' !== false)) {
                $p = 0.5;
            }
        }

        foreach (array('SELECT', 'CREATE TABLE', 'INSERT INTO', 'DROP TABLE', 'INNER JOIN', 'OUTER JOIN') as $str) {
            if (strpos($src, $str) !== false) {
                $p += 0.01;
            }
        }
        // single line comments --
        if (preg_match_all('/^--/m', $src, $m) > 5) {
            $p += 0.05;
        }
        if (preg_match('/VARCHAR\(\d+\)/', $src)) {
            $p += 0.05;
        }
        return $p;
    }
}
