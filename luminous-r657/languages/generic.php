<?php

/***************************************************************************
 * A supposedly generic grammar to prove minimal highlighting to anything 
 * vaguely C-like
 * 
 */

class LuminousGrammarGeneric extends LuminousGrammar
{
  
  public $keywords = array(    
    'assert', 'abstract',
    'begin', 'break',
    'case', 'catch', 'class', 'continue', 'const',
    'def', 'default', 'do',
    'each', 'else(?:if)?', 'end', 'extends?',
    'final(?:ly)?', 'for(?:each)?', 'function',
    'global', 'goto',
    'if', 'import', 'inherits?',
    'lambda', 'let', 'local', 'loop',
    'namespace', 'new',
    'package', 'private', 'protected', 'public',
    'return', 'require',
    'static', 'switch', 'signed',
    'then', 'this', 'throw', 'try', 'type',
    'us(?:e|ing)', 'unsigned',
    'with', 'when', 'while',
    'var'
  );
  
  public $types = array(
    '[aA]rray',
    '[bB]yte',
    '[bB]ool(?:ean)?', 
    '[cC]har(?:acter)?', 
    '[dD]ouble',
    '[eE]num',
    '[fF]loat',
    '[iI]nt(?:eger)?\d*',
    '[lL](?:ist|ong)', 
    '[sS](?:hort|tring)',
    '[uU](?:nion|int(?:eger)?\d*|long|short)',
    '[vV]oid',
    '(?i:true|false|nil|null)'
    );
    
    
  function LuminousGrammarGeneric()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
          'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('generic');
    $this->SetInfoVersion('r657');    
    
    $this->delimited_types = array(
      luminous_generic_comment_sl('//'),
      luminous_generic_comment_sl('#'),
      luminous_generic_comment_sl('--'),    
      new LuminousDelimiterRule(0, 'COMMENT', 0, '/*', '*/'),
      new LuminousDelimiterRule(0, 'COMMENT', 0, '(*', '*)'),
      new LuminousDelimiterRule(0, 'STRING', 0, '"', '"'),
      new LuminousDelimiterRule(0, 'STRING', 0, "'", "'")
    );
    
    
    
    $this->operators = array_merge($this->operators,
      array(
        '\band\b',
        '\bin\b',
        '\bis\b',
        '\bgte?\b',
        '\blte?\b',
        '\bnot\b',
        '\bor\b',
        '\bxor\b'
      )
    );
      
      
    
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    
  }
    
    
    
    
  
  
}