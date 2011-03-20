<?php

require_once(dirname(__FILE__) . '/java_func_list.php');

class LuminousJavaScanner extends LuminousSimpleScanner {

  function init() {
    $this->add_identifier_mapping('KEYWORD', array('abstract', 'assert',
    'break', 'case', 'class', 'continue', 'const', 'default', 'do',
    'else', 'final', 'finally', 'for', 'goto', 'if', 'implements',
    'import',
    'instanceof',
    'interface',
    'native', 'new', 'package', 'private', 'public', 'protected',
    'return', 'static', 'strictfp', 'switch', 'synchronized', 'this', 'throw',
    'throws', 'transient', 'volatile', 'while',
    'true', 'false', 'null'
    ));
    $this->add_identifier_mapping('TYPE', array_merge(
      array('bool', 'boolean', 'byte',
      'char', 'double', 'enum', 'float', 'int', 'long', 'short', 'void'),
      $GLOBALS['luminous_java_types'])
    );
    

    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_SL);
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    $this->add_pattern('CHARACTER', LuminousTokenPresets::$SINGLE_STR);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('IDENT', '/[a-zA-Z]\w*/');
    $this->add_pattern('OPERATOR', '/[!%^&*\-=+:?|<>]+/');
  }

}