<?php
class LuminousGrammarCpp extends LuminousGrammar
{    
  public $keywords = array('asm|auto', 'break', 
    'case|catch|class|continue|const(?:_cast)?', 
    'default|delete|do|dynamic_cast', 'else|explicit|extern', 'for|friend', 
    'goto', 'if|inline', 'mutable', 'namespace|new', 'operator', 
    'private|protected|public', 'register|reinterpret_cast|return', 
    'static(?:_cast)?|switch|sizeof|signed', 
    'template|this|throw|try|typedef|typeid|typename', 
    'union|using|unsigned', 'virtual|volatile', 'while');
  
  public $types =  array('FALSE','FILE',
    'NULL', 'TRUE', 'bool', 'char|clock_t', 'double|div_t', 
    'enum', 'false|float|fpos_t', 'int(?:(?:8|16|32|64)_t)?',     
    'long|ldiv_t', 'short|struct', 
    'ptrdiff_t', 'true|time_t', 
    'union', 'uint(?:8|16|32|64)_t',
    'void|va_list', 'wchar_t', 'size_t',
    // C++ std stuff
    'pair', 'vector', 'list', 'deque', 'queue', 'priority_queue',
    'stack', 'set', 'string', 'multiset', 'map', 'multimap', 
    'hash_(?:set|multiset|map|multimap)', 'bitset', 'valarray', 'iterator'
    );
    
  public $oo_separators = array('\.', '-&gt;', '::');


  public $functions = array(
  
  //ctype.h
  'is(?:alnum|alpha|blank|cntrl|digit|graph|lower|print|punct|space|upper|xdigit)',
  'to(?:upper|lower)',
  
  //stdlib.h,  
  'ato[fil]', 'strto(?:[dl]|ull?|ll)', 'rand(?:om)?', 'srand(?:om)?', 'malloc',
  'calloc', 'realloc', 'free', 'abort', 'atexit', 'exit',  'getenv', 'system',
  'bsearch', 'max', 'min', 'qsort', 'abs', 'fabs', 'labs', 'div', 'ldiv',
  'mblen', 'mbtowc', 'wctomb', 'mbstowcs', 'wcstombs', 'itoa',
  
  // stdio.h
  'fclose',   'fopen',  'freopen',  'remove',  'rename',  'rewind',  'tmpfile',
  'clearerr',  'feof',  'ferror',  'fflush',  'fgetpos',  'fgetc',  'fgets',
  'fputc',  'fputs',  'ftell',  'fseek',  'fsetpos',  'fread',  'fwrite',
  'getc',  'getchar',  'gets',  'printf',  'vprintf',  'fprintf',  'vfprintf',
  'sprintf',  'snprintf',  'vsprintf',  'vsnprintf',  'perror',  'putc',
  'putchar',  'fputchar',  'scanf',  'vscanf',  'fscanf',  'vfscanf',
  'sscanf',  'vsscanf',  'setbuf',  'setvbuf',  'tmpnam',  'ungetc',  'puts',
  
  
  //string.h
  'str(?:cat|chr|cmp|coll|cpy|cspn|dup|fry|len|ncat|ncmp|ncpy|pbrk|rchr|sep|spn|str|tok|xfrm)'
  );
  
  public function __construct()
  {
    $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
        'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('cpp');
    $this->SetInfoVersion('r657');
    
    
    $this->delimited_types = array(
      luminous_generic_doc_comment('/**', '*/'),
      luminous_generic_doc_comment('/*!', '*/'),
      luminous_generic_doc_comment('//!', "\n"),
      luminous_generic_doc_comment('///', "\n"),
      
      luminous_generic_comment('/*', '*/'),
      luminous_generic_comment('//', "\n"),
      luminous_generic_comment('#if 0', "#endif"),
      
      luminous_generic_string('"', '"'),
      
      new LuminousDelimiterRule(0, 'CHARACTER', 0, '\'', '\'',
        'luminous_type_callback_cstring'),
      new LuminousDelimiterRule(0, 'PREPROCESSOR', 0, '#', "\n")
    ); 
    
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
  }
}