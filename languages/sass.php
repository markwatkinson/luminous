<?php

/**
  * THIS IS VERY MUCH A WORK IN PROGRESS!
  *
  * SASS is a complex language to highlight because tokens tend to be quite
  * ambiguous. Consider the following:

a:hover {
  a:hover {
    a:hover;
  }
}

  * The approach we take is to do a minimal tokenisation pass, then
  * we run the stream through a parser of sorts which tries to 
  * analyse context and perform necessary disambiguation.  
  * 
  * Currently we only support SCSS syntax rather than SASS (which
  * uses whitespace instead of braces). I hope that we will be
  * able to shoe-horn in SASS syntax by inserting some markers in
  * the token stream before we send it to the parser.
  *
  */
  


class LuminousSassParser {

  public $tokens = array();
  public $index = 0;
  
  public $token;
  
  // SASS has two syntaxes: 'sass' and 'scss'.
  // The former is whitespace dependent, the 
  // latter uses braces
  public $syntax = 'sass';
  
  /**
    * Finds the index of the next token with the 
    * given type past the current offset.
    * For context, if the token is not found,
    * the return value is greater than the length of
    * the token array. This makes comparisons easier.
    */
  protected function _find_next($token_types, $skip=array()) {
    if (!is_array($token_types)) $token_types = array($token_types);
    for($i=$this->index; $i<count($this->tokens); $i++) {
      $found = true;
      $skipped = 0;
      for ($j=0; $j + $i + $skipped < count($this->tokens) 
        && $j < count($token_types) ; $j++) 
      {
        $type = $this->tokens[$j+$i+$skipped][0];
        if (in_array($type, $skip)) {
          $skipped++; 
          $j--; //refactor please
          continue;
        }
        if ($type !== $token_types[$j]) {
          $found = false;
          break;
        }
      }
      if ($found) return $i;
    }
    return count($this->tokens)+1;
  }
  
  /**
    * Parses a selector rule 
    */
  private function _parse_rule() {  
    $new_token = $this->token;
    $set = false;
    if ($this->index > 0) {
      $prev_token = &$this->tokens[$this->index-1];
      $prev_token_type = &$prev_token[0];
      $prev_token_text = &$prev_token[1];
      $concat = false;
      
      $map = array(
        'DOT' => 'CLASS_SELECTOR',
        'HASH' => 'ID_SELECTOR',
        'COLON' => 'PSEUDO_SELECTOR',
        'DOUBLE_COLON' => 'PSEUDO_SELECTOR'
      );
      if (isset($map[$prev_token_type])) {
        // mark the prev token for deletion and concat into one.
        $new_token[0] = $map[$prev_token_type];
        $prev_token_type = 'DELETE';
        $new_token[1] = $prev_token_text . $new_token[1];
        $set = true;
      }
    }
    if (!$set) {
      // must be an element
      $new_token[0] = 'ELEMENT_SELECTOR';
    }
    $this->tokens[$this->index] = $new_token;
  }
  
  private function _parse_property_value() {
    
  }
  
  
  
  private function _sass_inject_pseudo_tokens() {
    if ($this->syntax !== 'sass') return;
    $new_tokens = array();
    $indent_stack = array(0);
    $len = count($this->tokens);
    $bol = false;
    
    
    // pseudo token template
    // we add a data attribute so as not to risk overwriting
    // anything in future
    $pseudo_token_l = array(
      0 => 'L_BRACE',
      1 => '{',
      2 => false,
      'data' => array('pseudo' => true )
    );
    $pseudo_token_r = array(
      0 => 'R_BRACE',
      1 => '}',
      2 => false,
      'data' => array('pseudo' => true )
    );    
    $pseudo_token_s = array(
      0 => 'SEMICOLON',
      1 => ';',
      2 => false,
      'data' => array('pseudo' => true )
    );    
    
    
    $lines = array();
    
    $line_ = array('indent' => 0, 'tokens' => array(), 'children' => array());
    $line = $line_;
    foreach($this->tokens as $i=>$t) {
      $type = $t[0];
      $line['tokens'] [] = $t;
      if ($type === 'EOL' || $i === count($this->tokens)-1) {
        $bol = true;
        $lines[] = $line;
        continue;
      }
      if ($bol) {
        $line = $line_;
        if ($type === 'WHITESPACE') {
          $indent_width = strlen($t[1]);
          $line_['indent'] = $indent_width;
        }
      }
    }
    
  }
    
  
  
  
  public function parse() {
    $state = array();
    $prop_value = null;
    
    $this->_sass_inject_pseudo_tokens();
    
    for ($this->index = 0; $this->index < count($this->tokens); $this->index++) {
      $this->token = &$this->tokens[$this->index];
      $cur_state = count($state)? $state[count($state)-1] : null;
      
      if ($this->token[0] === 'L_BRACKET') {
        $state[] = 'bracket';
      } 
      else if ($this->token[0] === 'R_BRACKET' && $cur_state === 'bracket') {
        array_pop($state);
        $cur_state = count($state)? $state[count($state)-1] : null;
      }
      else if ($this->token[0] === 'L_BRACE') {
        $state[] = 'brace';
      }
      else if ($this->token[0] === 'R_BRACE' && $cur_state === 'brace') {
        array_pop($state);
        $cur_state = count($state)? $state[count($state)-1] : null;
      }
      
      if ($this->token[0] === 'AT_IDENTIFIER') {
      }
      
      if ($this->token[0] === 'GENERIC_IDENTIFIER') {
        if (false && $cur_state === 'bracket') {
          $this->token[0] = 'STRING';
        } else {
          
        
          // we have to figure out exactly what this is
          // if we can look ahead and find a '{' before we find a 
          // ';', then this is part of a selector.
          // Otherwise it's part of a property/value pair.          
          // the exception is when we have something like:
          // font : { family : sans-serif; } 
          // then we need to check for ':{'

          $colon =  min(
            $this->_find_next('SEMICOLON'),
            $this->_find_next(array('COLON', 'L_BRACE'), array('EOL', 'WHITESPACE'))
          );

          
          $brace = $this->_find_next('L_BRACE');          
          
          if ($colon > $brace) {
            $this->_parse_rule();
            // reset this in case there was a ':' in the selector
            $prop_val = 'property';
            
            
          } elseif ($brace >= $colon) {
          
            if ($prop_value === 'value') {
              // this could be a colour or numeric
              $this->token[0] = 'VALUE';
              
              $char = isset($this->token[1][0])? $this->token[1][0] : '';              
              if (ctype_digit($char)) {
                $this->token[0] = 'NUMERIC';                
              }
              if ($this->index) {
                // is it a colour?
                $prev_token = &$this->tokens[$this->index-1];
                if ($prev_token[0] === 'HASH') {
                  $prev_token[0] = 'DELETE';                  
                  $this->token[0] = 'NUMERIC';
                  $this->token[1] = $prev_token[1] . $this->token[1];
                }
              }
            }
            else {
              $this->token[0] = 'PROPERTY';
            }
          }
          else {
            // invalid syntax
          }
        }
      }
      elseif($this->token[0] === 'L_BRACE' || $this->token[0] === 'SEMICOLON') {
        $prop_value = 'property';
      } 
      elseif($this->token[0] === 'R_BRACE' && $cur_state === 'brace') {
        $prop_value = 'property';
      }
      elseif($this->token[0] === 'COLON') {
        $prop_value = 'value';
      }
    }
    
    foreach($this->tokens as $i=>$t) {
      if ($t[0] === 'DELETE' /*|| 
        (isset($t['data']) && isset($t['data']['pseudo'])
          && $t['data']['pseudo'] === true
        )*/
      ) {
        unset($this->tokens[$i]);
      }
    }  
    // reindex the array
    $this->tokens = array_values($this->tokens);
    return $this->tokens;    
  }
  

}




