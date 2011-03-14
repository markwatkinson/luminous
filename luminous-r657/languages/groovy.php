<?php


/* 
 * Groovy. Based on Java.
 * 
 */

class LuminousGrammarGroovy extends LuminousGrammarJava
{
  public function __construct()
  {
    
    $keywords = array('def', 'it', 'as');
    $operators = array('\bi[ns]\b');
    
    $this->keywords = array_merge($this->keywords, $keywords);
    $this->operators = array_merge($this->operators, $operators);
    
    parent::__construct();
    
    $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 
            'email'=>'markwatkinson@gmail.com',
            'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('groovy');
    $this->SetInfoVersion('r657');     
    
    
    // Java defines a 'x' to be a char, but in Groovy it's a string.
    // it also does something different with the strings.
    
    foreach($this->delimited_types as $k=>&$d)
    {
      if ($d->name == "CHARACTER" || $d->name == "STRING")
      {
        unset($this->delimited_types[$k]);       
      }
    }
    
    // It also uses a triple quote as a string marker, like python.
    // And there are regex literals.
    $this->delimited_types = array_merge(
      array(
        new LuminousDelimiterRule(0, 'STRING', 0, "'''", "'''",
          'luminous_type_callback_cstring'),
        new LuminousDelimiterRule(0, 'STRING', 0, "'", "'",
          'luminous_type_callback_cstring'),            
        new LuminousDelimiterRule(0, 'STRING_', 0, '"', '"', 
          'luminous_type_callback_cstring'),
        new LuminousDelimiterRule(0, 'INTERP', 0, '${', '}'),
              
        luminous_generic_regex(),
        luminous_generic_shebang()
      ),
      $this->delimited_types
    );

  $this->state_transitions = array(
    'GLOBAL' => array('STRING', 'STRING_', 'DOCCOMMENT', 'COMMENT', 'REGEX', 
      'SHEBANG'),
    'STRING_' => array('INTERP'),
    'INTERP' => array('STRING', 'STRING_')
  );
  $this->state_type_mappings = array(
    'STRING_' => 'STRING',
    'INTERP' => 'VARIABLE',
  );
  }
}