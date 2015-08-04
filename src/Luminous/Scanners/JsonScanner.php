<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\Scanner;

class JsonScanner extends Scanner
{
    private $stack = array();

    public function init()
    {
        $this->addIdentifierMapping('KEYWORD', array('true', 'false', 'null'));
    }

    public function state()
    {
        if (!empty($this->stack)) {
            return $this->stack[count($this->stack) - 1][0];
        }
        return null;
    }

    private function expecting($x = null)
    {
        if ($x !== null && !empty($this->stack)) {
            $this->stack[count($this->stack) - 1][1] = $x;
        }
        if (!empty($this->stack)) {
            return $this->stack[count($this->stack) - 1][1];
        }
        return null;
    }

    public function main()
    {
        while (!$this->eos()) {
            $tok = null;
            $c = $this->peek();

            list($state, $expecting) = array($this->state(), $this->expecting());

            $this->skipWhitespace();
            if ($this->eos()) {
                break;
            }
            if ($this->scan(TokenPresets::$NUM_REAL) !== null) {
                $tok = 'NUMERIC';
            } elseif ($this->scan('/[a-zA-Z]\w*/')) {
                $tok = 'IDENT';
            } elseif ($this->scan(TokenPresets::$DOUBLE_STR)) {
                $tok = ($state === 'obj' && $expecting === 'key')? 'TYPE' : 'STRING';
            } elseif ($this->scan('/\[/')) {
                $this->stack[] = array('array', null);
                $tok = 'OPERATOR';
            } elseif ($this->scan('/\]/')) {
                if ($state === 'array') {
                    array_pop($this->stack);
                    $tok = 'OPERATOR';
                }
            } elseif ($this->scan('/\{/')) {
                $this->stack[] = array('obj', 'key');
                $tok = 'OPERATOR';
            } elseif ($state === 'obj' && $this->scan('/\}/')) {
                array_pop($this->stack);
                $tok = 'OPERATOR';
            } elseif ($state === 'obj' && $this->scan('/:/')) {
                $this->expecting('value');
                $tok = 'OPERATOR';
            } elseif ($this->scan('/,/')) {
                if ($state === 'obj') {
                    $this->expecting('key');
                    $tok = 'OPERATOR';
                } elseif ($state === 'array') {
                    $tok = 'OPERATOR';
                }
            } else {
                $this->scan('/./');
            }

            $this->record($this->match(), $tok);
        }
    }

    public static function guessLanguage($src, $info)
    {
        // JSON is fairly hard to guess
        $p = 0;
        $src_ = trim($src);
        if (!empty($src_)) {
            $char = $src_[0];
            $char2 = $src_[strlen($src_) - 1];
            $str = '"(?>[^"\\\\]+|\\\\.)"';
            // looks like an object or array
            if (($char === '[' && $char2 === ']') || ($char === '{' && $char2 === '}')) {
                $p += 0.05;
            } elseif (preg_match("/^(?:$str|(\d+(\.\d+)?([eE]\d+)?)|true|false|null)$/", $src_)) {
                // just a string or number or value
                $p += 0.1;
            }
        }
        return $p;
    }
}
