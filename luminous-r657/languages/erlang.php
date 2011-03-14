<?php

/* 
 * Erlang for Luminous.
 */

class LuminousGrammarErlang extends LuminousGrammar
{
  
  public $keywords = array(
    '-module|-import|-export|-compile|-type|-spec|-file|-record',
    'after|and|andalso',
    'band|begin|bnot|bor|bsl|bsr|bxor',
    'case|catch|cond',
    'div',
    'end',
    'fun',
    'if',
    'let',
    'not',
    'of|or|orelse',
    'query',
    'receive|rem',
    'try',
    'when',
    'xor');
    
  public $types = array('false', 'true');
    
  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
                         'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('erlang');
    $this->SetInfoVersion('r657');      
    
    $this->delimited_types = array(
      // not a string.      
      luminous_generic_string("'", "'"),
      luminous_generic_string('"', '"'),
      luminous_generic_comment_sl('%'),
    );
    
    $this->simple_types[] = new LuminousSimpleRule(3, 'TYPE',
      LUMINOUS_REGEX, '/(?<=[\s,;:\(\[\{])[A-Z]\w*/');
    
    $this->SetSimpleTypeRules();
    $this->simple_types [] = new LuminousSimpleRule(3, 'NUMERIC',
      LUMINOUS_REGEX, '/\$(?:\\\)?/');
    $this->simple_types[] = new LuminousSimpleRule(3, 'NUMERIC',
      LUMINOUS_REGEX, '/\d+#\w+/');
  }
  
}
