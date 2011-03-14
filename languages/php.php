<?php


class PHPScanner extends  LuminousEmbeddedWebScript {
  
  
  function __construct($src=null) {
    
    $this->add_child_scanner('html', new HTMLScanner($src));
    
    parent::__construct($src);    
    
    $this->add_pattern('START', '/<\?(php|=)?/'); 
    $this->add_pattern('TERM', '/\?>/'); 
    $this->add_pattern('COMMENT', '% (?://|\#) .*? (?: (?=\?>) | $) %sx');
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);    
    $this->add_pattern('OPERATOR', '@[!%^&*\-=+~:<>?/]+@');
    $this->add_pattern('VARIABLE', '/\\$\w+(->\w+)*/');
    $this->add_pattern('IDENT', '/[a-zA-Z_]\w*/');
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);    
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    $this->add_pattern('STRING', LuminousTokenPresets::$SINGLE_STR);
  }
  
  
  function scan_child() {
    $s = $this->child_scanners['html'];
    $s->pos($this->pos());
    $out = $s->main();
    $this->tag($out, null, true);
    $this->pos($s->pos());
  }
  
  function main() {
    $inphp = false;
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
          $this->tag(substr($this->string(), $index, $match[1] - $index), null);
        }
      } else {
        $this->tag(substr($this->string(), $index), null);
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
      $this->tag($this->match(), $tok);
    }
    return $this->out;
  }
}