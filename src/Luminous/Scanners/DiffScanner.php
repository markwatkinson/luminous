<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\Scanners\Scanner;

/*
 * Diff is a strange one because we could just highlight the lines and be done
 * with it, but we are actually going to try to highlight the source code AND
 * the diff format
 *
 * As such, we handle formatting and tagging inside the scanner.
 */
class DiffScanner extends Scanner
{
    public $patterns = array();
    // pretty mode uses language sub-scanners
    // to try to highlight the embedded code
    public $prettyMode = false;

    /* TODO: plug this into the language code selector in the old EasyAPI
     * when we port it across
     * This function is just a placeholder and will be implemented properly
     * later.
     */
    public function getChildScanner($filename)
    {
        // HACK - pretty mode should be reflected elsewhere than here.
        if (!$this->prettyMode) {
            return null;
        }
        // $luminous_ is a singleton from the main calling API. It may or may not
        // exist here, but if it does, we're going to use it.
        global $luminous_;
        if (!isset($luminous_)) {
            return null;
        }

        $spos = strrpos($filename, '.');
        if ($spos === false) {
            return null;
        }
        $ext = substr($filename, $spos + 1);
        $s = $luminous_->scanners->getScanner(strtolower($ext));
        // we actually only want the classname, not an instance.
        if ($s === null) {
            return null;
        }
        return get_class($s);
    }

    public function string($string = null)
    {
        if ($string !== null) {
            if (preg_match('/^[><]/m', $string)) {
                // normal rules
                $this->patterns['range'] = '/\d+.*/';
                $this->patterns['codeblock'] = "/(^([<> ]).*(\n)?)+/m";
            } elseif (preg_match('/^\*{3}/m', $string)) {
                // context
                $this->patterns['range'] = "/([\-\*]{3})[ \t]+\d+,\d+[ \t]+\\1.*/";
                $this->patterns['codeblock'] = "/(^([!+ ]).*(\n)?)+/m";
            } else {
                // unified
                $this->patterns['range'] = "/@@.*/";
                $this->patterns['codeblock'] = "/(^([+\- ]).*(\n)?)+/m";
            }
        }
        return parent::string($string);
    }

    public function main()
    {
        // we're aiming to handle context, unified and normal diff all at once here
        // because it doesn't really seem that hard.
        $child = null;
        $lastIndex = -1;
        while (!$this->eos()) {
            $index = $this->pos();
            assert($index > $lastIndex);
            $lastIndex = $index;

            assert($this->bol());

            $tok = null;
            if ($this->scan('/diff\s.*$/m') !== null) {
                $tok = 'KEYWORD';
            } elseif ($this->scan($this->patterns['range']) !== null) {
                // normal, context and unified ranges
                $tok = 'DIFF_RANGE';
            } elseif ($this->scan("/-{3}[ \t]*$/m")) {
                $tok = null;
            } elseif ($this->scan('/(?:\**|=*|\w.*)$/m') !== null) {
                $tok = 'KEYWORD';
            } elseif ($this->scan("@[+\-\*]{3}(\s+([^\s]*)([ \t]|$))?.*@m") !== null) {
                // this is a header line which may contain a file path. If it does,
                // update the child scanner according to its extension.
                $m = $this->matchGroups();
                // unified uses +++, context uses *
                if ($m[0][0] === '+' || $m[0][0] === '*') {
                    $tok = 'DIFF_HEADER_NEW';
                } else {
                    $tok = 'DIFF_HEADER_OLD';
                }
                if (isset($m[2])) {
                    $filename = preg_replace('@.*\\\\/@', '', $m[2]);
                    $child = self::getChildScanner($filename);
                }
            } elseif ($this->scan('/\\\\.*/') !== null) {
                $tok = null;
            } elseif ($this->scan($this->patterns['codeblock']) !== null) {
                // this is actual source code.
                // we're going to format this here.
                // we're going to extract the block, and try to re-assemble it as
                // verbatim code, then highlight it via a child scanner, then split up
                // the lines, re-apply the necessary prefixes (e.g. + or -) to them,
                // and store them as being a DIFF_ token.
                // we have to do it like this, rather than line by line, otherwise
                // multiline tokens aren't going to work properly. There's stilla  risk
                // that the diff will be fragmented such the child scanner gets it
                // wrong but that can't be helped.

                // TODO restructure this so the complicated bits aren't done if there's
                // no child scanner to pass it down to

                $block = $this->match();
                if (!strlen($block)) {
                    assert(0);
                }

                $lines = explode("\n", $block);
                $verbatim = array();
                $verbatim_ = '';
                $types = array();
                $prefixes = array();
                foreach ($lines as $l) {
                    if (!strlen($l) || $l[0] === ' ') {
                        $types[]= 'DIFF_UNCHANGED';
                    } elseif ($l[0] === '+' || $l[0] === '>') {
                        $types[] = 'DIFF_NEW';
                    } elseif ($l[0] === '!' || $l[0] === '<' || $l[0] === '-')
                        $types[] = 'DIFF_OLD';
                    else {
                        assert(0);
                    }
                    $prefixes[] = (isset($l[0])) ? $l[0] : '';
                    $verbatim_[] = substr($l, 1);
                }
                $verbatim = implode("\n", $verbatim_);
                $escaped = false;
                $tagged;
                if ($child !== null) {
                    $c = new $child;
                    $c->init();
                    $c->string($verbatim);
                    $c->main();
                    $tagged = $c->tagged();
                    $escaped = true;
                } else {
                    $tagged = $verbatim;
                }
                $exp = explode("\n", $tagged);
                assert(count($exp) === count($prefixes));
                foreach ($exp as $i => $v) {
                    $t = $types[$i];
                    // if the sub-scanner escaped the line, we also need to escape the
                    // prefix for consistency
                    $prefix = $prefixes[$i];
                    if ($escaped) {
                        $prefix = Utils::escapeString($prefix);
                    }
                    $text = $prefix . $v;
                    $this->record($text, $t, $escaped);
                    if ($i < count($exp) - 1) {
                        $this->record("\n", null);
                    }
                }
                if ($this->eol()) {
                    $this->record($this->get(), null);
                }
                continue;
            } else {
                $this->scan('/.*/');
            }

            // previous else clause can capture empty strings
            if ($this->match() !== '') {
                $this->record($this->match(), $tok);
            }

            assert($this->eol());
            // consume newline
            if (!$this->eos()) {
                $this->record($this->get(), null);
            }
        }
    }

    public static function guessLanguage($src, $info)
    {
        // diff isn't too hard. We check for 'index' and a few other things
        $p = 0.0;
        if (preg_match("/^-{3}.*+[\n\r]++\\+{3}/m", $src)) {
            $p = 0.25;
        }
        if (preg_match('/^@@.*@@/m', $src)) {
            $p += 0.25;
        }
        if (preg_match('/^(index|diff)\\b/m', $src)) {
            $p += 0.10;
        }

        // finally we look for the diff markers at the line starts
        // we're going to use the remaining 40% of the probability as so:
        // We'll say a perfect match for diff has
        // 10%+ of its lines starting with the +/- markers (</> or +/! for
        // context/original format), and we'll scale real proportion
        // to fill up the remaining 0.4
        $c =  preg_match_all('/^[<>+\\-!]\s/m', $src, $m);
        $numLines = $info['num_lines'];
        if ($numLines > 0) {
            $proportion = $c / $numLines;
            $proportion = min(0.1, $proportion);
            $p += 0.4 * ($proportion * 10);
        }
        return $p;
    }
}
