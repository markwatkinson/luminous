<?php

class LuminousUtils {
  
  static function escape_string($string) {
  }
  static function escape_token($token) {
    if (!$token[2]) {
      $token[1] = htmlspecialchars($token[1], ENT_NOQUOTES);
      $token[2] = true;
    }
    return $token;
  }
}
