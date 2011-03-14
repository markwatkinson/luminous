<?php

/*
 * LUMINOUS CAN HAS LOLCODE?
 */

class LuminousGrammarLolcode extends LuminousGrammar
{
  
  public $operators = array(
    'ALL|AN|ANY',
    'BIGGR|BOTH',   
    'DIFF|DIFFRINT',
    'EITHER',

    'OF',
    'PRODUKT',
    'QUOSHUNT',
    'MOD|MKAY',
    'NOT',
    'SAEM|SMALLR|SUM',
    'THAN',
    'WON');
  public $operator_pattern = '/\b(%OPERATOR)\b/';
  public $keywords = array(
    'A',
    'DUZ',
    'I',
    'HAI',
    'KTHX(?:BYE)?',
    'HAS|HOW',
    'R',
    'I|IM|IN|IS|IT[ZS]|IF',
    'FOUND',
    'GTFO',
    'MAEK|MEBBE',
    'NO|NOW',
    'O|OIC|OMG|OMGWTF',
    'RLY\??',
    'SAY|SO',
    'TIL',
    'YA|YR',    
    'U',
    'WAI|WILE|WTF\?',
  );
  public $types = array(
    'FAIL',
    'NOOB',
    'NUMBAR|NUMBR',
    'TROOF',
    'WIN',
    'YARN',
  );
  public $functions = array(
    'GIMMEH', 'VISIBLE', 'UPPIN', 'NERFIN');
  public function __construct()
  {
    $this->SetInfoLanguage('lolcode');
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/(?s:OBTW.*?TLDR)|BTW.*/'),
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/".*?(?<!\:)(\:\:)*"/'),
      new LuminousDelimiterRule(3, 'ESC', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      '/:
      (?:
        (?:[\)o":]|&gt;)
        |\([a-fA-F0-9]*\)
        |\[[A-Z ]*\]
      )/x'),
      new LuminousDelimiterRule(3, 'VARIABLE_INTERP', LUMINOUS_COMPLETE
        |LUMINOUS_REGEX, '/:\{\w*\}/')      
      );
    $this->state_transitions = array(
      'GLOBAL' => '*',
      'STRING' => array('ESC', 'VARIABLE_INTERP'),
    );
    $this->state_type_mappings = array(
      'VARIABLE_INTERP' => 'VARIABLE'
    );
   

    
    $this->SetSimpleTypeRules();
    $this->simple_types[] = new LuminousSimpleRule(4, 'VARIABLE',
      LUMINOUS_REGEX, '/(?<!&)[A-Za-z]\w+/');
  }
  
  
}
