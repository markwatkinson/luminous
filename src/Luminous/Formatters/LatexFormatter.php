<?php
/** @cond ALL */

namespace Luminous\Formatters;

use Luminous\Utils\ColorUtils;
use Luminous\Utils\CssParser;

/**
 * LaTeX output formatter for Luminous.
 *
 * @since  0.5.4
 */
class LatexFormatter extends Formatter
{
    private $css = null;

    public function setTheme($theme)
    {
        $this->css = new CssParser();
        $this->css->convert($theme);
    }

    protected function linkify($src)
    {
        return $src;
    }

    /**
     * Defines all the styling commands, these are obtained from the css parser
     */
    public function defineStyleCommands()
    {
        if ($this->css === null) {
            throw new Exception('LaTeX formatter has not been set a theme');
        }
        $cmds = array();
        $round = function ($n) {
            return round($n, 2);
        };
        foreach ($this->css->rules() as $name => $properties) {
            if (!preg_match('/^\w+$/', $name)) {
                continue;
            }
            $cmd = "{#1}";
            if ($this->css->value($name, 'bold', false) === true) {
                $cmd = "{\\textbf$cmd}";
            }
            if ($this->css->value($name, 'italic', false) === true) {
                $cmd = "{\\emph$cmd}";
            }
            if (($col = $this->css->value($name, 'color', null)) !== null) {
                if (preg_match('/^#[a-f0-9]{6}$/i', $col)) {
                    $rgb = ColorUtils::normalizeRgb(ColorUtils::hex2rgb($col), true);
                    $rgb = array_map($round, $rgb);
                    $colStr = "{$rgb[0]}, {$rgb[1]}, $rgb[2]";
                    $cmd = "{\\textcolor[rgb]{{$colStr}}$cmd}";
                }
            }
            $name = str_replace('_', '', $name);
            $name = strtoupper($name);
            $cmds[] = "\\newcommand{\\lms{$name}}[1]$cmd";
        }

        if ($this->lineNumbers && ($col = $this->css->value('code', 'color', null)) !== null) {
            if (preg_match('/^#[a-f0-9]{6}$/i', $col)) {
                $rgb = ColorUtils::normalizeRgb(ColorUtils::hex2rgb($col), true);
                $rgb = array_map($round, $rgb);
                $colStr = "{$rgb[0]}, {$rgb[1]}, $rgb[2]";
                $cmd = "\\renewcommand{\\theFancyVerbLine}{\\textcolor[rgb]{{$colStr}}{\arabic{FancyVerbLine}}}";
                $cmds[] = $cmd;
            }
        }
        return implode("\n", $cmds);
    }

    public function getBackgroundColour()
    {
        if (($col = $this->css->value('code', 'bgcolor', null)) !== null) {
            if (preg_match('/^#[a-f0-9]{6}$/i', $col)) {
                $rgb = ColorUtils::normalizeRgb(ColorUtils::hex2rgb($col), true);
            }
            array_map(function ($n) {
                return round($n, 2);
            }, $rgb);
            $colStr = "{$rgb[0]}, {$rgb[1]}, $rgb[2]";
            return "\\pagecolor[rgb]{{$colStr}}";
        }
        return "";
    }

    public function format($str)
    {
        $out = '';

        $verbcmd = "\\begin{Verbatim}[commandchars=\\\\\\{\}";
        if ($this->lineNumbers) {
            $verbcmd .= ",numbers=left,firstnumber=1,stepnumber=1";
        }
        $verbcmd .= ']';
        // define the preamble
        $out .= <<<EOF
\documentclass{article}
\usepackage{fullpage}
\usepackage{color}
\usepackage{fancyvrb}
\begin{document}
{$this->defineStyleCommands()}
{$this->getBackgroundColour()}

$verbcmd

EOF;

        $s = '';
        $str = preg_replace('%<([^/>]+)>\s*</\\1>%', '', $str);
        $str = str_replace("\t", '  ', $str);

        $lines = explode("\n", $str);

        if ($this->wrapLength > 0) {
            $str = '';
            foreach ($lines as $i => $l) {
                $this->wrapLine($l, $this->wrapLength);
                $str .= $l;
            }
        }

        $str_ = preg_split('/(<[^>]+>)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $f1 = function ($matches) {
            return "\\lms" . str_replace("_", "", $matches[1]) . "{";
        };
        $f2 = function ($matches) {
            if ($matches[0][0] == "\\") {
                return "{\\textbackslash}";
            }
            return "\\" . $matches[0];
        };

        foreach ($str_ as $s_) {
            if ($s_[0] === '<') {
                $s_ = preg_replace('%</[^>]+>%', '}', $s_);
                $s_ = preg_replace_callback('%<([^>]+)>%', $f1, $s_);
            } else {
                $s_ = str_replace('&gt;', '>', $s_);
                $s_ = str_replace('&lt;', '<', $s_);
                $s_ = str_replace('&amp;', '&', $s_);
                $s_ = preg_replace_callback('/[#{}_$\\\&]|&(?=amp;)/', $f2, $s_);
            }

            $s .= $s_;
        }

        unset($str_);

        $s = "\\lmsCODE{" . $s . '}';

        /* XXX:
         * hack alert: leaving newline literals (\n) inside arguments seems to
         * leave them being totally ignored. This is a problem for wrapping.
         *
         * the current solution is to close all open lms commands before the
         * newline then reopen them afterwards.
         */

        $stack = array();
        $pieces = preg_split(
            '/(\\\lms[^\{]+\{|(?<!\\\)(\\\\\\\\)*[\{\}])/',
            $s,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        // NOTE: p being a reference is probably going to necessitate a lot of
        // copying to pass through all these preg_* and str* calls.
        // consider rewriting.
        foreach ($pieces as $k => &$p) {
            if (preg_match('/^\\\lms/', $p)) {
                $stack[] = "" . $p;
            } elseif (preg_match('/^(\\\\\\\\)*\}/', $p)) {
                array_pop($stack);
            } elseif (preg_match('/^(\\\\\\\\)*{/', $p)) {
                $stack [] = $p;
            } elseif (strpos($p, "\n") !== false) {
                $before = "";
                $after = "";
                foreach ($stack as $st_) {
                    $before .= $st_;
                    $after .= '}';
                }
                $p = str_replace("\n", "$after\n$before", $p);
            }
        }

        $s = implode('', $pieces);

        $out .= $s;
        $out .= <<<EOF
\end{Verbatim}
\end{document}
EOF;
        return $out;
    }
}

/** @endcond */
