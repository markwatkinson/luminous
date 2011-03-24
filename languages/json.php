<?php


class LuminousJSONScanner extends LuminousScanner {
  
  private $stack = array();
  
  
  public function init() {
    $this->add_identifier_mapping('KEYWORD', array('true', 'false', 'null'));
  
    
  }
  public function state() {
    if (!empty($this->stack)) return $this->stack[count($this->stack)-1][0];
    else return null;
  }
  
  private function expecting($x=null) {
    if ($x !== null) {
      if (!empty($this->stack)) $this->stack[count($this->stack)-1][1] = $x;
    }
    if (!empty($this->stack)) return $this->stack[count($this->stack)-1][1];
    else return null;
  }

  function main() {
    while (!$this->eos()) {
      $tok = null;
      $c = $this->peek();
      
      list($state, $expecting) = array($this->state(), $this->expecting());
      
      $this->skip_whitespace();
      if ($this->eos())  break;
      if ($this->scan(LuminousTokenPresets::$NUM_REAL) !== null) {
        $tok = 'NUMERIC';
      }
      elseif($this->scan('/[a-zA-Z]\w*/')) {
        $tok = 'IDENT';
      }
      elseif($this->scan(LuminousTokenPresets::$DOUBLE_STR)) {
        $tok = ($state === 'obj' && $expecting === 'key')? 'TYPE' : 'STRING';
      }
      elseif($this->scan('/\[/')) {
        $this->stack[] = array('array', null);
        $tok = 'OPERATOR';
      }
      elseif($this->scan('/\]/')) {
        if ($state === 'array') {
          array_pop($this->stack);
          $tok = 'OPERATOR';
        }
      }
      elseif($this->scan('/\{/')) {
        $this->stack[] = array('obj', 'key');
        $tok = 'OPERATOR';
      }
      elseif($this->scan('/\}/')) {
        array_pop($this->stack);
        $tok = 'OPERATOR';
      }
      elseif($state === 'obj' && $this->scan('/:/')) {
        $this->expecting('value');
        $tok = 'OPERATOR';
      }
      elseif($state === 'obj' && $this->scan('/,/')) {
        $this->expecting('key');
        $tok = 'OPERATOR';
      }
      else $this->scan('/./');
      
      $this->record($this->match(), $tok);
    }
  }
  
}