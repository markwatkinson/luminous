<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\EmbeddedWebScriptScanner;

/**
 * This is a rename of the JavaScript scanner.
 * TODO Some of these things are JS specific and should be moved into
 * the new JS scanner.
 */

class EcmaScriptScanner extends EmbeddedWebScriptScanner
{
    public $scriptTags = '</script>';
    // regular expressions in JavaScript are delimited by '/', BUT, the slash
    // character may appear unescaped within character classes
    // we can handle this fairly easily with a single regex because the classes
    // do not nest
    // TODO:
    // I do not know if this is specific to Javascript or ECMAScript derivatives
    // as a whole, I also don't know if multi-line regexen are legal (i.e. when
    // the definition spans multiple lines)
    protected $regexRegex =
        "%
            /
            (?:
                [^\\[\\\\/]+               # not slash, backslash, or [
                | \\\\.                    # escape char
                |
                (?:                        # char class [..]
                    \\[
                        (?:
                            [^\\]\\\\]+    # not slash or ]
                            | \\\\.        # escape
                        )*
                    (?: \\] | \$)
                )                          # close char class
            )*
            (?: /[iogmx]* | \$)            #delimiter or eof
        %sx";

    // logs a persistent token stream so that we can lookbehind to figure out
    // operators vs regexes.
    protected $tokens = array();

    private $childState = null;

    public function __construct($src = null)
    {
        $this->ruleTagMap = array(
            'COMMENT_SL' => 'COMMENT',
            'SSTRING' => 'STRING',
            'DSTRING' => 'STRING',
            'OPENER' => null,
            'CLOSER' => null,
        );
        $this->dirtyExitRecovery = array(
            'COMMENT_SL' => '/.*/',
            'COMMENT' => '%.*?(\*/|$)%s',
            'SSTRING' => "/(?:[^\\\\']+|\\\\.)*('|$)/",
            'DSTRING' => '/(?:[^\\\\"]+|\\\\.)*("|$)/',
            // FIXME: Anyone using a server-side interruption to build a regex is
            // frankly insane, but we are wrong in the case that they were in a
            // character class when the server language interrupted, and we may
            // exit the regex prematurely with this
            'REGEX' => '%(?:[^\\\\/]+|\\\\.)*(?:/[iogmx]*|$)%',
        );

        parent::__construct($src);
        $this->addIdentifierMapping('KEYWORD', array(
            'break',
            'case',
            'catch',
            'comment',
            'continue',
            'do',
            'default',
            'delete',
            'else',
            'export',
            'for',
            'function',
            'if',
            'import',
            'in',
            'instanceof',
            'label',
            'new',
            'null',
            'return',
            'switch',
            'throw',
            'try',
            'typeof',
            'var',
            'void',
            'while',
            'with',
            'true',
            'false',
            'this'
        ));
        $this->addIdentifierMapping('FUNCTION', array(
            '$',
            'alert',
            'confirm',
            'clearTimeout',
            'clearInterval',
            'encodeURI',
            'encodeURIComponent',
            'eval',
            'isFinite',
            'isNaN',
            'parseInt',
            'parseFloat',
            'prompt',
            'setTimeout',
            'setInterval',
            'decodeURI',
            'decodeURIComponent',
            'jQuery'
        ));

        $this->addIdentifierMapping('TYPE', array(
            'Array',
            'Boolean',
            'Date',
            'Error',
            'EvalError',
            'Infinity',
            'Image',
            'Math',
            'NaN',
            'Number',
            'Object',
            'Option',
            'RangeError',
            'ReferenceError',
            'RegExp',
            'String',
            'SyntaxError',
            'TypeError',
            'URIError',

            'document',
            'undefined',
            'window'
        ));
    }

