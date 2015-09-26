<?php

namespace Luminous\Utils;

/**
 * The parsing class
 */
class SassParser
{
    public $tokens;
    public $index;
    public $stack;
    public static $deleteToken = 'delete';

    /**
     * Returns true if the next token is the given token name
     * optionally skipping whitespace
     */
    public function nextIs($tokenName, $ignoreWhitespace = false)
    {
        $i = $this->index+1;
        $len = count($this->tokens);
        while ($i < $len) {
            $tok = $this->tokens[$i][0];
            if ($ignoreWhitespace && $tok === 'WHITESPACE') {
                $i++;
            } else {
                return $tok === $tokenName;
            }
        }
        return false;
    }

    /**
     * Returns the index of the next match of the sequence of tokens
     * given, optionally ignoring ertain tokens
     */
    public function nextSequence($sequence, $ignore = array())
    {
        $i = $this->index+1;
        $len = count($this->tokens);
        $seqLen = count($sequence);
        $seq = 0;
        $seqStart = 0;
        while ($i < $len) {
            $tok = $this->tokens[$i][0];
            if ($tok === $sequence[$seq]) {
                if ($seq === 0) {
                    $seqStart = $i;
                }
                $seq++;
                $i++;
                if ($seq === $seqLen) {
                    return $seqStart;
                }
            } else {
                if (in_array($tok, $ignore)) {
                } else {
                    $seq = 0;
                }
                $i++;
            }
        }
        return $len;
    }

    /**
     * Returns the first token which occurs out of the set of given tokens
     */
    public function nextOf($tokenNames)
    {
        $i = $this->index+1;
        $len = count($this->tokens);
        while ($i < $len) {
            $tok = $this->tokens[$i][0];
            if (in_array($tok, $tokenNames)) {
                return $tok;
            }
            $i++;
        }
        return null;
    }

    /**
     * Returns the index of the next token with the given token name
     */
    public function nextOfType($tokenName)
    {
        $i = $this->index+1;
        $len = count($this->tokens);
        while ($i < $len) {
            $tok = $this->tokens[$i][0];
            if ($tok === $tokenName) {
                return $i;
            }
            $i++;
        }
        return $len;
    }

    private function parseIdentifier($token)
    {
        $val = $token[1];
        $c = isset($val[0]) ? $val[0] : '';
        if (ctype_digit($c) || $c === '#') {
            $token[0] = 'NUMERIC';
        }
    }

    /**
    * Parses a selector rule
    */
    private function parseRule()
    {
        $newToken = $this->tokens[$this->index];
        $set = false;
        if ($this->index > 0) {
            $prevToken = &$this->tokens[$this->index-1];
            $prevTokenType = &$prevToken[0];
            $prevTokenText = &$prevToken[1];
            $concat = false;

            $map = array(
                'DOT' => 'CLASS_SELECTOR',
                'HASH' => 'ID_SELECTOR',
                'COLON' => 'PSEUDO_SELECTOR',
                'DOUBLE_COLON' => 'PSEUDO_SELECTOR'
            );
            if (isset($map[$prevTokenType])) {
                // mark the prev token for deletion and concat into one.
                $newToken[0] = $map[$prevTokenType];
                $prevTokenType = self::$deleteToken;
                $newToken[1] = $prevTokenText . $newToken[1];
                $set = true;
            }
        }
        if (!$set) {
            // must be an element
            $newToken[0] = 'ELEMENT_SELECTOR';
        }
        $this->tokens[$this->index] = $newToken;
    }

    /**
     * Cleans up the token stream by deleting any tokens marked for
     * deletion, and makes sure the array is continuous afterwards.
     */
    private function cleanup()
    {
        foreach ($this->tokens as $i => $t) {
            if ($t[0] === self::$deleteToken) {
                unset($this->tokens[$i]);
            }
        }
        $this->tokens = array_values($this->tokens);
    }

    /**
     * Main parsing function
     */
    public function parse()
    {
        $newTokens = array();
        $len = count($this->tokens);
        $this->stack = array();
        $propValue = 'PROPERTY';
        $pushes = array(
            'L_BRACKET' => 'bracket',
            'L_BRACE' => 'brace',
            'AT_IDENTIFIER' => 'at',
            'L_SQ_BRACKET' => 'square'
        );
        $pops = array(
            'R_BRACKET' => 'bracket',
            'R_BRACE' => 'brace',
            'R_SQ_BRACKET' => 'square'
        );
        $this->index = 0;
        while ($this->index < $len) {
            $token = &$this->tokens[$this->index];
            $stackSize = count($this->stack);
            $state = !$stackSize? null : $this->stack[$stackSize - 1];
            $tokName = &$token[0];
            $inBrace = in_array('brace', $this->stack);
            $inBracket = in_array('bracket', $this->stack);
            $inSq = in_array('square', $this->stack);
            $inAt = in_array('at', $this->stack);
            if ($tokName === self::$deleteToken) {
                continue;
            }

            if ($tokName === 'L_BRACE') {
                if ($state === 'at') {
                    array_pop($this->stack);
                }
                $this->stack[] = $pushes[$tokName];
                $propValue = 'PROPERTY';
            } elseif (isset($pushes[$tokName])) {
                $this->stack[] = $pushes[$tokName];
            } elseif (isset($pops[$tokName]) && $state === $pops[$tokName]) {
                array_pop($this->stack);
            } elseif (!$inBracket && $tokName === 'COLON') {
                $propValue = 'VALUE';
            } elseif ($tokName === 'SEMICOLON') {
                $propValue = 'PROPERTY';
                if ($state === 'at') {
                    array_pop($this->stack);
                }
            } elseif ($tokName === 'GENERIC_IDENTIFIER') {
                // this is where the fun starts.
                // we have to figure out exactly what this is
                // if we can look ahead and find a '{' before we find a
                // ';', then this is part of a selector.
                // Otherwise it's part of a property/value pair.
                // the exception is when we have something like:
                // font : { family : sans-serif; }
                // then we need to check for ':{'
                if ($inSq) {
                    $token[0] = 'ATTR_SELECTOR';
                } elseif ($inBracket) {
                    $this->parseIdentifier($token);
                } elseif (!$inAt) {
                    $semi = $this->nextOfType('SEMICOLON');
                    $colonBrace = $this->nextSequence(array('COLON', 'L_BRACE'), array('WHITESPACE'));
                    $brace = $this->nextOfType('L_BRACE');

                    $ruleTerminator = min($semi, $colonBrace);
                    if ($brace < $ruleTerminator) {
                        $this->parseRule();
                        $propValue = 'PROPERTY';
                    } else {
                        $this->tokens[$this->index][0] = $propValue;
                        if ($propValue === 'VALUE') {
                            $this->parseIdentifier($token);
                        }
                    }
                }
            }
            $this->index++;
        }
        $this->cleanup();
    }
}
