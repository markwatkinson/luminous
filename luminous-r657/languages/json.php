<?php

/*
 * Taken from the grammar at
 * http://www.json.org/
 *
 * some tokens are specified in the simple rules because it's a lot faster
 */
class LuminousGrammarJSON extends LuminousGrammar
{
  public function str_cb($str)
  {
    return preg_replace('/\\\
      (?: (?:u[a-fA-F0-9]{4})
      |[\\\bfnrt\\/"]
      )/x', '<ESC>$0</ESC>', $str);
  
  }
  public function __construct()
  {
    $this->SetInfoLanguage('json');

    $str_regex = '"(?:[^"\\\]+|\\\(?:["\\\bfnrt\\/]|u[a-fA-F0-9]{4})|\\\)*"';
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'BLOCK', LUMINOUS_CONSUME, '{', '}'),
      new LuminousDelimiterRule(0, 'LINE_', LUMINOUS_REGEX, '/[^\s\}]/', 
        '/,|(?=\})/'),
      new LuminousDelimiterRule(0, 'INDEX', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
       "/$str_regex/", null, array($this, 'str_cb')),
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        "/$str_regex/", null, array($this, 'str_cb')),
      new LuminousDelimiterRule(0, 'VALUE_', LUMINOUS_REGEX, '/:/', 
        '/(?=[,\}])/'),
      new LuminousDelimiterRule(0, 'ARRAY', LUMINOUS_CONSUME, '[', ']'),
      );

    $this->state_transitions = array(
      'GLOBAL' => array('BLOCK', 'ARRAY'),
      'BLOCK' => array('LINE_'),
      'LINE_' => array('INDEX', 'VALUE_'),
      'VALUE_' => array('STRING', 'BLOCK', 'ARRAY' ),
      'ARRAY'=>array('STRING', 'BLOCK', 'ARRAY')
    );
    $this->state_type_mappings = array(
      'BLOCK' => null,
      'TYPE_LITERAL'=>'VALUE',
      'LINE_'=>null,
      'ARRAY'=>null,
      'VALUE_' => null,
      'INDEX' => 'TYPE',
    );
    $this->numeric_regex = '/
      (?<![\w&])
      (?:
        -?
        (?:0|[1-9]\d*)
        (?:\.\d+)?
        (?:[eE][+-]?\d+)?
      )
      /x';
    $this->operators = array();
    $this->SetSimpleTypeRules();
    $this->simple_types[] = new LuminousSimpleRule(3, 'VALUE', 
      LUMINOUS_REGEX, '/true|false|null/');
 }
}
