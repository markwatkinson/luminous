<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\EmbeddedWebScriptScanner;

class HtmlScanner extends EmbeddedWebScriptScanner
{
    private $childState = null;

    public $scripts = true;

    // XML literals are part of several languages. Settings this makes the scanner
    // halt as soon as it pops the its root tag from the stack, so no trailing
    // code is consumed.
    public $xmlLiteral = false;
    private $tagStack = array();

    public function __construct($src = null)
    {
        $this->dirtyExitRecovery = array(
            'DSTRING' => '/[^">]*+("|$|(?=[>]))/',
            'SSTRING' => "/[^'>]*+('|$|(?=[>]))/",
            'COMMENT1' => '/(?> [^\\-]+ | -(?!->))*(?:-->|$)/x',
            'COMMENT2' => '/[^>]*+(?:>|$)/s',
            'CDATA' => '/(?>[^\\]]+|\\](?!\\]>))*(?:\\]{2}>|$)/xs',
            'ESC' => '/[^;]*+(?:;|$)/',
            'TYPE' => '/[^\s]*/',
            'VALUE' => '/[^\s]*/',
            'HTMLTAG' => '/[^\s]*/',
        );

        $this->ruleTagMap = array(
            'DSTRING' => 'STRING',
            'SSTRING' => 'STRING',
            'COMMENT1' => 'COMMENT',
            'COMMENT2' => 'COMMENT',
            'CDATA' => 'COMMENT',
        );

        parent::__construct($src);
    }

    public function scanChild($lang)
    {
        assert(isset($this->childScanners[$lang]));
        $scanner = $this->childScanners[$lang];
        $scanner->pos($this->pos());
        $substr = $scanner->main();
        $this->tokens[] = array(null, $scanner->tagged(), true);
        $this->pos($scanner->pos());
        if ($scanner->interrupt) {
            $this->childState = array($lang, $this->pos());
        } else {
            $this->childState = null;
        }
    }

    public function init()
    {
        $this->addPattern('', '/&/');
        if ($this->embeddedServer) {
            $this->addPattern('TERM', $this->serverTags);
        }
        $this->addPattern('', '/</');
        $this->state = 'global';
        if ($this->scripts) {
            $js = new JavaScriptScanner($this->string());
            $js->embeddedServer = $this->embeddedServer;
            $js->embeddedHtml = true;
            $js->serverTags = $this->serverTags;
            $js->init();

            $css = new CssScanner($this->string());
            $css->embeddedServer = $this->embeddedServer;
            $css->embeddedHtml = true;
            $css->serverTags = $this->serverTags;
            $css->init();

            $this->addChildScanner('js', $js);
            $this->addChildScanner('css', $css);
        }
    }

    private $tagname = '';
    private $expecting = '';

