<?php
class LuminousGrammarRubyHTML extends LuminousGrammarRuby
{
  
  public function __construct()
  {

    
    $this->child_grammar = new LuminousGrammarJavaScriptEmbedded();
    $this->ignore_outside = array(
      new LuminousBoundaryRule(LUMINOUS_REGEX,
      "/&lt;%=?/", "/%&gt;/")
      
      );
      $this->keywords[] = "&lt;%=?";
      $this->keywords[] = "%&gt;";
      parent::__construct();
      
      $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
                           'website'=>'http://www.asgaard.co.uk'));
                           $this->SetInfoLanguage('rhtml');
                           $this->SetInfoVersion('r657');      
  }
}