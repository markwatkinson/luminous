<?php
class LuminousGrammarCSharp extends LuminousGrammar
{
  public $keywords = array(
    'abstract|as',
    'base|break',
    'case|catch|checked|class|continue',
    'default|delegate|do',
    'event|explicit|extern|else',
    'finally|false|fixed|for|foreach',
    'goto',
    'if|implicit|in|interface|internal|is',
    'lock',
    'new|null|namespace',
    'object|operator|out|override',
    'params|private|protected|public',
    'readonly|ref|return',
    'struct|switch|sealed|sizeof|stackalloc|static',
    'this|throw|true|try|typeof',
    'unchecked|unsafe|using',
    'var|virtual|volatile',
    'while',
    'yield',
  );
  
  public $types = array(
    'bool|byte',
    'char|const',
    'double|decimal',
    'enum',
    'float',
    'int',
    'long',
    'sbyte|short|string',
    'uint|ulong|ushort',
    'void',
    // system namespace
    'Array|Attribute',
    'Byte|Buffer',
    'Char',
    'DateTime|Double',
    'EventArgs|eventHandler|Exception',
    'Object', 
    'String', 
    'Tuple|Type',
    'U?Int(?:16|32|64|Ptr)',
    // system.collection namespace
    'ArrayList',
    'Comparer',
    'Dictionary',
    'Hashtable|Hashset',
    'IComparer|IList|ISet',
    'Queue',
    'Sortedlist|Stack',
    
    //System.IO
    'BinaryReader|BinaryWriter|BufferedStream',
    'Directory(?:Info|NotFoundException)?|Drive(?:Info|NotFoundException)?',
    'EndOfStreamException|ErrorEvent(?:Args|Handler)', 
    "File(?:Access|Attributes|FormatException|Info|LoadException|Mode|NotFoundException|Options|Share|Stream|SystemEventArgs|SystemEventHandler|SystemInfo|SystemWatcher)?", 
    'HandleInheritability', 
    'InternalBufferOverflowException|InvalidDataException|IODescriptionAttribute|IOException', 
    'MemoryStream", 
    "NotifyFilters", 
    "Path(?:TooLongException)?|PipeException', 
    'RenamedEventArgs|RenamedEventHandler', 
    'SearchOption|SeekOrigin|Stream(?:Reader|Writer)?|String(?:Reader|Writer)', 
    'Text(?:Reader|Writer)', 
    'UnmanagedMemory(?:Accessor|Stream)', 
    'WaitForChangedResult|WatcherChangeTypes',
    
    // module things
    'System(?:[\w\.]*)?',
    'Accessibility',
    'Microsoft(?:[\w\.]*)?',
    'UIAutomationClientsideProviders',
    'XamlGeneratedNamespace'
  );
  
  public $functions = array("get", "set");
  
  public $oo_separators = array('\.');
  
  function __construct()
  {
    
    
    $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
      'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('cs');
    $this->SetInfoVersion('r657');    
    
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'PREPROCESSOR', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      '/#.*?[^\\\](\\\\)*$/sm'),
      luminous_generic_doc_comment('/**', '*/'),
      luminous_generic_doc_comment('/*!', '*/'),
      luminous_generic_doc_comment_sl('//!'),
      luminous_generic_doc_comment_sl('///'),
      
      luminous_generic_comment('/*', '*/'),
      luminous_generic_comment_sl('//'),
      luminous_generic_string('"', '"'),
      
      new LuminousDelimiterRule(0, 'CHARACTER', 0, '\'', '\'',
      'luminous_type_callback_cstring'),
    );
    
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    
  }

}
