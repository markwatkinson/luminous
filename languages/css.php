<?php

class CSSScanner extends LuminousEmbeddedWebScript {
  
  
  
  
  function __construct($src=null) {
    parent::__construct($src);
        
    $this->rule_tag_map = array(
      'TAG' => 'KEYWORD',
      'KEY' => 'TYPE',
      'SELECTOR' => 'VARIABLE',
      'ATTR_SELECTOR' => 'VARIABLE',
      'SSTRING' => 'STRING',
      'DSTRING' => 'STRING',
    );
    
    $this->dirty_exit_recovery = array(
      'COMMENT' => '%.*?\*/%s',
      'SSTRING' => "/(?:[^\\\\']+|\\\\.)*'/",
      'DSTRING' => '/(?:[^\\\\"]+|\\\\.)*"/',
      'ATTR_SELECTOR' => '/(?: [^\\]\\\\]+ | \\\\.)* \]/xs'
    );
    $this->state_ [] = 'global';
    
  }
    
  
  
  function main() {
    
    $comment_regex = '% /\* .*? \*/ %sx';
    
    $expecting = null;
    
    
    $this->out = '';
    
    while (!$this->eos()) {
      if (!$this->clean_exit) {
        
        $tok = $this->resume();
        $this->tag($this->match(), $tok);
        continue;
        
      }
      $this->skip_whitespace();
      $pos = $this->pos();
      $tok = null;
      $m = null;
      $state = $this->state();
      $in_block = $state === 'block';
      $get = false;
      $c = $this->peek();
      

      
      if ($c === '/' && $this->scan(LuminousTokenPresets::$C_COMMENT_ML)) 
        $tok = 'COMMENT';
      elseif($in_block && $c === '#' && 
        $this->scan('/#[a-fA-F0-9]{3}(?:[a-fA-F0-9]{3})?/'))
        $tok = 'NUMERIC';
      elseif($in_block && (ctype_digit($c) || $c === '-') 
        && $this->scan('/-?(?>\d+)(\.(?>\d+))?(?:em|px|ex|ch|mm|cm|in|pt|%)?/')) {
        $tok = 'NUMERIC';
      }
      elseif(( ctype_alpha($c) || $c === '!' || $c === '@' || $c === '_' || $c === '-' )
        &&  $this->scan('/(!?)[\-\w@]+/')) {
        if (!$in_block || $this->match_group(1) !== '') $tok = 'TAG';
        elseif($expecting === 'key') $tok = 'KEY';
        elseif($expecting === 'value') {
          $m = $this->match();
          if ($m === 'url' || $m === 'rgb' || $m === 'rgba') $tok = 'FUNCTION';
          else $tok = 'VALUE';
        }
      }
      elseif($c === '#' && !$in_block && $this->scan('/#[\w\-]+/'))
        $tok = 'SELECTOR';
      elseif($c === '.' && !$in_block && $this->scan('/\.[\w+\-]+/'))
        $tok = 'SELECTOR';
      elseif(!$in_block && $c === '[' 
        && $this->scan('/\[ (?: [^\\]\\\\]+ | \\\\.)* \]/sx'))
        $tok = 'ATTR_SELECTOR';
      
      elseif($c === '}' || $c === '{') {
        $get = true;
        if ($c === '}' && $in_block)
          array_pop($this->state_);
        elseif (!$in_block && $c === '{') {
          $this->state_[] = 'block';
          $expecting = 'key';
        }
      }
      elseif($c === '"' && $this->scan('/" (?: [^"\\\\]+ | \\\\.)* (?:"|$)/xs') )
        $tok = 'DSTRING';      
      elseif($c === "'" && $this->scan("/' (?: [^'\\\\]+ | \\\\.)* (?:'|$)/xs"))         
        $tok = 'SSTRING';
      elseif($c === ':' && $in_block) {
        $expecting = 'value';
        $get = true;
      }
      elseif($c=== ';' && $in_block) {
        $expecting = 'key';
        $get = true;
      }
      elseif($this->scan('%<\s*/\s*style%i')) {
        $this->unscan();
        break;
      }
      elseif($this->scan('/<\?/')) {
        $this->unscan();
        break;
      }      
      else {
        $get = true;        
      }
      
     if ($this->server_break($tok)) break;
      
      $this->tag($get? $this->get() : $this->match(), $tok);
      assert($this->pos() > $pos || $this->eos()) or die();
      
    }
    
    return $this->out;
  }
}