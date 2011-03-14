<?php



// Poor man's namespace.
class LuminousFilters {
  
  
  private static function doxygen_arg_length($command) {
    switch(strtolower($command)) {
      case "addtogroup":
      case "category":
      case "class":
      case "def":
      case "defgroup":
      case "dir":
      case "enum":
      case "example":
      case "extends":
      case "file":
      case "headerfile":
      case "implements":
      case "ingroup":
      case "interface":
      case "namespace":
      case "memberof":
      case "package":
      case "page":
      case "relates":
      case "relatesalso":
      case "weakgroup":    
      case "cond":
      case "elseif":
      case "exception":
      case "if":
      case "ifnot":
      case "par":
      case "param":
      case "tparam":
      case "retval":
      case "throw":
      case "throws":
      case "xrefitem":
      case 'see':
      case 'since':
        return 1;
      default: return 0;
    }
  }
  
  
  static function doxygenize_cb($matches) {
    $lead = $matches[1];
    $tag_char = $matches[2];
    $tag = $matches[3];
    
    $line = "";
    if (isset($matches[4]))
      $line = $matches[4];
    
    $len = -1;
    // JSDoc-like  
    $l_ = ltrim($line);
    if (isset($l_[0]) && $l_[0] === '{') {
      $line = preg_replace('/({[^}]*})/', "<DOCPROPERTY>$1</DOCPROPERTY>", $line);
      return "$lead<DOCTAG>$tag_char$tag</DOCTAG>$line";
    }    
    else      
      $len = self::doxygen_arg_length($tag);
    
    if($len === 0)
      return "$lead<DOCTAG>$tag_char$tag</DOCTAG>$line";    
    else {
      $l = explode(' ', $line);
      $start = "$lead<DOCTAG>$tag_char$tag</DOCTAG><DOCPROPERTY>";
      
      $j = 0;
      $c = count($l);
      for($i=0; $j<$len && $i<$c; $i++)
      {      
        $s = $l[$i];
        $start .= $s . ' ';
        unset($l[$i]);
        if (trim($s) != '')
          $j++;
      }
      $start .= "</DOCPROPERTY>";
      $start .= implode(' ', $l);    
      return $start;
    }
  }
  
  static function doxygenize($token) {
    $token = LuminousUtils::escape_token($token);    
    $token[1] = preg_replace_callback("/(^(?>[\/\*#\s]*))([\@\\\])([^\s]*)([ \t]+.*?)?$/m",
        array('LuminousFilters', 'doxygenize_cb'),   $token[1]);    
    return $token;
    
  }
  
  static function generic_doc_comment($token) {
    if (preg_match('/^.(.)\\1(?!\\1)/', $token[1])) {
      $token[0] = 'DOCCOMMENT';
      $token = self::doxygenize($token);
    }
    return $token;    
    
  }
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
      |\d{1,3}           # octal
      |x[a-fA-F0-9]{2}   # hex
      |.                 # whatever
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
  
  
 