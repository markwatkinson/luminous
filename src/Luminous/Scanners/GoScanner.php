<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

/*
 * Go.
 *
 * http://golang.org/doc/go_spec.html
 *
 * TODO: the different string formats have different escape codes, need
 * to override the generic filter to handle this
 * also, if there's a standard library API list, that would be useful.
 *
 */

class GoScanner extends SimpleScanner
{
    public function typeOverride($matches)
    {
        $this->record($matches[1], 'IDENT');
        $this->record($matches[2], null);
        $this->record($matches[3], 'USER_FUNCTION');
        $this->posShift(strlen($matches[0]));
        $this->userDefs[$matches[3]] = ($matches[1] === 'type') ? 'TYPE' : 'FUNCTION';
    }

    public function init()
    {
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);

        $ident = '[\p{L}_][\p{L}\p{N}_]*';
        // this should be unicode for letter (\p{L}) and number (\p{N})
        $this->addPattern('type', "/\\b(type|func)(\s+)($ident)/u");
        $this->overrides['type'] = array($this, 'typeOverride');

        $this->addPattern('IDENT', "/$ident/u");
        $this->addPattern('OPERATOR', '/[+\\-\\*\\/%&\\|^<>&=!:\\.,;]+/');

        $exp = '[eE][+-]?\d+';
        // note the trailing i - which denotes imaginary literals
        $this->addPattern('NUMERIC', "/(?:\d+\.\d*(?:$exp)?|\d+$exp|\.\d+(?:$exp)?)i?/");
        $this->addPattern('NUMERIC', '/(?:0(?:\d+|x[a-fA-F0-9]+)|\d+)i?/');

        $this->addPattern('CHARACTER', "/'(?:\\\\(?:\d+|[uUxX][a-fA-F0-9]+|.)|.)'/u");
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('STRING', '/`(?:[^`\\\\]+|\\\\.)*(?:`|$)/s');

        $this->addIdentifierMapping('KEYWORD', array(
            'break',
            'case',
            'chan',
            'const',
            'continue',
            'default',
            'defer',
            'else',
            'fallthrough',
            'for',
            'func',
            'go',
            'goto',
            'if',
            'import',
            'interface',
            'map',
            'package',
            'range',
            'return',
            'select',
            'struct',
            'switch',
            'type',
            'var'
        ));

        $this->addIdentifierMapping('TYPE', array(
            'any',
            'bool',
            'byte',
            'complex',
            'complex64',
            'complex128',
            'int',
            'int8',
            'int16',
            'int32',
            'int64',
            'float',
            'float32',
            'float64',
            'string',
            'struct',
            'uint',
            'uint8',
            'uint16',
            'uint32',
            'uint64',
            'uintptr'
        ));
        $this->addIdentifierMapping('VALUE', array('false', 'iota', 'true'));

        // from the old luminous language file, don't know how sensible these are
        $this->addIdentifierMapping('FUNCTION', array(
            'append',
            'cap',
            'copy',
            'cmplx',
            'imag',
            'len',
            'make',
            'new',
            'panic',
            'print',
            'println',
            'real',
            'recover',
            'sizeof'
        ));
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        if (strpos($src, 'func ') !== false) {
            $p += 0.02;
        }
        if (preg_match('/func\s*\\(\s*\w+\s*\\*\s*\w+/', $src)) {
            $p += 0.05;
        }
        if (preg_match('/^package\s+\w+/', $src)) {
            $p += 0.01;
        }
        if (preg_match('/type\s+\w+\s+struct\s*\\{/', $src)) {
            $p += 0.03;
        }
        return $p;
    }
}
