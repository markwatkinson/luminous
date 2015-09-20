<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\Scanner;
use Luminous\Scanners\Keywords\PhpKeywords;

/*
 * This is not a scanner called by an external interface, it's controlled
 * by LuminousPHPScanner.
 *
 * It should break when it sees a '?>', but it should assume it's in php
 * when it's called.
 */
class PhpSubScanner extends Scanner
{
    protected $caseSensitive = false;
    public $snippet = false;

    public function init()
    {
        $this->addPattern('TERM', '/\\?>/');
        $this->addPattern('COMMENT', '% (?://|\#) .*? (?=\\?>|$)  %xm');
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        // this should be picked up by the LuminousPHPScanner, but in case
        // a user incorrectly calls the PHP-snippet scanner, we detect it.
        $this->addPattern('DELIMITER', '/<\?(?:php)?/');
        $this->addPattern('OPERATOR', '@[!%^&*\\-=+~:<>/\\|\\.;,]+|\\?(?!>)@');
        $this->addPattern('VARIABLE', '/\\$\\$?[a-zA-Z_]\w*/');
        $this->addPattern('IDENT', '/[a-zA-Z_]\w*/');
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('STRING', TokenPresets::$SINGLE_STR);
        $this->addPattern('FUNCTION', '/`(?>[^`\\\\]+|\\\\.)*(`|$)/s');
        $this->addIdentifierMapping('FUNCTION', PhpKeywords::FUNCTIONS);
        $this->addIdentifierMapping('KEYWORD', PhpKeywords::KEYWORDS);

        $this->addFilter('STRING', array($this, 'strFilter'));
        $this->addFilter('HEREDOC', array($this, 'strFilter'));
        $this->addFilter('NOWDOC', array($this, 'nowdocFilter'));
    }

    public static function strFilter($token)
    {
        if ($token[1][0] !== '"' && $token[0] !== 'HEREDOC') {
            return $token;
        } elseif (strpos($token[1], '$') === false) {
            return $token;
        }

        $token = Utils::escapeToken($token);
        // matches $var, ${var} and {$var} syntax
        $token[1] = preg_replace(
            '/
                (?: \$\{ | \{\$ ) [^}]++ \}
                |
                \$\$?[a-zA-Z_]\w*
            /x',
            '<VARIABLE>$0</VARIABLE>',
            $token[1]
        );
        return $token;
    }

    public static function nowdocFilter($token)
    {
        $token[0] = 'HEREDOC';
        return $token;
    }

    public function main()
    {
        $this->start();
        while (!$this->eos()) {
            $tok = null;

            $index = $this->pos();

            if (($match = $this->nextMatch()) !== null) {
                $tok = $match[0];
                if ($match[1] > $index) {
                    $this->record(substr($this->string(), $index, $match[1] - $index), null);
                }
            } else {
                $this->record($this->rest(), null);
                $this->terminate();
                break;
            }

            if ($tok === 'TERM') {
                $this->unscan();
                break;
            }

            if ($tok === 'IDENT') {
                // do the user defns here, i.e. class XYZ extends/implements ABC
                // or function XYZ
                $m = $this->match();
                $this->record($m, 'IDENT');
                if (($m === 'class' || $m === 'function' || $m === 'extends' || $m === 'implements')) {
                    if ($this->scan('/(\s+)([a-zA-Z_]\w*)/')) {
                        $this->record($this->matchGroup(1), null);
                        $this->record($this->matchGroup(2), 'USER_FUNCTION');
                        $this->userDefs[$this->matchGroup(2)] = ($m === 'function') ? 'FUNCTION' : 'TYPE';
                    }
                }
                continue;
            } elseif ($tok === 'OPERATOR') {
                // figure out heredoc syntax here
                if (strpos($this->match(), '<<<') !== false) {
                    $this->record($this->match(), $tok);
                    $this->scan('/([\'"]?)([\w]*)((?:\\1)?)/');
                    $g = $this->matchGroups();
                    $nowdoc = false;
                    if ($g[1]) {
                        // nowdocs are delimited by single quotes. Heredocs MAY be
                        // delimited by double quotes, or not.
                        $nowdoc = $g[1] === "'";
                        $this->record($g[1], null);
                    }
                    $delimiter = $g[2];
                    $this->record($delimiter, 'KEYWORD');
                    if ($g[3]) {
                        $this->record($g[3], null);
                    }
                    // bump us to the end of the line
                    if (strlen($this->scan('/.*/'))) {
                        $this->record($this->match(), null);
                    }
                    if ($this->scanUntil("/^$delimiter|\z/m")) {
                        $this->record($this->match(), ($nowdoc) ? 'NOWDOC' : 'HEREDOC');
                        if ($this->scan('/\w+/')) {
                            $this->record($this->match(), 'KEYWORD');
                        }
                    }
                    continue;
                }
            }
            assert($this->pos() > $index);
            $this->record($this->match(), $tok);
        }
    }
}
