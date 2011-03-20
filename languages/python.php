<?php

class LuminousPythonScanner extends LuminousScanner {
  
  
  
  public function init() {
    
    
    $this->remove_filter('comment-to-doc');
    
    // so it turns out this template isn't quite as readable as I hoped, but
    // it's a triple string, e.g:
    //  "{3} (?: [^"\\]+ | ""[^"\\]+ | "[^"\\]+  | \\.)* (?: "{3}|$)
    
    
    $triple_str_template = '%1$s{3} (?: [^%1$s\\\\]+ | %1$s%1$s[^%1$s\\\\]+ | %1$s[^%1$s\\\\]+ | \\\\. )* (?: %1$s{3}|$)';
    $str_template = '%1$s (?: [^%1$s\\\\]+ | \\\\. )* (?: %1$s|$)';
    $triple_dstr = sprintf($triple_str_template, '"');
    $triple_sstr = sprintf($triple_str_template, "'");    
    
    $this->add_pattern('IDENT', '/[a-zA-Z_](?>\w*)(?!["\'])/');
    $this->add_pattern('COMMENT', '/\#.*/');
    
    // catch the colon separately so we can use $match === ':' in figuring out
    // where docstrs occur
    $this->add_pattern('OPERATOR', '/[!%^*\-=+;<>\\\\(){}\[\],]+|:/');
    
    // decorator
    $this->add_pattern('TYPE', '/@(\w+\.?)+/');
    
    // Python strings may be prefixed with r (raw) or u (unicode).
    // This affects how it handles backslashes, but I don't *think* it
    // affects escaping of quotes.... 
    $this->add_pattern('STRING', "/[RUru]?$triple_dstr/x");
    $this->add_pattern('STRING', "/[RUru]?$triple_sstr/x");
    $this->add_pattern('STRING', "/[RUru]?" . sprintf($str_template, '"') . '/x');
    $this->add_pattern('STRING', "/[RUru]?" . sprintf($str_template, "'") . '/x');
    
    // EPIC.
    $this->add_pattern('NUMERIC', '/
    #hex
    (?:0[xX](?>[0-9A-Fa-f]+)[lL]*)
    |
    # binary
    (?:0[bB][0-1]+)
    |
    #octal
    (?:0[oO0][0-7]+)
    |
    # regular number
    (?:
      (?>[0-9]+)
      (?: 
        # long identifier
        [lL]
        |
        # Or a fractional part, which may be imaginary
        (?:
          (?:\.?(?>[0-9]+)?        
            (?:(?:[eE][\+\-]?)?(?>[0-9]+))?
          )[jJ]?
        )
      )?
    )
    |
    (
      # or only after the point, float x = .1;
      \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?(?>[0-9]+))?[jJ]?
    )
    /x'); 
    
    
    $this->add_identifier_mapping('KEYWORD', array('assert', 'as', 'break',
    'class', 'continue', 'del', 'def', 'elif', 'else', 'except', 'exec',
    'finally', 'for', 'from', 'global', 'if', 'import', 'lambda', 
    'print', 'pass', 'raise', 'return', 'try', 'while', 'yield',
    'and', 'not', 'in', 'is', 'or',
    'print', 'True', 'False', 'None'));
    
    $this->add_identifier_mapping('FUNCTION', array('all', 'abs', 'any', 
    'basestring', 'bin', 'callable', 'chr', 'classmethod', 'cmp', 'compile',
    'dir', 'divmod', 'enumerate', 'eval', 'execfile', 'file', 'filter', 'format',
    'frozenset', 'getattr', 'globals', 'hasattr', 'hash', 'help', 'hex', 
    'id', 'input', 'isinstance', 'issubclass', 'iter', 'len', 'locals', 'map',
    'max', 'min', 'memoryview', 'next', 'object', 'oct', 'open', 'ord', 'pow',
     'property', 'range', 'raw_input', 'reduce', 'reload', 'repr', 'reversed',
     'round', 'setattr', 'slice', 'sorted', 'staticmethod', 'sum', 'super',
     'type', 'unichr', 'vars', 'xrange', 'zip', '__import__'));
     


  }
  
  
  function main() {
    $definition = false;
    $doccstr = false;
    $expect = '';
    while (!$this->eos()) {
      $tok = null;
      $index = $this->pos();
      
      if (($rule = $this->next_match()) !== null) {
        $tok = $rule[0];
        if ($rule[1] > $index) {
          $this->record(substr($this->string(), $index, $rule[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        break;
      }
      $m = $this->match();
      
      /* python doc strs are a pain because they're actually strings. 
       * Also, I'm pretty sure a string in a non-interesting place just counts
       * as a no-op and is also used as a comment sometimes
       * So we've got something a bit complicated going on here: if we meet
       * a 'class' or a 'def' (function def) then we wait until the next ':' 
       * and say "we expect a doc-str now". If the next token is not a string,
       * we discard that state.
       * 
       * similarly, if we meet a string which isn't a doc-str, we look behind 
       * and expect to see an operator or open bracket, else it's a comment.
       * NOTE: we class ':' as a legal string preceding char because it's used
       * as dictionary key:value separators. This will fail on the case:
       * 
       * while 1: 
       *  "do something" 
       *  break
       * 
       * 
       * NOTE: note we're skipping whitespace.
       */
      
      if ($definition && $doccstr) {
        if($tok === 'STRING')
          $tok = 'COMMENT';
      }
      elseif ($tok === 'STRING') {
        $i = count($this->tokens);
        $tok = 'COMMENT';
        while ($i--) {
          $t = $this->tokens[$i][0];
          $s = $this->tokens[$i][1];
          if ($t === null || $t === 'COMMENT') continue;
          elseif ($t === 'OPERATOR' || $t === 'IDENT' || $t === 'NUMERIC') { 
            $tok = 'STRING';
          }
          break;
        }
      }
     
      // reset this; if it didn't catch above then it's not valid now.
      if ($definition && $doccstr) {
        $definition = false;
        $doccstr = false;
      }
            
      if ($tok === 'IDENT') {
        
        if ($m === 'class' || $m === 'def') {
          $definition = true;
          $expect = 'user_def';
        }        
        elseif($expect === 'user_def') {
          $tok = 'USER_FUNCTION';
          $expect = false;
          $this->user_defs[$m] = 'FUNCTION';
        }
      }
      else { $expect = false; }
      
      if ($definition && $m === ':') {
        $doccstr = true;
      }
      
      $this->record($m, $tok);
    }
  }
  
  
}