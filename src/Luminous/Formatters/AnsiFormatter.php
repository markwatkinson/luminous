<?php
/** @cond ALL */

namespace Luminous\Formatters;

use Luminous\Utils\CssParser;

/**
 * ANSI output formatter for Luminous.
 */
class AnsiFormatter extends Formatter
{
    private $css = null;

    public function setTheme($theme)
    {
        $this->css = new CssParser();
        $this->css->convert($theme);
    }

    /**
     * Converts a hexadecimal string in the form #ABCDEF to an RGB array
     */
    public static function hex2rgb($hex)
    {
        $x = hexdec(substr($hex, 1));
        $b = $x % 256;
        $g = ($x >> 8) % 256;
        $r = ($x >> 16) % 256;

        $rgb = array($r, $g, $b);
        return $rgb;
    }

    protected function linkify($src)
    {
        return $src;
    }

    public function insertEscapeSequences($matches)
    {
        $match = strtolower($matches[1]);
        $rules = $this->css->rules();
        $escSeq = '';

        if (isset($rules[$match])) {
            if ($this->css->value($match, 'bold', false) === true) {
                $escSeq .= "\033[1m";
            }
            if ($this->css->value($match, 'italic', false) === true) {
                $escSeq .= "\033[3m";
            }
            if ($this->css->value($match, 'underline', false) === true) {
                $escSeq .= "\033[4m";
            }
            if ($this->css->value($match, 'strikethrough', false) === true) {
                $escSeq .= "\033[9m";
            }
            if (($color = $this->css->value($match, 'color', null)) !== null) {
                if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                    $escSeq .= "\033[38;2;" . implode(';', self::hex2rgb($color)) . 'm';
                }
            }
            if (($bgColor = $this->css->value($match, 'bgcolor', null)) !== null) {
                if (preg_match('/^#[a-f0-9]{6}$/i', $bgColor)) {
                    $escSeq .= "\033[48;2;" . implode(';', self::hex2rgb($bgColor)) . 'm';
                }
            }
        }
        return $escSeq;
    }

    public function format($str)
    {
        if ($this->css === null) {
            throw new Exception('ANSI formatter has not been set a theme');
        }
        $out = '';

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

        foreach ($str_ as $s_) {
            if ($s_[0] === '<') {
                $s_ = preg_replace('%</[^>]+>%', "\033[0m", $s_);
                $s_ = preg_replace_callback('%<([^>]+)>%', array($this, 'insertEscapeSequences'), $s_);
            } else {
                $s_ = str_replace('&gt;', '>', $s_);
                $s_ = str_replace('&lt;', '<', $s_);
                $s_ = str_replace('&amp;', '&', $s_);
            }
            $s .= $s_;
        }
        unset($str_);

        $out .= $s;
        return $out;
    }
}
/** @endcond */
