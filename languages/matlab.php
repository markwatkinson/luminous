<?php

/*
 * Matlab's pretty simple. Hoorah
 */

require_once(dirname(__FILE__) . '/include/matlab_func_list.php');

class LuminousMATLABScanner extends LuminousSimpleScanner {
  
  // Comments can nest. This beats a PCRE recursive regex, because they are 
  // pretty flimsy and crash/stack overflow easily 
  function comment_override($matches) {
    $start = $this->pos();
    $stack = 0;
    while (1) {
      $next = $this->get_next(array('/%\\{/', '/%\\}/'));
      if ($next[0] === -1) $this->terminate();
      else {
        $this->pos($next[0] + strlen($next[1][0]));
        if ($next[1][0] === '%{') $stack++;
        elseif ($next[1][0] === '%}') $stack--;
        else assert(0);
      }
      if (!$stack) break;
    }
    $this->record(substr($this->string(), $start, $this->pos()-$start), 'COMMENT');
  }
  
  
  function init() {
    // these can nest so we override this
    $this->add_pattern('COMMENT_ML', '/%\\{/');
    $this->add_pattern('COMMENT', '/%.*/');
    $this->add_pattern('IDENT', '/[a-z_]\w*/i');
    // stray single quotes are a unary operator when they're attached to 
    // an identifier or return value, or something. so we're going to 
    // use a lookbehind to exclude those
    $this->add_pattern('STRING', "/(?<![\w\)\]\}']) ' (?: [^']+ | '')* ($|')/x");
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('OPERATOR', "@[¬!%^&*\-+=~;:|<>,./?]+|'@");
    
    $this->overrides = array('COMMENT_ML' => array($this, 'comment_override'));
    $this->rule_tag_map = array('COMMENT_ML' => 'COMMENT');
    $this->add_identifier_mapping('KEYWORD', $GLOBALS['luminous_matlab_keywords']);
    $this->add_identifier_mapping('VALUE', $GLOBALS['luminous_matlab_values']);
    $this->add_identifier_mapping('FUNCTION', $GLOBALS['luminous_matlab_functions']);
  }
  
}