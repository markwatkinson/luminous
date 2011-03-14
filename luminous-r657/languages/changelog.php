<?php
class LuminousGrammarChangelog extends LuminousGrammar
{
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
          'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('changelog');
    $this->SetInfoVersion('r657');
    
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(3, 'VALUE', LUMINOUS_REGEX,
      "/^[ \t]+[*\-\+][ \t]/m", "/$|(?=\n[ \t]*\n)|(?m:(?=\n\w))/"
      )
    );
      
    
    $this->simple_types = array(
      // Changelog heading
      new LuminousSimpleRule(0, 'KEYWORD', LUMINOUS_REGEX,
        "/^[a-zA-Z0-9_]+.*$/m"),
      // --
      new LuminousSimpleRule(0, 'COMMENT', LUMINOUS_REGEX,
        "/^[\s]*\-\-(?!\-).*$/m"),
      // subheading, if present
      new LuminousSimpleRule(1, 'TYPE', LUMINOUS_REGEX,
        '/^[\s]+.*:[ \t]*$/m')
    );
  }  
}