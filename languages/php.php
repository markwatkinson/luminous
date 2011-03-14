<?php

require_once( dirname(__FILE__) . '/php_func_list.php');

class PHPScanner extends  LuminousEmbeddedWebScript {
  
  protected $case_sensitive = false;
  
  function __construct($src=null) {
    $h = new HTMLScanner($src);
    $h->embedded_server = true;
    $h->init();    
    $this->add_child_scanner('html', $h);
    
    parent::__construct($src);
    
    $this->add_pattern('START', '/<\?(php|=)?/'); 
    $this->add_pattern('TERM', '/\?>/'); 
    // Why does hash need escaping?
    $this->add_pattern('COMMENT', '% (?://|\#) .* (?=\\?>|$)  %xm');
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
    
    
  }
  
  function init() {}
  
  function scan_child() {
    $s = $this->child_scanners['html'];
    $s->pos($this->pos());
    $s->main();
    $this->tokens = array_merge($this->tokens, $s->token_array());
    $this->pos($s->pos());
  }
  
  function main() {
    $inphp = false;
    $this->start();
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
      
      
      if ($tok === 'IDENT') {
        $tok = $this->map_identifier($this->match());
        if ($tok === 'IDENT') $tok = null;
      }
      assert($this->pos() > $index) or die("$tok didn't consume anything");
      $this->record($this->match(), $tok);
    }
  }
}