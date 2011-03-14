<?php
class LuminousGrammarPlain extends LuminousGrammar
{
  
  function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
                         'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('plain');
    $this->SetInfoVersion('r657');      
    
    $this->simple_types = array(
      new LuminousSimpleRule(0, 'COMMENT', LUMINOUS_REGEX,
      '/^[\s]*[#;].*$/m'),

      new LuminousSimpleRule(3, 'VALUE', LUMINOUS_REGEX,
      '/(^[[:alnum:]_ \t]+?=)(.*)/m', null, 2),
      new LuminousSimpleRule(2, 'VARIABLE', LUMINOUS_REGEX,
      '/^[[:alnum:]_ \t]*?(?=[=])/m'),
      new LuminousSimpleRule(2, 'KEYWORD', LUMINOUS_REGEX,
      '/^([ \t]*)(\[)(.+?)(\])/im'),
      new LuminousSimpleRule(4, 'OPERATOR', LUMINOUS_REGEX, '/(?!<[=]|^)=(?!=)/')
      
      );
  }
  
  public function SetRuleset(&$str)
  {
    
  }
  
}