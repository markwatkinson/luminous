<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\JavaKeywords;

/*
 * Groovy is pretty much a cross between Python and Java.
 * It inherits all of Java's stuff
 * http://groovy.codehaus.org/jsr/spec/Chapter03Lexical.html
 */
class GroovyScanner extends SimpleScanner
{
    public $interpolation = false;
    protected $braceStack = 0;

    public function regexOverride($match)
    {
        assert($this->peek() === '/');
        assert($match === array(0 => '/'));
        $regex = false;

        $i = count($this->tokens);
        while ($i--) {
            list($tok, $contents) = $this->tokens[$i];
            if ($tok === 'COMMENT') {
                continue;
            } elseif ($tok === 'OPERATOR') {
                $regex = true;
            } elseif ($tok !== null) {
                $regex = false;
            } else {
                $t = rtrim($contents);
                if ($t === '') {
                    continue;
                }
                $c = $t[strlen($t) - 1];
                $regex = ($c === '(' || $c === '[' || $c === '{');
            }
            break;
        }

        if (!$regex) {
            $this->record($this->get(), 'OPERATOR');
        } else {
            $m = $this->scan('@ / (?: [^\\\\/]+ | \\\\. )* (?: /|$) @sx');
            assert($m !== null);
            $this->record($m, 'REGEX');
        }
    }

    // string interpolation is complex and it nests, so we do that in here
    public function interpString($m)
    {
        // this should only be called for doubly quoted strings
        // and triple-double quotes
        //
        // interpolation is betwee ${ ... }
        $patterns = array('interp' => '/(?<!\\$)\\$\\{/');
        $start = $this->pos();
        if (preg_match('/^"""/', $m[0])) {
            $patterns['term'] = '/"""/';
            $this->posShift(3);
        } else {
            assert(preg_match('/^"/', $m[0]));
            $patterns['term'] = '/"/';
            $this->posShift(1);
        }
        while (1) {
            $p = $this->pos();
            list($name, $index, $matches) = $this->getNextNamed($patterns);
            if ($name === null) {
                // no matches, terminate
                $this->record(substr($this->string(), $start), 'STRING');
                $this->terminate();
                break;
            } elseif ($name === 'term') {
                // end of the string
                $range = $index + strlen($matches[0]);
                $this->record(substr($this->string(), $start, $range - $start), 'STRING');
                $this->pos($range);
                break;
            } else {
                // interpolation, handle this with a subscanner
                $this->record(substr($this->string(), $start, $index - $start), 'STRING');
                $this->record($matches[0], 'DELIMITER');
                $subscanner = new GroovyScanner($this->string());
                $subscanner->interpolation = true;
                $subscanner->init();
                $subscanner->pos($index + strlen($matches[0]));
                $subscanner->main();

                $tagged = $subscanner->tagged();
                $this->record($tagged, 'INTERPOLATION', true);
                $this->pos($subscanner->pos());
                if ($this->scan('/\\}/')) {
                    $this->record($this->match(), 'DELIMITER');
                }
                $start = $this->pos();
            }
            assert($p < $this->pos());
        }
    }

    // brace override halts scanning if the stack is empty and we hit a '}',
    // this is for interpolated code, the top-level scanner doesn't bind to this
    public function brace($m)
    {
        if ($m[0] === '{') {
            $this->braceStack++;
        } elseif ($m[0] === '}') {
            if ($this->braceStack <= 0) {
                return true;
            }
            $this->braceStack--;
        } else {
            assert(0);
        }
        $this->record($m[0], null);
        $this->posShift(strlen($m[0]));
    }

    public function init()
    {
        $this->addIdentifierMapping('KEYWORD', JavaKeywords::KEYWORDS);
        $this->addIdentifierMapping('TYPE', JavaKeywords::TYPES);
        $this->addIdentifierMapping('KEYWORD', array('any', 'as', 'def', 'in', 'with', 'do', 'strictfp', 'println'));

        // C+P from python
        // so it turns out this template isn't quite as readable as I hoped, but
        // it's a triple string, e.g:
        //  "{3} (?: [^"\\]+ | ""[^"\\]+ | "[^"\\]+  | \\.)* (?: "{3}|$)
        $tripleStrTemplate =
            '%1$s{3} (?> [^%1$s\\\\]+ | %1$s%1$s[^%1$s\\\\]+ | %1$s[^%1$s\\\\]+ | \\\\. )* (?: %1$s{3}|$)';
        $strTemplate = '%1$s (?> [^%1$s\\\\]+ | \\\\. )* (?: %1$s|$)';
        $tripleDstr = sprintf($tripleStrTemplate, '"');
        $tripleSstr = sprintf($tripleStrTemplate, "'");

        $this->addPattern('COMMENT', '/^#!.*/');
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);
        $this->addPattern('INTERP_STRING', "/$tripleDstr/sx");
        $this->addPattern('STRING', "/$tripleSstr/xs");
        $this->addPattern('INTERP_STRING', TokenPresets::$DOUBLE_STR);
        $this->overrides['INTERP_STRING'] = array($this, 'interpString');
        // differs from java:
        $this->addPattern('STRING', TokenPresets::$SINGLE_STR);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('IDENT', '/[a-zA-Z_]\w*/');
        $this->addPattern('OPERATOR', '/[~!%^&*\-=+:?|<>]+/');
        $this->addPattern('SLASH', '%/%');
        $this->overrides['SLASH'] = array($this, 'regexOverride');
        if ($this->interpolation) {
            $this->addPattern('BRACE', '/[{}]/');
            $this->overrides['BRACE'] = array($this, 'brace');
        }
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        if (preg_match('/\\bdef\s+\w+\s*=/', $src)) {
            $p += 0.04;
        }
        if (preg_match('/println\s+[\'"\w]/', $src)) {
            $p += 0.03;
        }
        // Flawed check for interpolation, might match after a string
        // terminator
        if (preg_match("/\"[^\"\n\r]*\\$\\{/", $src)) {
            $p += 0.05;
        }
        // regex literal ~/regex/
        if (preg_match('%~/%', $src)) {
            $p += 0.05;
        }
        if (preg_match('/^import\s+groovy/m', $src)) {
            $p += 0.2;
        }
        return $p;
    }
}
