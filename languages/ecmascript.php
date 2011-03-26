<?php

/**
 * This is a rename of the JavaScript scanner.
 * TODO Some of these things are JS specific and should be moved into
 * the new JS scanner.
 */

class LuminousECMAScriptScanner extends LuminousEmbeddedWebScript {
  
  // TODO clean up redunancy here and in constructor
  public $server_tags = '<?';
  public $script_tags = '</script>';
    
  // logs a persistent token stream so that we can lookbehind to figure out
  // operators vs regexes.
  private $tokens_ = array(); 
  
  private $child_state = null;
  
  function __construct($src=null) {
    
    $this->rule_tag_map = array(
      'COMMENT_SL' => 'COMMENT',
      'SSTRING' => 'STRING',
      'DSTRING' => 'STRING',
      'OPENER' => null,
      'CLOSER' => null,
    );
    $this->dirty_exit_recovery = array(
      'COMMENT_SL' => '/.*/',
      'COMMENT' => '%.*?(\*/|$)%s',
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
  
  static function is_operand($tokens) {
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
      // XXX this covers <? and <% but not very well
      if ($this->embedded_server) $op_pattern .= '?%';
      if ($this->embedded_html) $op_pattern .= '/';
      $op_pattern .= '])'; // closes lookahead
      $op_pattern = "(?:$op_pattern)+";
    }
    $op_pattern = "@$op_pattern@";
    
    $this->add_pattern('IDENT', '/[a-zA-Z_$][_$\w]*/'); 
    // NOTE: slash is a special case, and </ may be a script close
    $this->add_pattern('OPERATOR', $op_pattern);
    // we care about openers for figuring out where regular expressions are
    $this->add_pattern('OPENER', '/[\[\{\(]+/');
    $this->add_pattern('CLOSER', '/[\)\}\]]+/');
    
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('SSTRING', LuminousTokenPresets::$SINGLE_STR_SL);
    $this->add_pattern('DSTRING', LuminousTokenPresets::$DOUBLE_STR_SL);    
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    $this->add_pattern('COMMENT_SL', LuminousTokenPresets::$C_COMMENT_SL);
    // special case
    $this->add_pattern('SLASH', '%/%');
    
    $stop_patterns = array();
    if ($this->embedded_server) $stop_patterns[] = "(?P<SERVER>" . preg_quote($this->server_tags, '%') . ")";
    if ($this->embedded_html) $stop_patterns[] = "(?P<SCRIPT_TERM></script>)";
    if (!empty($stop_patterns)) {
      $this->stop_pattern = '%' . join('|', $stop_patterns) . '%i';
      $this->add_pattern('STOP', $this->stop_pattern);
    }
    
    $xml_scanner = new LuminousHTMLScanner($this->string());
    $xml_scanner->xml_literal = true;
    $xml_scanner->scripts = false;
    $xml_scanner->embedded_server = $this->embedded_server;
    $xml_scanner->init();
    $xml_scanner->pos($this->pos());
    $this->add_child_scanner('xml', $xml_scanner);
  }
  
  
  
  
  // c+p from HTML scanner
  function scan_child($lang) {
    assert (isset($this->child_scanners[$lang])) or die("No such child scanner: $lang");
    $scanner = $this->child_scanners[$lang];
    $scanner->pos($this->pos());
    $substr = $scanner->main();
    $this->record($scanner->tagged(), 'XML', true);
    $this->pos($scanner->pos());
    if ($scanner->interrupt) {
      $this->child_state = array($lang, $this->pos());
    } else {
      $this->child_state = null;
    }
  } 

  
  function main() {
    $this->start();
    $this->interrupt = false;
    while (!$this->eos()) {
      $index = $this->pos();
      $tok = null;
      $m = null;
      $escaped = false;
      
        
      if (!$this->clean_exit) {
        $tok = $this->resume();
      }
      elseif ($this->child_state !== null && $this->child_state[1] < $this->pos()) {
        $this->scan_child($this->child_state[0]);
        continue;
      }
      
      elseif (($rule = $this->next_match()) !== null) {
        $tok = $rule[0];
        if ($rule[1] > $index) {
          $this->record(substr($this->string(), $index, $rule[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        $this->clean_exit = true;
        $this->interrupt = false;
        break;
      }
      
      if ($tok === 'SLASH') {
        if (self::is_operand($this->tokens_)) {
        $this->unscan();
        assert ($this->peek() === '/');
        $tok = 'REGEX';
        $m = $this->scan('% / (?: [^/\\\\]+ | \\\\.)* (?:/[ioxgm]*|$)%sx');
        assert ($m !== null) or die();
        } else {
          $tok = 'OPERATOR';
        }
      }
      elseif ($this->match() === '<') {
        // confusing function name, actually checks operator/operand position
        if (self::is_operand($this->tokens_)) {
          // XML literal. TODO, we need a dedicated XML scanner which will
          // terminate when it it runs out of tags.
          $this->unscan();
          $this->scan_child('xml');
          if ($this->embedded_server && $this->peek(2) === '<?') {
            $this->interrupt = true;
            break;
          }
          continue;
        }
      }

      elseif ($tok === 'STOP') {
        if ($this->match() === $this->server_tags) $this->interrupt = true;
        $this->unscan();
        break;
      }
      if ($m === null) $m = $this->match();
      
      if ($this->server_break($tok)) { break; }
      
      if (($tok === 'COMMENT_SL')
        && $this->script_break($tok)
      ) 
        break;
      assert($this->pos() > $index) or die("'$tok' didn't consume anything");
      $this->tokens_[] = $tok;
      
      $tag = $tok;
      if ($tok === 'OPENER')
        $tag = null;
      $this->record($m, $tag, $escaped);

    }
  }
}
