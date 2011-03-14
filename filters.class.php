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
  
  static function pcre($token) {
    $token = self::string($token);
    $str = &$token[1];
    $flags = array();
    if (preg_match("/[[:alpha:]]+$/", $str, $matches)){
      $m = $matches[0];
      $flags = str_split($m);
      $str = preg_replace("/(?<![[:alnum:]\s])[[:alpha:]]+$/", 
        "<KEYWORD>$0</KEYWORD>", $str);
    }
    $str = preg_replace("/((?<!\\\)[\*\+\.|])|((?<![\(\\\])\?)/",
                          "<REGEX_OPERATOR>$0</REGEX_OPERATOR>", $str);  
    $str = preg_replace("/(?<=\()\?(?:(?:[a-zA-Z:!|=])|(?:(?:&lt;)[=!]))/", 
      "<REGEX_SUBPATTERN>$0</REGEX_SUBPATTERN>",  $str);
    $str = preg_replace("/(?<!\\\)[\(\)]/", 
      "<REGEX_SUBPATTERN_MARKER>$0</REGEX_SUBPATTERN_MARKER>", $str);
    $str = preg_replace("/(?<!\\\)[\[\]]/", 
      "<REGEX_CLASS_MARKER>$0</REGEX_CLASS_MARKER>",  $str);    
    $str = preg_replace("/(?<!\\\)
      \{
        (
          ((?>\d+)(,(?>\d+)?)?)
          |
          (,(?>\d+))
        )
      \}/x", "<REGEX_REPEAT_MARKER>$0</REGEX_REPEAT_MARKER>",  $str);
      
    // extended regex: # signifies a comment
    if (in_array('x', $flags))
      $str = preg_replace('/(?<!\\\)#.*$/m', '<COMMENT>$0</COMMENT>',
        $str); 
    return $token;
  }
  
  
  static function doc_comment($token) {
    
  }
}
  
  
 