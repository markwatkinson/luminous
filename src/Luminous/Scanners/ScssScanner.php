<?php

namespace Luminous\Scanners;

use Luminous\Utils\SassParser;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\Scanner;

/**
 * The SCSS scanner is quite complex, having to deal with nested rules
 * and so forth and some disambiguation is non-trivial, so we are employing
 * a two-pass approach here - we first tokenize the source as normal with a
 * scanner, then we parse the token stream with a parser to figure out
 * what various things really are.
 */
class ScssScanner extends Scanner
{
    private $regexen = array();

    public $ruleTagMap = array(
        'PROPERTY' => 'TYPE',
        'COMMENT_SL' => 'COMMENT',
        'COMMENT_ML' => 'COMMENT',
        'ELEMENT_SELECTOR' => 'KEYWORD',
        'STRING_S' => 'STRING',
        'STRING_D' => 'STRING',
        'CLASS_SELECTOR' => 'VARIABLE',
        'ID_SELECTOR' => 'VARIABLE',
        'PSEUDO_SELECTOR' => 'OPERATOR',
        'ATTR_SELECTOR' => 'OPERATOR',
        'WHITESPACE' => null,
        'COLON' => 'OPERATOR',
        'SEMICOLON' => 'OPERATOR',
        'COMMA' => 'OPERATOR',
        'R_BRACE' => 'OPERATOR',
        'R_BRACKET' => 'OPERATOR',
        'R_SQ_BRACKET' => 'OPERATOR',
        'L_BRACE' => 'OPERATOR',
        'L_BRACKET' => 'OPERATOR',
        'L_SQ_BRACKET' => 'OPERATOR',
        'OTHER_OPERATOR' => 'OPERATOR',
        'GENERIC_IDENTIFIER' => null,
        'AT_IDENTIFIER' => 'KEYWORD',
        'IMPORTANT' => 'KEYWORD',
    );

    public function init()
    {
        $this->regexen = array(
            // For the first pass we just feed in a bunch of tokens.
            // Some of these are generic and will require disambiguation later
            'COMMENT_SL' => TokenPresets::$C_COMMENT_SL,
            'COMMENT_ML' =>  TokenPresets::$C_COMMENT_ML,
            'STRING_S' => TokenPresets::$SINGLE_STR,
            'STRING_D' => TokenPresets::$DOUBLE_STR,
            // TODO check var naming, is $1 a legal variable?
            'VARIABLE' => '%\$[\-a-z_0-9]+ | \#\{\$[\-a-z_0-9]+\} %x',
            'AT_IDENTIFIER' => '%@[a-zA-Z0-9]+%',

            // This is generic - it may be a selector fragment, a rule, or
            // even a hex colour.
            'GENERIC_IDENTIFIER' =>
                '@
                    \\#[a-fA-F0-9]{3}(?:[a-fA-F0-9]{3})?
                    |
                    [0-9]+(\.[0-9]+)?(\w+|%|in|cm|mm|em|ex|pt|pc|px|s)?
                    |
                    -?[a-zA-Z_\-0-9]+[a-zA-Z_\-0-9]*
                    |&
                @x',
            'IMPORTANT' => '/!important/',
            'L_BRACE' => '/\{/',
            'R_BRACE' => '/\}/',
            'L_SQ_BRACKET' => '/\[/',
            'R_SQ_BRACKET' => '/\]/',
            'L_BRACKET' => '/\(/',
            'R_BRACKET' => '/\)/',

            'DOUBLE_COLON' => '/::/',
            'COLON' => '/:/',
            'SEMICOLON' => '/;/',

            'DOT' => '/\./',
            'HASH' => '/#/',

            'COMMA' => '/,/',

            'OTHER_OPERATOR' => '@[+\-*/%&>=!]@',

            'WHITESPACE' => '/\s+/'
        );
    }

    public function main()
    {
        while (!$this->eos()) {
            $m = null;
            foreach ($this->regexen as $token => $pattern) {
                if (($m = $this->scan($pattern)) !== null) {
                    $this->record($m, $token);
                    break;
                }
            }
            if ($m === null) {
                $this->record($this->get(), null);
            }
        }
        $parser = new SassParser();
        $parser->tokens = $this->tokens;
        $parser->parse();
        $this->tokens = $parser->tokens;
    }
}
