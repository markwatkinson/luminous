<?php
class LuminousGrammarActionscriptEmbedded extends LuminousGrammarActionscript
{
  public function __construct()
  {
    parent::__construct();
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
          'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');
    
    $this->simple_types = array_merge(array(
      new LuminousSimpleRule(0, 'COMMENT', LUMINOUS_REGEX,
                             '/(?:&lt;!\[CDATA\[)|(?:\]\]&gt;)/')
      ),
      $this->simple_types
    );
    
    $this->ignore_outside = array(
      new LuminousBoundaryRule(LUMINOUS_REGEX|LUMINOUS_EXCLUDE, 
                               '/&lt;[\s]*mx:Script[\s]*&gt;/i',
                               '/&lt;[\s]*\/[\s]*mx:Script[\s]*&gt;/i')
    );
    
    $this->child_grammar = new LuminousGrammarHTML();
  }  
}