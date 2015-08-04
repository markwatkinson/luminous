<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\SimpleScanner;

/*
 * TODO: user defined types and stuff
 */

class AdaScanner extends SimpleScanner
{
    public function init()
    {
        // http://en.wikibooks.org/wiki/Ada_Programming/Keywords
        // http://en.wikibooks.org/wiki/Ada_Programming/All_Keywords
        $kws = array(
            'abort',
            'abstract',
            'accept',
            'access',
            'aliased',
            'all',
            'array',
            'at',
            'begin',
            'body',
            'case',
            'constant',
            'declare',
            'delay',
            'delta',
            'digits',
            'do',
            'else',
            'elsif',
            'end',
            'entry',
            'exception',
            'exit',
            'for',
            'function',
            'generic',
            'goto',
            'if',
            'interface',
            'is',
            'limited',
            'loop',
            'new',
            'of',
            'others',
            'out',
            'overriding',
            'package',
            'pragma',
            'private',
            'procedure',
            'protected',
            'raise',
            'range',
            'record',
            'renames',
            'requeue',
            'return',
            'reverse',
            'select',
            'separate',
            'subtype',
            'synchronized',
            'tagged',
            'task',
            'terminate',
            'then',
            'type',
            'until',
            'use',
            'when',
            'while',
            'with',
        );
        $ops = array('abs', 'and', 'in', 'mod', 'not', 'or', 'rem', 'xor');
        $vals = array('false', 'null', 'true');
        // http://en.wikibooks.org/wiki/Ada_Programming/Type_System#Predefined_types
        $types = array(
            'Float',
            'Duration',
            'Character',
            'String',
            'Boolean',
            'Address',
            'Storage_Offset',
            'Storage_Count',
            'Storage_Element',
            'Storage_Array',
            'Wide_character',
            'Wide_Wide_Character',
            'Wide_String',
            'Wide_Wide_String',
            'Integer',
            'Long',
            'Short',
            'Byte'
        );

        $ident = '(?i:[a-z](?:_?[a-z]++|\d++)*+)';
        // http://en.wikibooks.org/wiki/Ada_Programming/Lexical_elements#Identifiers
        $this->addPattern('OO', "/(?<=[a-z0-9_]')$ident/");
        $this->addPattern('IDENT', "/$ident/");
        // http://en.wikibooks.org/wiki/Ada_Programming/Lexical_elements#Numbers
        // no bnf :( might be wrong
        $this->addPattern('NUMERIC', '/\d+#[a-f0-9]*#/i');
        $this->addPattern('NUMERIC', "/[0-9]++[0-9_]*+(\.[0-9_]++)?([eE][\-+]?[0-9_]++)?/");
        $this->addPattern('COMMENT', '/--.*/');
        $this->addPattern('OPERATOR', '@=|/=|>=?|<=?|\+|-|\*\*?|/|&|:=@');

        // http://rosettacode.org/wiki/Special_characters#Ada
        $this->addPattern('CHARACTER', "/'.'/");
        $this->addPattern('STRING', '/"(?:[^"]++|"")*"/');

        $this->addIdentifierMapping('KEYWORD', $kws);
        $this->addIdentifierMapping('OPERATOR', $ops);
        $this->addIdentifierMapping('VALUE', $vals);
        $this->addIdentifierMapping('TYPE', $types);
    }
}
