<?php

require_once( dirname(__FILE__) . '/php_func_list.php');

class LuminousPHPScanner extends  LuminousEmbeddedWebScript {
  
  protected $case_sensitive = false;
  
  function __construct($src=null) {
    $h = new LuminousHTMLScanner($src);
    $h->embedded_server = true;
    $h->init();    
    $this->add_child_scanner('html', $h);
    
    parent::__construct($src);
    
    $this->add_pattern('START', '/<\?(php|=)?/'); 
    $this->add_pattern('TERM', '/\?>/'); 
    $this->add_pattern('COMMENT', '% (?://|\#) .*? (?=\\?>|$)  %xm');
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML); 
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('OPERATOR', '@[!%^&*\-=+~:<>?/]+@');
    $this->add_pattern('VARIABLE', '/\\$\\$?[a-zA-Z_]\w*/');
    $this->add_pattern('IDENT', '/[a-zA-Z_]\w*/');
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    $this->add_pattern('STRING', LuminousTokenPresets::$SINGLE_STR);
    $this->add_identifier_mapping('FUNCTION', $GLOBALS['luminous_php_functions']);
    $this->add_identifier_mapping('KEYWORD', $GLOBALS['luminous_php_keywords']);

    $this->add_filter('STRING', array($this, 'str_filter'));
    $this->add_filter('HEREDOC', array($this, 'str_filter'));
    $this->add_filter('NOWDOC', array($this, 'nowdoc_filter'));
    
    
  }

  static function str_filter($token) {
    if ($token[1][0] !== '"' && $token[0] !== 'HEREDOC') return $token;
    elseif(strpos($token[1], '$') === false) return $token;
    
    $token = LuminousUtils::escape_token($token);
    $token[1] = preg_replace('/
      \{\$[^}]+\}
      |
      \$\$?[a-zA-Z_]\w*
      /x', '<VARIABLE>$0</VARIABLE>',
    $token[1]);
    return $token;
  }
  
  static function nowdoc_filter($token) {
    $token[0] = 'HEREDOC';
    return $token;
  }
  
  function init() {}
  
  function scan_child() {
    $s = $this->child_scanners['html'];
    $s->pos($this->pos());
    $s->main();
    $this->tokens[] = array(null, $s->tagged(), true);
    $this->pos($s->pos());
  }

  
  function main() {
    $inphp = false;
    $this->start();
    $expecting = false;
    while (!$this->eos()) {
      $tok = null;      
      if (!$inphp) {
        if ($this->peek(2) !== '<?') {
          $this->scan_child();
        }
        assert ($this->peek(2) === '<?' || $this->eos()) or die($this->rest());
        if ($this->eos()) break;
      }      
      $index = $this->pos();
      
      if (($match = $this->next_match()) !== null) {
        $tok = $match[0];
        if ($match[1] > $index) {
          $this->record(substr($this->string(), $index, $match[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        break;
      }
      
      if ($tok === 'TERM') {
        $tok = 'KEYWORD';
        $inphp = false;
      }
      elseif($tok === 'START') {
        $tok = 'KEYWORD';
        $inphp = true;
      }
      
      
      if($tok === 'IDENT') {
        $m = $this->match();
        if ($m === 'class') $expecting = 'class';
        elseif ($m === 'function') $expecting = 'function';
        else {
          if ($expecting === 'class') {                      
            $this->user_defs[$m] = 'TYPE';
            $tok = 'USER_FUNCTION';
          }
          elseif($expecting === 'function') {
            $this->user_defs[$m] = 'FUNCTION';
            $tok = 'USER_FUNCTION';
          }
          $expecting = false;
        }
      }

      elseif($tok === 'OPERATOR') {
        // figure out heredoc syntax here 
        if (strpos($this->match(), '<<<') !== false) {
          $this->record($this->match(), $tok);
          $this->scan('/([\'"]?)([\w]*)((?:\\1)?)/');
          $g = $this->match_groups();
          $nowdoc = false;
          if ($g[1]) {
            // nowdocs are delimited by single quotes. Heredocs MAY be 
            // delimited by double quotes, or not.
            $nowdoc = $g[1] === "'";
            $this->record($g[1], null);
          }
          $delimiter = $g[2];
          $this->record($delimiter, 'KEYWORD');
          if ($g[3]) $this->record($g[3], null);
          // bump us to the end of the line
          if (strlen($this->scan('/.*/')))
            $this->record($this->match(), null);
          if ($this->scan_to("/^$delimiter|\z/m")) {
            $this->record($this->match(), ($nowdoc)? 'NOWDOC' : 'HEREDOC');
            $this->record($this->scan('/\w+/'), 'KEYWORD');
          }
          continue;
        }
      }

      assert($this->pos() > $index) or die("$tok didn't consume anything");
      $this->record($this->match(), $tok);
    }
  }
}

