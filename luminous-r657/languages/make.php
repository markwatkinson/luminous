<?php
class LuminousGrammarMakefile extends LuminousGrammarBash
{
  function __construct()
  {
    
    $this->keywords[] = "endif";
    $this->keywords[] = "ifdef";
    
    // dependency
    $this->simple_types[] = new LuminousSimpleRule(3, 'MAKE_DEP', LUMINOUS_REGEX,
    "/(^[[:alnum:]_\-\. ]+?:)((?:=$)|(?:.*?[^\\\\](?>\\\\\\\\)*$))/ms", null, 2);
     /* [^\\\n]+|(?>\\)*\\\\n)*$)/m", null, 2); */
      
//     // Makefile make Rule.
    $this->simple_types[] = 
      new LuminousSimpleRule(3, 'MAKE_TARGET', LUMINOUS_REGEX, 
        '/^[[:alnum:]_\-\. \t]+?(?=:)/m');
    parent::__construct();
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');
        
  }
  
  
}