    public function isOperand()
    {
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            $tok = $this->tokens[$i][0];
            if ($tok === null || $tok === 'COMMENT' || $tok === 'COMMENT_SL') {
                continue;
            }
            return ($tok === 'OPERATOR' || $tok === 'OPENER');
        }
        return true;
    }

    public function init()
    {
        if ($this->embeddedServer) {
            $this->addPattern('STOP_SERVER', $this->serverTags);
        }
        if ($this->embeddedHtml) {
            $this->addPattern('STOP_SCRIPT', '%</script>%');
        }

        $opPattern = '[=!+*%\-&^|~:?\;,.>';
        if (!($this->embeddedServer || $this->embeddedHtml)) {
            $opPattern .= '<]+';
        } else {
            // build an alternation with a < followed by a lookahead
            $opPattern .= ']|<(?![';
            // XXX this covers <? and <% but not very well
            if ($this->embeddedServer) {
                $opPattern .= '?%';
            }
            if ($this->embeddedHtml) {
                $opPattern .= '/';
            }
            $opPattern .= '])'; // closes lookahead
            $opPattern = "(?:$opPattern)+";
        }
        $opPattern = "@$opPattern@";

        $this->addPattern('IDENT', '/[a-zA-Z_$][_$\w]*/');
        // NOTE: slash is a special case, and </ may be a script close
        $this->addPattern('OPERATOR', $opPattern);
        // we care about openers for figuring out where regular expressions are
        $this->addPattern('OPENER', '/[\[\{\(]+/');
        $this->addPattern('CLOSER', '/[\]\}\)]+/');

        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('SSTRING', TokenPresets::$SINGLE_STR_SL);
        $this->addPattern('DSTRING', TokenPresets::$DOUBLE_STR_SL);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('COMMENT_SL', TokenPresets::$C_COMMENT_SL);
        // special case
        $this->addPattern('SLASH', '%/%');

        $stopPatterns = array();

        $xmlScanner = new HtmlScanner($this->string());
        $xmlScanner->xmlLiteral = true;
        $xmlScanner->scripts = false;
        $xmlScanner->embeddedServer = $this->embeddedServer;
        if ($this->embeddedServer) {
            $xmlScanner->serverTags = $this->serverTags;
        }
        $xmlScanner->init();
        $xmlScanner->pos($this->pos());
        $this->addChildScanner('xml', $xmlScanner);
    }

    // c+p from HTML scanner
    public function scanChild($lang)
    {
        assert(isset($this->childScanners[$lang]));
        $scanner = $this->childScanners[$lang];
        $scanner->pos($this->pos());
        $substr = $scanner->main();
        $this->record($scanner->tagged(), 'XML', true);
        $this->pos($scanner->pos());
        if ($scanner->interrupt) {
            $this->childState = array($lang, $this->pos());
        } else {
            $this->childState = null;
        }
    }

    public function main()
    {
        $this->start();
        $this->interrupt = false;
        while (!$this->eos()) {
            $index = $this->pos();
            $tok = null;
            $m = null;
            $escaped = false;

            if (!$this->cleanExit) {
                try {
                    $tok = $this->resume();
                } catch (Exception $e) {
                    if (LUMINOUS_DEBUG) {
                        throw $e;
                    } else {
                        $this->cleanExit = true;
                        continue;
                    }
                }
            } elseif ($this->childState !== null && $this->childState[1] < $this->pos()) {
                $this->scanChild($this->childState[0]);
                continue;
            } elseif (($rule = $this->nextMatch()) !== null) {
                $tok = $rule[0];
                if ($rule[1] > $index) {
                    $this->record(substr($this->string(), $index, $rule[1] - $index), null);
                }
            } else {
                $this->record(substr($this->string(), $index), null);
                $this->cleanExit = true;
                $this->interrupt = false;
                $this->terminate();
                break;
            }

            if ($tok === 'SLASH') {
                if ($this->isOperand()) {
                    $tok = 'REGEX';
                    $this->unscan();
                    assert($this->peek() === '/');
                    $m = $this->scan($this->regexRegex);
                    if ($m === null) {
                        assert(0);
                        $m = $this->rest();
                        $this->terminate();
                    }
                } else {
                    $tok = 'OPERATOR';
                }
            } elseif ($tok === 'OPERATOR' && $this->match() === '<') {
                if ($this->isOperand()) {
                    $this->unscan();
                    $this->scanChild('xml');
                    continue;
                }
            } elseif ($tok === 'STOP_SERVER') {
                $this->interrupt = true;
                $this->unscan();
                break;
            } elseif ($tok === 'STOP_SCRIPT') {
                $this->unscan();
                break;
            }
            if ($m === null) {
                $m = $this->match();
            }

            if ($this->serverBreak($tok)) {
                break;
            }

            if ($tok === 'COMMENT_SL' && $this->scriptBreak($tok)) {
                break;
            }
            assert($this->pos() > $index);

            $tag = $tok;

            $this->record($m, $tag, $escaped);
        }
    }
}
