<?php
/* 
 * I don't actually know ActionScript so there's definitely room for improvement
 * in this one
 */
class LuminousGrammarActionscript extends LuminousGrammar
{
  public $keywords = array('as',
  'break',
  'case|catch|class|const|continue',  
  'default|delete|do',
  'else|extends',
  'false|finally|for|function',
  'if|impements|import|in|instanceof|interface|internal|is',
  'native|new|null',
  'package|private|protected|public',
  'return',
  'super|switch|static',
  'this|throw|to|true|try|typeof',
  'use',
  'void',
  'while|with',  
  );
  
  public $functions = array('add', 'chr', 'clearInterval', 'escape', 'eval', 
    'evaluate', 'fscommand', 'getProperty', 'getTimer', 'getVersion', 
    'globalStyleFormat', 'gotoAndPlay', 'gotoAndStop', 'ifFrameLoaded', 
    'instanceOf', 'isFinite', 'isNaN', 'loadMovie(?:Num)?', 'loadVariables', 
    'mb(?:chr|length|ord|substring)', 'next(?:Frame|Scene)', 'onClipEvent', 
    'ord', 'parseFloat', 'parseInt', 'play', 'prev(?:Frame|Scene)', 
    'print(?:AsBitmap(?:Num)?)?', 'printNum', 'random', 'scroll', 'setInterval',
    'setProperty', 'stop(?:Drag)?', 'substring', 'super', 'targetPath', 
    'tellTarget', 'toString', 'toggleHighQuality', 'trace', 'unescape');
  
  public $types = array('Accessibility', 'Array', 'Arguments', 'Boolean', 
    'Button', 'ByteArray', 'Camera', 'Color', 'Date', 'Event', 'FScrollPane', 
    'FStyleFormat', 
    'Function', 'int', 'Key', 'LoadVars', 'LocalConnection', 'Math',
    'Microphone', 'Mouse', 'Movieclip', 'Number', 'Object', 'Selection', 
    'Sound', 'Sprite', 'String', 'System', 'Text(?:Field|Format)', 
    'Timer(?:Event)?', 'uint', 'var',  'void', 'XML');
  
  public $oo_separators = array('\.');
  function __construct()
  {
    $this->delimited_types = array(
      luminous_generic_doc_comment('/**', '*/'),
      luminous_generic_comment_sl('//'),      
      luminous_generic_comment('/*', '*/'),
      luminous_generic_string('"', '"'),
      luminous_generic_string("'", "'"),
      luminous_generic_regex('gimsx'),
      new LuminousDelimiterRule(0, 'PREPROCESSOR',
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/#(include\s|initclip|endinitclip).*$/m')
    );
    
    $this->SetInfoLanguage("as");
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');          
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
  }
}