class LuminousSassScanner extends LuminousScanner {

  protected $regexen = array();
  
  /*
  public $rule_tag_map = array(
    'PROPERTY' => 'TYPE',
    'COMMENT_SL' => 'COMMENT',
    'COMMENT_ML' => 'COMMENT',
    'ELEMENT_SELECTOR' => 'KEYWORD',
    'STRING_S' => 'STRING',
    'STRING_D' => 'STRING',
    'CLASS_SELECTOR' => 'VARIABLE',
    'ID_SELECTOR' => 'VARIABLE',
    'PSEUDO_SELECTOR' => 'VARIABLE',
    'ATTR_SELECTOR' => 'OPERATOR',
  ); */
  
  
  public function init() {
    $this->regexen = array(
      // For the first pass we just feed in a bunch of tokens.
      // Some of these are generic and will require disambiguation later
      'COMMENT_SL' => LuminousTokenPresets::$C_COMMENT_SL,
      'COMMENT_ML' =>  LuminousTokenPresets::$C_COMMENT_ML,
      'STRING_S' => LuminousTokenPresets::$SINGLE_STR,
      'STRING_D' => LuminousTokenPresets::$DOUBLE_STR,
      // TODO check var naming, is $1 a legal variable?
      'VARIABLE' => '%\$[\-a-z_0-9]+ | \#\{\$[\-a-z_0-9]+\} %x',
      'AT_IDENTIFIER' => '%@[a-zA-Z0-9]+%',
      
      // This is generic - it may be a selector fragment, a rule, or
      // even a hex colour.
      'GENERIC_IDENTIFIER' => '@
        [a-fA-F0-9]{3}[a-fA-F0-9]{3}?
        |
        [0-9]+(\.[0-9]+)?(%|in|cm|mm|em|ex|pt|pc|px|s)?
        |
        -?[a-zA-Z_\-0-9]+[a-zA-Z_\-0-9]*
        |&
      @x',
      'L_BRACE' => '/\{/',
      'R_BRACE' => '/\}/',
      'L_SQ_BRACKET' => '/\[/',
      'R_SQ_BRACKET' => '/\]/',
      'L_BRACKET' => '/\(/',
      'R_BRACKET' => '/\)/',
      
      'DOUBLE_COLON' => '/::/',
      'COLON' => '/:/',
      'SEMICOLON' => '/;/',
      
      'DOT' => '/\./',
      'HASH' => '/#/',
      
      'COMMA' => '/,/',
      
      'EOL' => "/\r\n|\n|\r/",
      'WHITESPACE' => '/\s+/'
    );
  }
  
  
  

  

  
  

  public function main() {
    while (!$this->eos()) {
      $m;
      foreach($this->regexen as $token => $pattern) {
        if ( ($m = $this->scan($pattern)) !== null) {
          $this->record($m, $token);
          break;
        }
      }
      if ($m === null)
        $this->record($this->get(), null);
    }
    
    // now we've got a token stream, we can manipulate it and 
    // try to parse some contexts
    
    
    $parser = new LuminousSassParser();
    $parser->tokens = $this->tokens;
    $this->tokens = $parser->parse();
  }
  
}


