<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\HaskellKeywords;

// Haskell scanner.
// We do not yet support TemplateHaskell because it looks INSANE.

/*
 * TODO: Some contextual awareness would be great, Kate seems to highlight
 * things differently depending on whether they're in [..] or (...) blocks,
 * but I don't understand Haskell enough to embark on that right now.
 *
 * It would also be nice to distinguish between some different classes of
 * operator.
 */

class HaskellScanner extends SimpleScanner
{
    // handles comment nesting of multiline comments.
    public function commentOverride()
    {
        $this->nestableToken('COMMENT', '/\\{-/', '/-\\}/');
    }

    public function init()
    {
        $this->addIdentifierMapping('KEYWORD', HaskellKeywords::KEYWORDS);
        $this->addIdentifierMapping('TYPE', HaskellKeywords::TYPES);
        $this->addIdentifierMapping('FUNCTION', HaskellKeywords::FUNCTIONS);
        $this->addIdentifierMapping('VALUE', HaskellKeywords::VALUES);

        // shebang
        $this->addPattern('COMMENT', '/^#!.*/');

        // Refer to the sections in
        // http://www.haskell.org/onlinereport/lexemes.html
        // for the rules implemented here.

        // 2.4
        $this->addPattern('TYPE', '/[A-Z][\'\w]*/');
        $this->addPattern('IDENT', '/[_a-z][\'\w]*/');

        // http://www.haskell.org/onlinereport/prelude-index.html
        $this->addPattern('FUNCTION', '/
            (?: !!|\\$!?|&&|\\|{1,2}|\\*{1,2}|\\+{1,2}|-(?!-)|\\.|\\/=?|<=?|==|=<<|>>?=?|\\^\\^? )
        /x');

        $opChars = '\\+%^\\/\\*\\?#<>:;=@\\[\\]\\|\\\\~\\-!$@%&\\|=';

        // ` is used to make a function call into an infix operator
        // CRAZY OR WHAT.
        $this->addPattern('OPERATOR', '/`[^`]*`/');
        // some kind of function, lambda, maybe.
        $this->addPattern('FUNCTION', "/\\\\(?![$opChars])\S+/");

        // Comments are hard!
        // http://www.mail-archive.com/haskell@haskell.org/msg09019.html
        // According to this, we can PROBABLY, get away with checking either side
        // for non-operator chars followed by at least 2 dashes, but I could well
        // be wrong. It'll do for now.
        $this->addPattern('COMMENT', "/(?<![$opChars])---*(?![$opChars]).*/");
        // nested comments are easy!
        $this->addPattern('NESTED_COMMENT', '/\\{-/');
        $this->overrides['NESTED_COMMENT'] = array($this, 'commentOverride');
        $this->ruleTagMap['NESTED_COMMENT'] = 'COMMENT';
        $this->addPattern('OPERATOR', "/[$opChars]+/");

        // FIXME: the char type is way more discriminating than this
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR_SL);
        $this->addPattern('CHARACTER', TokenPresets::$SINGLE_STR_SL);

        // 2.5
        $this->addPattern('NUMERIC', '/
            0[oO]\d+  #octal
            |
            0[xX][a-fA-F\d]+  #hex
            |
            # decimal and float can be done at once, according to the grammar
            \d+ (?: (?:\.\d+)? (?: [eE][+-]? \d+))?
        /x');
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        // comments
        if (preg_match('/\\{-.*\\-}/', $src)) {
            $p += 0.05;
        }
        // 'import qualified' seems pretty unique
        if (preg_match('/^import\s+qualified/m', $src)) {
            $p += 0.05;
        }
        // "data SomeType something ="
        if (preg_match('/data\s+\w+\s+\w+\s*=/', $src)) {
            $p += 0.05;
        }
        return $p;
    }
}
