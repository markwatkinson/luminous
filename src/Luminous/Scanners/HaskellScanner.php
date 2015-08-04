<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

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
        $this->addIdentifierMapping('KEYWORD', array(
            'as',
            'case',
            'of',
            'class',
            'data',
            'family',
            'instance',
            'default',
            'deriving',
            'do',
            'forall',
            'foreign',
            'hiding',
            'if',
            'then',
            'else',
            'import',
            'infix',
            'infixl',
            'infixr',
            'let',
            'in',
            'mdo',
            'module',
            'newtype',
            'proc',
            'qualified',
            'rec',
            'type',
            'where'
        ));
        $this->addIdentifierMapping('TYPE', array(
          'Bool',
          'Char',
          'Double',
          'Either',
          'FilePath',
          'Float',
          'Int',
          'Integer',
          'IO',
          'IOError',
          'Maybe',
          'Ordering',
          'ReadS',
          'ShowS',
          'String',

          'Bounded',
          'Enum',
          'Eq',
          'Floating',
          'Fractional',
          'Functor',
          'Integral',
          'Monad',
          'Num',
          'Ord',
          'Read',
          'Real',
          'RealFloat',
          'RealFrac',
          'Show'
        ));
        $this->addIdentifierMapping('FUNCTION', array(
            'abs',
            'acos',
            'acosh',
            'all',
            'and',
            'any',
            'appendFile',
            'applyM',
            'asTypeOf',
            'asin',
            'asinh',
            'atan',
            'atan2',
            'atanh',
            'break',
            'catch',
            'ceiling',
            'compare',
            'concat',
            'concatMap',
            'const',
            'cos',
            'cosh',
            'curry',
            'cycle',
            'decodeFloat',
            'div',
            'divMod',
            'drop',
            'dropWhile',
            'elem',
            'encodeFloat',
            'enumFrom',
            'enumFromThen',
            'enumFromThenTo',
            'enumFromTo',
            'error',
            'even',
            'exp',
            'exponent',
            'fail',
            'filter',
            'flip',
            'floatDigits',
            'floatRadix',
            'floatRange',
            'floor',
            'fmap',
            'foldl',
            'foldl1',
            'foldr',
            'foldr1',
            'fromEnum',
            'fromInteger',
            'fromIntegral',
            'fromRational',
            'fst',
            'gcd',
            'getChar',
            'getContents',
            'getLine',
            'head',
            'id',
            'init',
            'interact',
            'ioError',
            'isDenormalized',
            'isIEEE',
            'isInfinite',
            'isNaN',
            'isNegativeZero',
            'iterate',
            'last',
            'lcm',
            'length',
            'lex',
            'lines',
            'log',
            'logBase',
            'lookup',
            'map',
            'mapM',
            'mapM_',
            'max',
            'maxBound',
            'maximum',
            'maybe',
            'min',
            'minBound',
            'minimum',
            'mod',
            'negate',
            'not',
            'notElem',
            'null',
            'odd',
            'or',
            'otherwise',
            'pi',
            'pred',
            'print',
            'product',
            'properFraction',
            'putChar',
            'putStr',
            'putStrLn',
            'quot',
            'quotRem',
            'read',
            'readFile',
            'readIO',
            'readList',
            'readLn',
            'readParen',
            'reads',
            'readsPrec',
            'realToFrac',
            'recip',
            'rem',
            'repeat',
            'replicate',
            'return',
            'reverse',
            'round',
            'scaleFloat',
            'scanl',
            'scanl1',
            'scanr',
            'scanr1',
            'seq',
            'sequence',
            'sequence_',
            'show',
            'showChar',
            'showList',
            'showParen',
            'showString',
            'shows',
            'showsPrec',
            'significand',
            'signum',
            'sin',
            'sinh',
            'snd',
            'span',
            'splitAt',
            'sqrt',
            'subtract',
            'succ',
            'sum',
            'tail',
            'take',
            'takeWhile',
            'tan',
            'tanh',
            'toEnum',
            'toInteger',
            'toRational',
            'truncate',
            'uncurry',
            'undefined',
            'unlines',
            'until',
            'unwords',
            'unzip',
            'unzip3',
            'userError',
            'words',
            'writeFile',
            'zip',
            'zip3',
            'zipWith',
            'zipWith3'
        ));
        $this->addIdentifierMapping('VALUE', array(
          'EQ',
          'False',
          'GT',
          'Just',
          'Left',
          'LT',
          'Nothing',
          'Right',
          'True',
        ));

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
