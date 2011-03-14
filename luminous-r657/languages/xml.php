<?php
class LuminousGrammarHTMLText extends LuminousGrammar
{  
  function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('htmltext');
    $this->SetInfoVersion('r657');        
    
    $this->simple_types = array(
      new LuminousSimpleRule(4, 'ESC', LUMINOUS_REGEX, 
      "/(?:&amp;)(?:[a-z]+|(#[0-9]+));/i")
      );
  }  
}


class LuminousGrammarHTML extends LuminousGrammar
{
 function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('html');
    $this->SetInfoVersion('r657');        
    
    $this->ignore_outside = array( 
    new LuminousBoundaryRule(0, "&lt;", "&gt;")
    );
    $this->child_grammar = new LuminousGrammarHTMLText();
    
    $test = true;
    
    $this->delimited_types = array(    
    new LuminousDelimiterRule(0, "COMMENT", LUMINOUS_END_IS_END,
    "&lt;!--", "--&gt;"),
    new LuminousDelimiterRule(0, "COMMENT", LUMINOUS_END_IS_END|LUMINOUS_REGEX,
      "/&lt;!(?!(?i)DOCTYPE)/", "/&gt;/")
    );

    $this->delimited_types[] = new LuminousDelimiterRule(0, "STRING", 
      LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      "/
        (?:'')
        |
        (?:'.*?(?:'|(?=&[lg]t;)))
      /xs");
    
    $this->delimited_types[] = new LuminousDelimiterRule(0, "STRING", 
    LUMINOUS_REGEX|LUMINOUS_COMPLETE,
    '/
    (?:"")|
    (?:".*?(?:"|(?=&gt;)))
    /xs');  

      
    $this->simple_types = array();      
      
    $this->simple_types[] = new LuminousSimpleRule(2, "TYPE", LUMINOUS_REGEX,
      "/(?<=([\s]))(?:[a-z\-:]+)(?=([=]))/i");
    $this->simple_types[] = new LuminousSimpleRule(1, "HTMLTAG", LUMINOUS_REGEX,
      "/(?<=(&lt;))(\/?)[a-z0-9_\-\:]+/i");
    $this->simple_types[] = new LuminousSimpleRule(1, "CONSTANT", 
      LUMINOUS_REGEX, "/(?<=(&lt;))[!?][a-z0-9_\-\:]+/i");    
    $this->simple_types[] = new LuminousSimpleRule(2, 'VALUE', LUMINOUS_REGEX,
      "/(?<=(=))[\s]*(?![\"'\s])[^\s]+/");
  }
}