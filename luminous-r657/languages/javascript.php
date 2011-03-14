<?php
class LuminousGrammarJavaScript extends LuminousGrammar
{
  public $keywords =  array("break", "c(?:ase|atch|omment|ontinue)", 
    "d(?:efault|elete|o)", "e(?:lse|xport)",
    "f(?:or|unction)", "i(?:f|mport|n)",   "label", "return",  "switch",  "throw|try",  
    "var|void", "w(?:hile|ith)");    
  
  public $functions = array('\$', 
    'alert',
    'confirm',
    'encodeURI(?:Component)?|eval',
    'is(?:Finite|NaN)',
    'parse(?:Int|Float)|prompt',
    'decodeURI(?:Component)?',
    'jQuery'
    );
  
  public $types = array(
    'Array',
    'Boolean',
    'Date',
    'Error|EvalError',
    'Infinity|Image',
    'Math',
    'NaN|Number',
    'Object|Option',
    'R(?:ange|eference)Error|RegExp',
    'String|SyntaxError',
    'TypeError',
    'URIError',
    'false',
    'this|true',
    'undefined');
  

  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('js');
    $this->SetInfoVersion('r657');    
    
    $this->operators = array_merge($this->operators,
    array('\?', '\:', '(?<![a-zA-Z0-9_])delete(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])get(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])in(stanceof)?(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])let(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])new(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])set(?![a-zA-Z0-9_])',
    '(?<![a-zA-Z0-9_])typeof(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])void(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])yield(?![a-zA-Z0-9_])'));
      
//     $this->operators = array();
    
    $this->delimited_types = array(
      luminous_generic_doc_comment('/**', '*/'),
      luminous_generic_doc_comment('/*!', '*/'),
      new LuminousDelimiterRule(0, 'DOCCOMMENT', 
        LUMINOUS_STOP_AT_END|LUMINOUS_REGEX, 
        '%///(?!/)%', '/$/m'),
      new LuminousDelimiterRule(0, 'DOCCOMMENT', 
        LUMINOUS_STOP_AT_END|LUMINOUS_REGEX, 
        '%//!(?!\!)%', '/$/m'),   
      luminous_generic_comment('/*', '*/'),
      new LuminousDelimiterRule(0, 'COMMENT', 
        LUMINOUS_STOP_AT_END|LUMINOUS_REGEX, 
        '%//%', '/$/m'),
        
        
      luminous_generic_string('"', '"'),
      luminous_generic_string("'", "'"),
      luminous_generic_regex('igm')

    );

    $this->simple_types[] = new LuminousSimpleRule(4, 'USER_FUNCTION',
      LUMINOUS_REGEX, '/(\bfunction\s+)(\w+)/', null, 2);

    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    $this->simple_types[] = new LuminousSimpleRule(4, 'OBJ', LUMINOUS_REGEX,
      '/(?>[\$\w]+)(?=\.)/');
    $this->simple_types[] = new LuminousSimpleRule(4, 'OO', LUMINOUS_REGEX,
      '/(?<=\.)[\$\w]+/');
    
  }
  
  
}


class LuminousGrammarJavaScriptEmbedded extends LuminousGrammarJavaScript
{

  function __construct()
  {    
    $this->ignore_outside = array(
      new LuminousBoundaryRule(LUMINOUS_REGEX|LUMINOUS_EXCLUDE,
      "/(?:&lt;script.*?&gt;)/si", "/(?:&lt;\/?script&gt;)/si")
      );    
    $this->child_grammar = new LuminousGrammarCSSEmbedded();
    
    parent::__construct();
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');          
    
  }
}
