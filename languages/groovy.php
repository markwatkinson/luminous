<?php

/*
 * Groovy is pretty much a cross between Python and Java.
 * It inherits all of Java's stuff
 * http://groovy.codehaus.org/jsr/spec/Chapter03Lexical.html
 */
require_once(dirname(__FILE__) . '/include/java_func_list.php');
class LuminousGroovyScanner extends LuminousSimpleScanner {

  function regex_override($match) {
    assert($this->peek() === '/');
    assert($match === array(0=>'/'));
    $regex = false;
    
    $i = count($this->tokens);
    while ($i--) {
      list($tok, $contents) = $this->tokens[$i];
      if ($tok === 'COMMENT') continue;
      elseif ($tok === 'OPERATOR') $regex = true;
      elseif($tok !== null) $regex = false;
      else {
        $t = rtrim($contents);
        if ($t === '') continue;
        $c = $t[strlen($t)-1];
        $regex = ($c === '(' || $c === '[' || $c === '{');
      }
      break;
    }
    
    if (!$regex) {
      $this->record($this->get(), 'OPERATOR');      
    }
    else {
      $m = $this->scan('@ / (?: [^\\\\/]+ | \\\\. )* (?: /|$) @sx');
      assert($m !== null);
      $this->record($m, 'REGEX');
    }
  }

  
  function init() {
    $this->add_identifier_mapping('KEYWORD',
      $GLOBALS['luminous_java_keywords']);
    $this->add_identifier_mapping('TYPE', $GLOBALS['luminous_java_types']);
    $this->add_identifier_mapping('KEYWORD', array('any', 'as', 'def', 'in',
      'with', 'do', 'strictfp',
      'println'));


    // C+P from python
    // so it turns out this template isn't quite as readable as I hoped, but
    // it's a triple string, e.g:
    //  "{3} (?: [^"\\]+ | ""[^"\\]+ | "[^"\\]+  | \\.)* (?: "{3}|$)
    $triple_str_template = '%1$s{3} (?: [^%1$s\\\\]+ | %1$s%1$s[^%1$s\\\\]+ | %1$s[^%1$s\\\\]+ | \\\\. )* (?: %1$s{3}|$)';
    $str_template = '%1$s (?: [^%1$s\\\\]+ | \\\\. )* (?: %1$s|$)';
    $triple_dstr = sprintf($triple_str_template, '"');
    $triple_sstr = sprintf($triple_str_template, "'");

    $this->add_pattern('COMMENT', '/^#!.*/');
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_SL);
    $this->add_pattern('STRING', "/$triple_dstr/sx");
    $this->add_pattern('STRING', "/$triple_sstr/xs");
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    // differs from java:
    $this->add_pattern('STRING', LuminousTokenPresets::$SINGLE_STR);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('IDENT', '/[a-zA-Z_]\w*/');
    $this->add_pattern('OPERATOR', '/[~!%^&*\-=+:?|<>]+/');
    $this->add_pattern('SLASH', '%/%');
    
    $this->overrides['SLASH'] = array($this, 'regex_override');
  }
}
