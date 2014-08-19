<?php
/// @cond ALL
require_once(dirname(__FILE__) . '/../utils/cssparser.class.php');

/**
 * ANSI output formatter for Luminous.
 */
class LuminousFormatterAnsi extends LuminousFormatter {

  private $css = null;

  function __construct() { }

  function set_theme($theme) {
    $this->css = new LuminousCSSParser();
    $this->css->convert($theme);
  }

  /// Converts a hexadecimal string in the form #ABCDEF to an RGB array
  static function hex2rgb($hex) {
    $x = hexdec(substr($hex, 1));
    $b = $x % 256;
    $g = ($x >> 8) % 256;
    $r = ($x >> 16) % 256;

    $rgb = array($r, $g, $b);
    return $rgb;
  }

  protected function linkify($src) {
    return $src;
  }

  function insert_escape_sequences($matches) {
    $match = strtolower($matches[1]);
    $rules = $this->css->rules();
    $esc_seq = '';

    if (isset($rules[$match])) {
      if ($this->css->value($match, 'bold', false) === true)
        $esc_seq .= "\033[1m";
      if ($this->css->value($match, 'italic', false) === true)
        $esc_seq .= "\033[3m";
      if ($this->css->value($match, 'underline', false) === true)
        $esc_seq .= "\033[4m";
      if ($this->css->value($match, 'strikethrough', false) === true)
        $esc_seq .= "\033[9m";
      if (($color = $this->css->value($match, 'color', null)) !== null) {
        if (preg_match('/^#[a-f0-9]{6}$/i', $color))  {
          $esc_seq .= "\033[38;2;" . implode(';', self::hex2rgb($color)) . 'm';
        }
      }
      if (($bgcolor = $this->css->value($match, 'bgcolor', null)) !== null) {
        if (preg_match('/^#[a-f0-9]{6}$/i', $bgcolor))  {
          $esc_seq .= "\033[48;2;" . implode(';', self::hex2rgb($bgcolor)) . 'm';
        }
      }
    }

    return $esc_seq;
  }

  function format($str) {
    if ($this->css === null)
      throw new Exception('ANSI formatter has not been set a theme');
    $out = '';

    $s = '';
    $str = preg_replace('%<([^/>]+)>\s*</\\1>%', '', $str);
    $str = str_replace("\t", '  ', $str);

    $lines = explode("\n", $str);

    if ($this->wrap_length > 0)  {
      $str = '';
      foreach($lines as $i=>$l) {
        $this->wrap_line($l, $this->wrap_length);
        $str .= $l;
      }
    }

    $str_ = preg_split('/(<[^>]+>)/', $str, -1,
      PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

    foreach($str_ as $s_) {
      if ($s_[0] === '<') {
        $s_ = preg_replace('%</[^>]+>%', "\033[0m", $s_);
        $s_ = preg_replace_callback('%<([^>]+)>%', array($this, 'insert_escape_sequences'), $s_);
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
/// @endcond
