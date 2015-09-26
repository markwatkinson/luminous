<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\SqlKeywords;

class SqlScanner extends SimpleScanner
{
    public function init()
    {
        $this->caseSensitive = false;
        // $this->removeStreamFilter('oo-syntax');
        $this->removeFilter('comment-to-doc');
        $this->removeFilter('constant');
        $this->addIdentifierMapping('KEYWORD', SqlKeywords::$KEYWORDS);
        $this->addIdentifierMapping('TYPE', SqlKeywords::$TYPES);
        $this->addIdentifierMapping('VALUE', SqlKeywords::$VALUES);
        $this->addIdentifierMapping('OPERATOR', SqlKeywords::$OPERATORS);
        $this->addIdentifierMapping('FUNCTION', SqlKeywords::$FUNCTIONS);

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
