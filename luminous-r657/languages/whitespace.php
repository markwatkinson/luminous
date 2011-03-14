<?php

class LuminousGrammarWhitespace extends LuminousGrammar
{
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('whitespace');
    $this->SetInfoVersion('r657');
    $this->simple_types[] = 
    new LuminousSimpleRule(0, 'COMMENT', LUMINOUS_REGEX, '/[^\s]+/');
    $this->simple_types[] = 
    new LuminousSimpleRule(0, 'WHITESPACE_SPACE', 0, ' ');
    $this->simple_types[] = 
    new LuminousSimpleRule(0, 'WHITESPACE_TAB', 0, "\t");
  }
  
}