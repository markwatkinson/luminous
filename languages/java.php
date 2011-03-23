<?php

require_once(dirname(__FILE__) . '/include/java_func_list.php');

class LuminousJavaScanner extends LuminousSimpleScanner {

  function init() {
    $this->add_identifier_mapping('KEYWORD',
      $GLOBALS['luminous_java_keywords']);
    $this->add_identifier_mapping('TYPE', $GLOBALS['luminous_java_types']);
    

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
