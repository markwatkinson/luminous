<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\EmbeddedWebScriptScanner;

/**
  * CSS scanner.
  * TODO: it would be nice if we could extend this somehow to handle
  * CSS dialects which allow rule nesting.
  */
class CssScanner extends EmbeddedWebScriptScanner
{
    private $expecting;

    public function __construct($src = null)
    {
        parent::__construct($src);

        $this->ruleTagMap = array(
            'TAG' => 'KEYWORD',
            'KEY' => 'TYPE',
            'SELECTOR' => 'VARIABLE',
            'ATTR_SELECTOR' => 'OPERATOR',
            'SSTRING' => 'STRING',
            'DSTRING' => 'STRING',
            'ROUND_BRACKET_SELECTOR' => 'OPERATOR',
        );

        $this->dirtyExitRecovery = array(
            'COMMENT' => '%.*?(?:\*/|$)%s',
            'SSTRING' => "/(?:[^\\\\']+|\\\\.)*(?:'|$)/",
            'DSTRING' => '/(?:[^\\\\"]+|\\\\.)*(?:"|$)/',
            'ATTR_SELECTOR' => '/(?: [^\\]\\\\]+ | \\\\.)* (?:\]|$)/xs',
            'ROUND_BRACKET_SELECTOR' => '/(?: [^\\)\\\\]+ | \\\\.)* (?:\)|$)/xs',
        );
        $this->state[] = 'global';
    }

    public function init()
    {
        $this->expecting = null;
    }

    public function main()
    {
        $commentRegex = '% /\* .*? \*/ %sx';

        $this->start();

        while (!$this->eos()) {
            if (!$this->cleanExit) {
                try {
                    $tok = $this->resume();
                    if ($this->serverBreak($tok)) {
                        break;
                    }
                    $this->record($this->match(), $tok);
                } catch (Exception $e) {
                    if (LUMINOUS_DEBUG) {
                        throw $e;
                    } else {
                        continue;
                    }
                }
            }
            $this->skipWhitespace();
            $pos = $this->pos();
            $tok = null;
            $m = null;
            $state = $this->state();
            $inBlock = $state === 'block';
            $inMedia = $state === 'media';
            $get = false;
            $c = $this->peek();

            $hexColourRegex = '/#[a-fA-F0-9]{3}(?:[a-fA-F0-9]{3})?/';
            $lengthRegex = '/-?(?>\d+)(\.(?>\d+))?(?:em|px|ex|ch|mm|cm|in|pt|%)?/';
            $mediaRegex = '/@(-(moz|ms|webkit|o)-)?keyframes\\b|media\\b/';
            if ($this->embeddedServer && $this->check($this->serverTags)) {
                $this->interrupt = true;
                $this->cleanExit = true;
                break;
            } elseif ($c === '/' && $this->scan(TokenPresets::$C_COMMENT_ML)) {
                $tok = 'COMMENT';
            } elseif ($inBlock && $c === '#' && $this->scan($hexColourRegex)) {
                $tok = 'NUMERIC';
            } elseif ($inBlock && (ctype_digit($c) || $c === '-') && $this->scan($lengthRegex) !== null) {
                $tok = 'NUMERIC';
            } elseif (!$inBlock && $this->scan('/(?<=[#\.:])[\w\-]+/') !== null) {
                $tok = 'SELECTOR';
            } elseif (!$inBlock && !$inMedia && $c === '@' && $this->scan($mediaRegex)) {
                $this->state[] = 'media';
                $tok = 'TAG';
            } elseif ((ctype_alpha($c) || strspn($c, "!@_-") === 1) &&  $this->scan('/(!?)[\-\w@]+/')) {
                if ($inMedia) {
                    $tok = 'VALUE';
                } elseif (!$inBlock || $this->matchGroup(1) !== '') {
                    $tok = 'TAG';
                } elseif ($this->expecting === 'key') {
                    $tok = 'KEY';
                } elseif ($this->expecting === 'value') {
                    $m = $this->match();
                    if ($m === 'url' || $m === 'rgb' || $m === 'rgba') {
                        $tok = 'FUNCTION';
                    } else {
                        $tok = 'VALUE';
                    }
                }
            } elseif (!$inBlock && $c === '[' && $this->scan('/\[ (?> [^\\]\\\\]+ | \\\\.)* \]/sx')) {
                // TODO attr selectors should handle embedded strings, I think.
                $tok = 'ATTR_SELECTOR';
            } elseif (!$inBlock && $c === '(' && $this->scan('/\( (?> [^\\)\\\\]+ | \\\\.)* \) /sx')) {
                $tok = 'ROUND_BRACKET_SELECTOR';
            } elseif ($c === '}' || $c === '{') {
                $get = true;
                if ($c === '}' && ($inBlock || $inMedia)) {
                    array_pop($this->state);
                    if ($inMedia) {
                        // @media adds a 'media' state, then the '{' begins a new global state.
                        // We've just popped global, now we need to pop media.
                        array_pop($this->state);
                    }
                } elseif (!$inBlock && $c === '{') {
                    if ($inMedia) {
                        $this->state[] = 'global';
                    } else {
                        $this->state[] = 'block';
                        $this->expecting = 'key';
                    }
                }
            } elseif ($c === '"' && $this->scan(TokenPresets::$DOUBLE_STR)) {
                $tok = 'DSTRING';
            } elseif ($c === "'" && $this->scan(TokenPresets::$SINGLE_STR)) {
                $tok = 'SSTRING';
            } elseif ($c === ':' && $inBlock) {
                $this->expecting = 'value';
                $get = true;
                $tok = 'OPERATOR';
            } elseif ($c=== ';' && $inBlock) {
                $this->expecting = 'key';
                $get = true;
                $tok = 'OPERATOR';
            } elseif ($this->embeddedHtml && $this->check('%<\s*/\s*style%i')) {
                $this->interrupt = false;
                $this->cleanExit = true;

                break;
            } elseif ($this->scan('/[:\\.#>*]+/'))
                $tok = 'OPERATOR';
            else {
                $get = true;
            }

            if ($this->serverBreak($tok)) {
                break;
            }

            $m = $get ? $this->get() : $this->match();
            $this->record($m, $tok);
            assert($this->pos() > $pos || $this->eos());
        }
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0;
        if (preg_match("/(font-family|font-style|font-weight)\s*+:\s*+[^;\n\r]*+;/", $src)) {
            $p += 0.15;
        }
        if (strpos($src, '!important') !== false) {
            $p += 0.05;
        }
        // generic rule
        if (preg_match("/\\b(div|span|table|body)\\b [^\n\r\{]*+ [\r\n]*+ \{/x", $src)) {
            $p += 0.10;
        }
        return $p;
    }
}
