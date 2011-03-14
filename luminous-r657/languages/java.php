<?php
class LuminousGrammarJava extends LuminousGrammar
{
  public $keywords = array(
    'abstract|assert',
    'break',
    'case|class|continue|const',
    'default|do',
    'else',
    'final|finally|for',
    'goto',
    'if|implements|import|instanceof|interface',
    'native|new',
    'package|private|public|protected',
    'return',
    'static|strictfp|switch|synchronized',
    'this|throws?|transient',
    'volatile',
    'while');

  public $functions = array('super');

  public $types = array(
    'bool(ean)?|byte', 
    'char', 
    'double', 
    'enum', 
    'float', 
    'int',
    'long',
    'short',
    'Abstract(?:Collection|List|Map|SequentialList|Set)|Array(?:List|s)',
    'Array(?:List|s)',    
    'Big(?:Integer|Decimal)|BitSet|Boolean|Byte',
    'Calendar|Character|ClassCollection|Currency',
    'Date|Dictionary|Double',  
    'EventListenerProxy|EventObject|Exception',  
    'GregorianCalendar',
    'Hash(?:Map|Set|table)',
    'IdentityHashMap|Integer',
    'Float',
    'Linked(?:Hash(?:Map|Set)|List)|ListResourceBundle|Locale|Long',
    'Number',
    'Object|Observable',
    'Package|Properties|Property(?:Permission|ResourceBundle)',  
    'Random|ResourceBundle',  
    'SimpleTimeZone|Stack|StringTokenizer|String(?:Buffer)?|Short',
    'Timer(?:Task)?|TimeZone|Tree(?:Map|Set)|Thread',
    'Vector|Void',
    'WeakHashMap'
  
  );
  public $oo_separators = array('\.');
  function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('java');
    $this->SetInfoVersion('r657');        
    
    $this->delimited_types = array(
      
      luminous_generic_doc_comment('/**', '*/'),
      
      luminous_generic_comment('/*', '*/'),
      luminous_generic_comment_sl('//'),
      
      luminous_generic_string('"', '"'),
      
      new LuminousDelimiterRule(0, 'CHARACTER', 0, '\'', '\'',
      'luminous_type_callback_cstring')      
      
      );
    
    $this->simple_types[] = new LuminousSimpleRule(3, 'TYPE', LUMINOUS_COMPLETE
      |LUMINOUS_REGEX, '/(?:(?<=import)|(?<=package))(\s+)([\w\.\*]+)/', null, null, 2);
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    
      
  }
}