    public function main()
    {
        $this->start();
        $this->interrupt = false;

        while (!$this->eos()) {
            $index = $this->pos();

            if ($this->embeddedServer &&  $this->check($this->serverTags)) {
                $this->interrupt = true;
                break;
            }

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
                        $this->cleanExit = true;
                    }
                }
                continue;
            }

            if ($this->childState !== null && $this->childState[1] < $this->pos()) {
                $this->scanChild($this->childState[0]);
                continue;
            }

            $inTag = $this->state === 'tag';
            if (!$inTag) {
                $next = $this->nextMatch(false);
                if ($next) {
                    $skip = $next[1] - $this->pos();
                    $this->record($this->get($skip), null);
                    if ($next[0] === 'TERM') {
                        $this->interrupt = true;
                        break;
                    }
                }
            } else {
                $this->skipWhitespace();
                if ($this->embeddedServer && $this->check($this->serverTags)) {
                    $this->interrupt = true;
                    break;
                }
            }

            $index = $this->pos();
            $c = $this->peek();

            $tok = null;
            $get = false;
            if (!$inTag && $c === '&' && $this->scan('/&[^;\s]+;/')) {
                $tok = 'ESC';
            } elseif (!$inTag && $c === '<') {
                if ($this->peek(2) === '<!') {
                    // urgh
                    $cdataRegex =
                        '/
                            <!\\[CDATA\\[
                            (?> [^\\]]+ | \\](?!\\]>) )*
                            (?: \\]\\]> | $ )
                        /ixs';
                    if ($this->scan('/(<)(!DOCTYPE)/i')) {
                        // special case: doctype
                        $matches = $this->matchGroups();
                        $this->record($matches[1], null);
                        $this->record($matches[2], 'KEYWORD');
                        $this->state = 'tag';
                        continue;
                    } elseif ($this->scan($cdataRegex)) {
                        $tok = 'CDATA';
                    } elseif ($this->scan('/<!--(?> [^\\-]+ | (?:-(?!->))+)* (?:-->|$)/xs')) {
                        $tok = 'COMMENT1';
                    } elseif ($this->scan('/<![^>]*+(?:>|$)/s')) {
                        $tok = 'COMMENT2';
                    } else {
                        assert(0);
                    }
                } else {
                    // check for <script>
                    $this->state = 'tag';
                    $this->expecting = 'tagname';
                    $get = true;
                }
            } elseif ($c === '>') {
                $get = true;
                $this->state = 'global';
                if ($this->scripts && ($this->tagname === 'script' || $this->tagname === 'style')) {
                    $this->record($this->get(), null);
                    $this->scanChild(($this->tagname === 'script') ? 'js' : 'css');
                    continue;
                }
                $this->tagname = '';
            } elseif ($inTag && $this->scan('@/\s*>@')) {
                $this->state = 'global';
                array_pop($this->tagStack);
            } elseif ($inTag && $c === "'" && $this->scan("/' (?> [^'\\\\>]+ | \\\\.)* (?:'|$|(?=>))/xs")) {
                $tok = 'SSTRING';
                $this->expecting = '';
            } elseif ($inTag && $c === '"' && $this->scan('/" (?> [^"\\\\>]+ | \\\\.)* (?:"|$|(?=>))/xs')) {
                $tok = 'DSTRING';
                $this->expecting = '';
            } elseif ($inTag && $this->scan('@(?:(?<=<)/)?[^\s=<>/]+@') !== null) {
                if ($this->expecting === 'tagname') {
                    $tok = 'HTMLTAG';
                    $this->expecting = '';
                    $this->tagname = strtolower($this->match());
                    if ($this->tagname[0] === '/') {
                        array_pop($this->tagStack);
                    } else {
                        $this->tagStack[] = $this->tagname;
                    }
                } elseif ($this->expecting === 'value') {
                    $tok = 'VALUE'; // val as in < a href=*/index.html*
                    $this->expecting = '';
                } else {
                    $tok = 'TYPE';     // attr, as in <a *HREF*= ....
                }
            } elseif ($inTag && $c === '=') {
                $this->expecting = 'value';
                $get = true;
            } else {
                $get = true;
            }
            if (!$get && $this->serverBreak($tok)) {
                break;
            }

            $this->record($get ? $this->get() : $this->match(), $tok);
            assert($index < $this->pos() || $this->eos());
            if ($this->xmlLiteral && $this->state !== 'tag' && empty($this->tagStack)) {
                return;
            }
        }
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0;
        // we have to be a bit careful of XML literals nested in other
        // langauges here.
        // We also have to becareful to take precedence over embedded CSS and JS
        // but leave some room for being embedded in PHP or Rails
        // so we're not going to go over 0.75
        $doctype = strpos(ltrim($src), '<!DOCTYPE ');
        if ($doctype === 0) {
            return 0.75;
        }
        if (preg_match('/<(a|table|span|div)\s+class=/', $src)) {
            $p += 0.05;
        }
        if (preg_match('%</(a|table|span|div)>%', $src)) {
            $p += 0.05;
        }
        if (preg_match('/<(style|script)\\b/', $src)) {
            $p += 0.15;
        }
        if (preg_match('/<!\\[CDATA\\[/', $src)) {
            $p += 0.15;
        }

        // look for 1 tag at least every 4 lines
        $lines = preg_match_all('/$/m', preg_replace('/^\s+/m', '', $src), $m);
        if (preg_match_all('%<[!?/]?[a-zA-Z_:\\-]%', $src, $m) > $lines/4) {
            $p += 0.15;
        }
        return $p;
    }
}
