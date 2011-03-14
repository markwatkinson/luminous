<?php
class LuminousGrammarVisualBasic extends LuminousGrammar 
{
  
  
  public $operators = array("\+", "\-", "&amp;", "=", "\*", "\^", "\/",  
  "(?<![a-z0-9])and(also?)(?![a-z0-9])", "(?<![a-z0-9])or(else)?(?![a-z0-9])",
  '(?<![a-z0-9])not(?![a-z0-9])', '(?<![a-z0-9])xor(?![a-z0-9])'
  );


  public $types = array(
    "boolean",
    "byte",
    "c(?:bool|byte|char|date|dec|dbl|int|lng|obj|short|sng|str|type)",
    "char",
    "date",
    "decimal",
    "double",
    "enum",
    "false",
    "integer",
    "long",
    "short",
    "string",
    "true");
    
  // HOW MANY?
  public $keywords = array(
    "addhandler", "addressof", "alias", "ansi", "as", "assembly", "auto",
    "byref", "byval", "call", "case", "catch", "const", "declare", "default",
    "delegate", "dim", "directcast", "do", "each", "else(?:if)?", "end", "erase",
    "error", "event", "exit", "finally", "for", "friend", "function", 
    "get(?:type)?", "gosub", "goto", "handles", "if", "imports",
    "implements", "interface",
    "let", "Lib", "like", "loop", "me", "mod", "module", "mustinheret", 
    "mustoverride", "mybase", "myclass", "namespace", "new", "next", "nothing",
    "on", "option", "optional", "overloads", "overridable", "overrides",
    "paramarray", "preserve", "private", "property", "protected", "public", 
    "raiseevent", "readonly", "return", "select", "set", "shadows", "shared",
    "single", "static", "step", "stop", "structure", "sub", "synclock", "then",
    "throw", "to", "try", "typeof", "unicode", "until", "variant", "when", 
    "while", "with", "withevents", "writeonly"
  );
  
  
  
  public $escape_chars = array();
  
  

  public function __construct()
  {
    $this->case_insensitive = true;
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('vb');
    $this->SetInfoVersion('r657');        
    
    $this->delimited_types = array(
      luminous_generic_doc_comment_sl("'''"),
      luminous_generic_comment_sl("'"),
      new LuminousDelimiterRule(0, "STRING", 0, '"', '"'),
      new LuminousDelimiterRule(0, "PREPROCESSOR", 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE, "/^#.*?(?=($|\"))/m")
    );
    
    $this->SetSimpleTypeRules();
    
    $this->simple_types[] =  new LuminousSimpleRule(3, 'CONSTANT', 
      LUMINOUS_REGEX,
      '/(?<![[:alnum:]_&<])(?-i:\b[A-Z_][A-Z0-9_]{2,})(?![[:alnum:]_])/');
    
  }
  
}