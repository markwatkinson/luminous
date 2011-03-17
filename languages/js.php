<?php


class JSScanner extends LuminousEmbeddedWebScript {
  
  // TODO clean up redunancy here and in constructor
  public $server_tags = '<?';
  public $script_tags = '</script>';
    
  // logs a persistent token stream so that we can lookbehind to figure out
  // operators vs regexes.
  private $tokens_ = array(); 
  
  function __construct($src=null) {
    
    $this->rule_tag_map = array(
      'COMMENT_SL' => 'COMMENT',
      'SSTRING' => 'STRING',
      'DSTRING' => 'STRING'
    );
    $this->dirty_exit_recovery = array(
      'COMMENT_SL' => '/.*/',
      'COMMENT' => '%.*(\*/|$)%s',
      'SSTRING' => "/(?:[^\\\\']+|\\\\.)*('|$)/",
      'DSTRING' => '/(?:[^\\\\"]+|\\\\.)*("|$)/'
    );
    
    parent::__construct($src);
    $this->add_identifier_mapping('KEYWORD', array('break', 'case', 'catch', 
      'comment', 'continue', 'do', 'default', 'delete', 'else', 'export', 
      'for', 'function', 'if', 'import', 'in', 'instanceof', 'label', 'new', 
      'null', 'return', 'switch', 'throw', 'try', 'typeof', 'var', 'void', 
      'while', 'with',
      'true', 'false', 'this'
      ));
    $this->add_identifier_mapping('FUNCTION', array('$', 'alert', 'confirm', 
      'clearTimeout', 'clearInterval',
      'encodeURI', 'encodeURIComponent', 'eval', 'isFinite', 'isNaN', 
      'parseInt', 'parseFloat', 'prompt',
      'setTimeout', 'setInterval',      
      'decodeURI', 'decodeURIComponent', 'jQuery'));
      
    $this->add_identifier_mapping('TYPE', array('Array', 'Boolean', 'Date', 
      'Error', 'EvalError', 'Infinity', 'Image', 'Math', 'NaN', 'Number', 
      'Object', 'Option', 'RangeError', 'ReferenceError', 'RegExp', 'String',
      'SyntaxError', 'TypeError', 'URIError', 
      
      'document',
      'undefined', 'window'));
  }
  
  static function is_regex($tokens) {
    $i = count($tokens);
    while ($i--) {
      $t = $tokens[$i];
      if ($t === 'COMMENT' || $t === 'COMMENT_SL') continue;
      elseif ($t === 'OPENER' || $t === 'OPERATOR') {
        return true;
      }
      return false;
    }
    return true;
  }
  
  function init() {
    
    $op_pattern = '[=!+*%\-&^|~:?\;,.>';
    if (!($this->embedded_server || $this->embedded_html)) 
      $op_pattern .= '<]+';
    else {
      // build an alternation with a < followed by a lookahead
      $op_pattern .= ']|<(?![';
      if ($this->embedded_server) $op_pattern .= '?';
      if ($this->embedded_html) $op_pattern .= '/';
      $op_pattern .= '])'; // closes lookahead
      $op_pattern = "(?:$op_pattern)+";
    }
    $op_pattern = "@$op_pattern@";
    
    $this->add_pattern('IDENT', '/[a-zA-Z_$]+[_$\w]*/'); 
    // NOTE: slash is a special case, and </ may be a script close
    $this->add_pattern('OPERATOR', $op_pattern);
    // we care about openers for figuring out where regular expressions are
    $this->add_pattern('OPENER', '/[\[\{\(]+/');
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('SSTRING', LuminousTokenPresets::$SINGLE_STR);
    $this->add_pattern('DSTRING', LuminousTokenPresets::$DOUBLE_STR);    
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    $this->add_pattern('COMMENT_SL', LuminousTokenPresets::$C_COMMENT_SL);
    // special case
    $this->add_pattern('SLASH', '%/%');
    
    $stop_patterns = array();
    if ($this->embedded_server) $stop_patterns[] = "(?P<SERVER><\\?)";
    if ($this->embedded_html) $stop_patterns[] = "(?P<SCRIPT_TERM></script>)";
    if (!empty($stop_patterns)) {
      $this->stop_pattern = '%' . join('|', $stop_patterns) . '%i';
      $this->add_pattern('STOP', $this->stop_pattern);
    }
    
  }
  
  function main() {
    $this->start();
    
    while (!$this->eos()) {
      $index = $this->pos();
      $tok = null;
      $m = null;
      $escaped = false;
      
      if (!$this->clean_exit) {
        $tok = $this->resume();
      }
      elseif (($rule = $this->next_match()) !== null) {
        $tok = $rule[0];
        if ($rule[1] > $index) {
          $this->record(substr($this->string(), $index, $rule[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        $this->clean_exit = true;
        break;
      }
      
     if ($tok === 'SLASH') {
      if (self::is_regex($this->tokens_)) {
        $this->unscan();
        assert ($this->peek() === '/');
        $tok = 'REGEX';
        $m = $this->scan('% / (?: [^/\\\\]+ | \\\\.)* (?:/[ioxgm]*|$)%sx');
        assert ($m !== null) or die();
        } else {
          $tok = 'OPERATOR';
        }
      }
      elseif ($tok === 'STOP') {
        $this->unscan();
        break;
      }
      if ($m === null) $m = $this->match();
      
      if ($this->server_break($tok)) { break; }
      
      if (($tok === 'COMMENT_SL')
        && $this->script_break($tok)
      ) 
        break;
      
      assert($this->pos() > $index) or die("$tok didn't consume anything");
      $this->tokens_[] = $tok;
      
      $tag = $tok;
      if ($tok === 'OPENER')
        $tag = null;
      $this->record($m, $tag, $escaped);

    }
  }
}
