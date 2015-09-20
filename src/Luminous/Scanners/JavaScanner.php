<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\JavaKeywords;

class JavaScanner extends SimpleScanner
{
    public function init()
    {
        $this->addIdentifierMapping('KEYWORD', JavaKeywords::KEYWORDS);
        $this->addIdentifierMapping('TYPE', JavaKeywords::TYPES);

        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('CHARACTER', TokenPresets::$SINGLE_STR);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('IDENT', '/[a-zA-Z$_][$\w]*/');
        $this->addPattern('OPERATOR', '/[!%^&*\-=+:?|<>]+/');
        // this is called an annotation
        // http://download.oracle.com/javase/1,5.0/docs/guide/language/annotations.html
        $this->addPattern('FUNCTION', '/@[\w]+/');
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0;
        if (preg_match('/^import\s+java\./m', $src)) {
            return 1.0;
        }
        if (preg_match('/System\.out\.print/', $src)) {
            $p += 0.2;
        }
        if (preg_match('/public\s+static\s+void\s+main\\b/', $src)) {
            $p += 0.2;
        }
        return $p;
    }
}
