<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\CSharpKeywords;
use Luminous\Scanners\Keywords\VisualBasicKeywords;

/*
 * VB.NET
 *
 * Language spec:
 * http://msdn.microsoft.com/en-us/library/aa712050(v=vs.71).aspx
 *
 * TODO: IIRC vb can be embedded in asp pages like php or ruby on rails,
 * and XML literals: these are a little bit confusing, something
 * like "<xyz>.something" appears to be a valid XML fragment (i.e. the <xyz>
 * is a complete fragment), but at other times, the fragment would run until
 * the root tag is popped. Need to find a proper description of the grammar
 * to figure it out
 */
class VisualBasicScanner extends SimpleScanner
{
    public $caseSensitive = false;

    public function init()
    {
        $this->addPattern('PREPROCESSOR', "/^[\t ]*#.*/m");
        $this->addPattern('COMMENT', "/'.*/");

        $this->addPattern('COMMENT', '/\\bREM\\b.*/i');
        // float
        $this->addPattern(
            'NUMERIC',
            '/ (?<!\d)
                \d+\.\d+ (?: e[+\\-]?\d+)?
                |\.\d+ (?: e[+\\-]?\d+)?
                | \d+ e[+\\-]?\d+
            /xi'
        );
        // int
        $this->addPattern(
            'NUMERIC',
            '/ (?:
                &H[0-9a-f]+
                | &O[0-7]+
                | (?<!\d)\d+
            ) [SIL]*/ix'
        );

        $this->addPattern('CHARACTER', '/"(?:""|.)"c/i');

        $this->addPattern('STRING', '/" (?> [^"]+ | "" )* ($|")/x');
        // in theory we should also match unicode quote chars
        // in reality, well, I read the php docs and I have no idea if it's
        // even possible.
        // The chars are:
        // http://www.fileformat.info/info/unicode/char/201c/index.htm
        // and
        // http://www.fileformat.info/info/unicode/char/201d/index.htm

        // date literals, this isn't as discriminating as the grammar specifies.
        $this->addPattern('VALUE', "/#[ \t][^#\n]*[ \t]#/");

        $this->addPattern('OPERATOR', '/[&*+\\-\\/\\\\^<=>,\\.]+/');

        // http://msdn.microsoft.com/en-us/library/aa711645(v=VS.71).aspx
        // XXX: it warns about ! being ambiguous but I don't see how it can be
        // ambiguous if we use this regex?
        $this->addPattern('IDENT', '/[a-z_]\w*[%&@!#$]?/i');

        // we'll borrow C#'s list of types (ie modules, classes, etc)
        $this->addIdentifierMapping('VALUE', VisualBasicKeywords::$VALUES);
        $this->addIdentifierMapping('OPERATOR', VisualBasicKeywords::$OPERATORS);
        $this->addIdentifierMapping('TYPE', VisualBasicKeywords::$TYPES);
        $this->addIdentifierMapping('KEYWORD', VisualBasicKeywords::$KEYWORDS);
        $this->addIdentifierMapping('TYPE', CSharpKeywords::$TYPES);
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        if (preg_match('/^Imports\s+System/i', $src)) {
            $p += 0.1;
        }
        if (preg_match('/Dim\s+\w+\s+As\s+/i', $src)) {
            $p += 0.2;
        }
        if (preg_match('/(Public|Private|Protected)\s+Sub\s+/i', $src)) {
            $p += 0.1;
        }
        return $p;
    }
}
