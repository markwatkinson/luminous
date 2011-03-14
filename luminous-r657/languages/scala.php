<?php

/*
 * Scala language
 * www.scala-lang.org/docu/files/ScalaReference.pdf
 * 
 * TODO: Definitely need some global function names, but I haven't
 * found a good reference for them. 
 */

class LuminousGrammarScala extends LuminousGrammar
{
  
  public $keywords = array(
    'abstract',
    'case|catch|class',
    'def|do',
    'else|extends',
    'false|final|finally|for|forSome',
    'if|implicit|import',
    'lazy',
    'match',
    'new',
    'null',
    'object|override',    
    'package|private|protected',
    'return',
    'sealed|super',
    'this|throw|trait|try|true|type',
    'val|var',
    'while|with',
    'yield');
  
  public $types = array(
    'Boolean|Byte',
    'Char',
    'Double',
    'Float',
    'Long',
    'Int', 
    'Short|String',
    'Unit',
    'Void',
    
    'boolean|byte',
    'char',
    'double',
    'float',
    'int',
    'long',
    'short',
    'unit',
    'void',
    
    "'\w+"
  );
    
  
  public function __construct()
  {
    $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 
            'email'=>'markwatkinson@gmail.com',
            'website'=>'http://www.asgaard.co.uk')
    );
    $this->SetInfoLanguage('scala');
    $this->SetInfoVersion('r657');
    
    $this->delimited_types = array(
      luminous_generic_comment_sl('//'),
      // nested comments
//       new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
//         '%/\* ( .*? (?R)* .*?)* \*/%sx'),
      new LuminousDelimiterRule(0, 'COMMENT_', 0,
        '/*', '*/'),        
      luminous_generic_string('"""', '"""'),
      luminous_generic_string('"', '"'),
      
      // this is an 'xml literal'
      // TODO: this should be a boundary rule, but Luminous doesn't yet
      // support LUMINOUS_COMPLETE in boundary rules so we can't assess
      // where the XML ends.
      new LuminousDelimiterRule(0, 'TYPE', LUMINOUS_COMPLETE|LUMINOUS_REGEX,      
        '/(?<=[\s\{\(])&lt;(?:!\?)?(\w+).*?&lt;\/?\\1&gt;/s'),
      
    );
    
    $this->state_transitions = array(
      'GLOBAL' => '*',
      'COMMENT_' =>array('COMMENT_')
    );
        $this->state_type_mappings = array(
      'COMMENT_' => 'COMMENT'
    );
    $this->SetSimpleTypeRules();
  }
  
}