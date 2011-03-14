<?php



// Poor man's namespace.
class LuminousFilters {
  
  static function comment_note($token) {    
      $token = LuminousUtils::escape_token($token);
      $token[1] = preg_replace('/\\b(?:NOTE|XXX|FIXME|TODO|HACK|BUG):?/',
        '<COMMENT_NOTE>$0</COMMENT_NOTE>', $token[1]);
      return $token;
  }
  
  static function string($token) {
    $token = LuminousUtils::escape_token($token);
    $token[1] = preg_replace('/
    \\\\
    (?:
      (?:u[a-f0-9]{4,8}) # unicode
      |[a-fA-F\d]{1,3}   #hex or octal
      |.                 #whatever
    )
    /xi', '<ESC>$0</ESC>', $token[1]);
    return $token;
  }
  
  static function doc_comment($token) {
    
  }
}
  
  
 