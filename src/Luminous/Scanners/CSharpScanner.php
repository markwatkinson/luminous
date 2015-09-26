<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\CSharpKeywords;

class CSharpScanner extends SimpleScanner
{
    public function init()
    {
        $this->addPattern('PREPROCESSOR', "/\\#(?: [^\\\\\n]+ | \\\\. )*/sx");
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('CHARACTER', TokenPresets::$SINGLE_STR);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('IDENT', '/[a-z_]\w+/i');
        $this->addPattern('OPERATOR', '/[¬!%^&*\-=+~|?\\/><;:.,]+/i');

        $this->addIdentifierMapping('KEYWORD', array(
            'abstract',
            'as',
            'base',
            'break',
            'case',
            'catch',
            'checked',
            'class',
            'continue',
            'default',
            'delegate',
            'do',
            'event',
            'explicit',
            'extern',
            'else',
            'finally',
            'false',
            'fixed',
            'for',
            'foreach',
            'goto',
            'if',
            'implicit',
            'in',
            'interface',
            'internal',
            'is',
            'lock',
            'new',
            'null',
            'namespace',
            'operator',
            'out',
            'override',
            'params',
            'private',
            'protected',
            'public',
            'readonly',
            'ref',
            'return',
            'struct',
            'switch',
            'sealed',
            'sizeof',
            'stackalloc',
            'static',
            'this',
            'throw',
            'true',
            'try',
            'typeof',
            'unchecked',
            'unsafe',
            'using',
            'var',
            'virtual',
            'volatile',
            'while',
            'yield'
        ));

        $this->addIdentifierMapping('TYPE', array_merge(array(
            // primatives
            'bool',
            'byte',
            'char',
            'const',
            'double',
            'decimal',
            'enum',
            'float',
            'int',
            'long',
            'object',
            'sbyte',
            'short',
            'string',
            'uint',
            'ulong',
            'ushort',
            'void'
        ), \Luminous\Scanners\Keywords\CSharpKeywords::$TYPES));
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        if (preg_match('/^\s*#region\\b/m', $src)) {
            $p += 0.10;
        }
        if (preg_match('/^\s*using\s+System;/m', $src)) {
            $p += 0.10;
        }
        if (preg_match('/^\s*using\s+System\\..*;/m', $src)) {
            $p += 0.10;
        }
        if (preg_match('/partial\s+class\s+\w+/', $src)) {
            $p += 0.05;
        }
        return $p;
    }
}
