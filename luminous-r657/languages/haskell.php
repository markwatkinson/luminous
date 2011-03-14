<?php
class LuminousGrammarHaskell extends LuminousGrammar
{

  public $keywords = array("as", "case", "of", "class", "data", "family",
    "instance", "default", "deriving", "do", "forall", "foreign", "hiding",
    "if", "then", "else", "import", "infix", "infixl", "infixr", "let", "in",
    "mdo", "module", "newtype", "proc", "qualified", "rec", "type", "where");
    
  public $types = array("Integer", "Char", "String", "Float");
  
  // Haskell's philosophy seems to be "you've got all those keys on your
  // keyboard and I'm not going to waste them"
  public $operators = array('\+', '\*', '-', '\/', '=', '&gt;', '&lt;', '=',
  '!', '%', '&amp;', '\|', '~', '\^', '\[', '\]', '\(', '\)', '\.', ':', '@'
  );
  
  public function __construct()
  {
    
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('hs');
    $this->SetInfoVersion('r657');        
    
    
    
    $this->delimited_types = array(
      luminous_generic_string('"', '"'),
      new LuminousDelimiterRule(0, "CHARACTER", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        "/'(\\\\)?.'/"),
      new LuminousDelimiterRule(0, "FUNCTION", 0, "`", "`"),
      new LuminousDelimiterRule(0, "FUNCTION", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        "/\\\\\S+/"),
      luminous_generic_comment_sl('--'),
      
      new LuminousDelimiterRule(0, "COMMENT_", 0,
        '{-', '-}')
    );
    $this->state_transitions = array(
      'GLOBAL' => '*',
      'COMMENT_' => array('COMMENT_')
    );
    $this->state_type_mappings = array(
      'COMMENT_' => 'COMMENT'
    );
      
    $this->SetSimpleTypeRules();  
  }
  
}