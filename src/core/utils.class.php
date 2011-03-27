<?php
// Essentially a namespace
class LuminousUtils {

  static function balance_delimiter($delimiter) {
    switch($delimiter) {
    case '(' : return ')';
    case '{' : return '}';
    case '[' : return ']';
    case '<' : return '>';
    default: return $delimiter;
    }
  }

  static function escape_string($string) {
    return htmlspecialchars($string, ENT_NOQUOTES);
  }
  
  static function escape_token($token) {
    if (!$token[2]) {
      $token[1] = htmlspecialchars($token[1], ENT_NOQUOTES);
      $token[2] = true;
    }
    return $token;
  }

  static function tag_block($type, $block, $split_multiline=true) {
    if ($type === null) return $block;
    $open = '<' . $type . '>';
    $close = '</' . $type . '>';
    if ($split_multiline)
      return $open . str_replace("\n", $close . "\n" . $open, $block) .
          $close;
    else
      return $open . $block . $close;
  }

  static function pcre_error_decode($errcode) {
    switch ($errcode) {
      case PREG_NO_ERROR:
        return 'PREG_NO_ERROR';
      case PREG_INTERNAL_ERROR:
        return 'PREG_INTERNAL_ERROR';
      case PREG_BACKTRACK_LIMIT_ERROR:
        return 'PREG_BACKTRACK_LIMIT_ERROR';
      case PREG_RECURSION_LIMIT_ERROR:
        return 'PREG_RECURSION_LIMIT_ERROR';
      case PREG_BAD_UTF8_ERROR:
        return 'PREG_BAD_UTF8_ERROR';
      case PREG_BAD_UTF8_OFFSET_ERROR:
        return 'PREG_BAD_UTF8_OFFSET_ERROR';
      default:
        return "Unknown error code `$errcode'";
    }
  }
}
