<?php

/** 
 * \example languages/cpp_stateful.php an example of a C++ grammar implemented 
 * in a stateful manner
 */

class LuminousGrammarCpp extends LuminousGrammar
{
  public $keywords = array('asm|auto', 'break', 
    'case|catch|class|continue|const(?:_cast)?|connect', 
    'default|delete|do|dynamic_cast', 'else|explicit|extern', 
    'for(?:_?each)?|friend', 
    'goto', 'if|inline', 'mutable', 'namespace|new', 'operator', 
    'private|protected|public', 'register|reinterpret_cast|return', 
    'static(?:_cast)?|switch|sizeof|signed', 
    'template|this|throw|try|typedef|typeid|typename', 
    'using|unsigned', 'virtual|volatile', 'while',
    'SIGNAL|SLOT'
  );

  
  public $types =  array('FALSE','FILE',
    'NULL', 'TRUE', 'bool', 'char|clock_t', 'double|div_t', 
    'enum', 'false|float|fpos_t', 'int_?(?:(?:8|16|32|64)(?:_t)?)?',     
    'long|ldiv_t', 'short|struct|size_t',
    'ptrdiff_t', 'true|time_t', 
    'union|uint_?(?:(?:8|16|32|64)(?:_t)?)?',
    'void|va_list', 'wchar_t',
    
    // C++ std stuff    
    'pair', 'list', 'deque', 'queue', 'priority_queue',
    'set|stack|string', 'map|multi(?:set|map)',
    'hash_(?:set|multiset|map|multimap)', 'bitset', 'vector|valarray', 'iterator'
    );
  
  // x.y, x->y and x::y
  public $oo_separators = array('\.', '-&gt;', '::');


  public $functions = array(
  
  //ctype.h
  'is(?:alnum|alpha|blank|cntrl|digit|graph|lower|print|punct|space|upper|xdigit)',
  'to(?:upper|lower)',
  
  //stdlib.h,  
  'ato[fil]|abort|atexit|abs', 
  'strto(?:[dl]|ull?|ll)|srand(?:om)?|system', 
  'rand(?:om)?|realloc', 
  'malloc|max|mblen|mbtowc|mbstowcs|min',
  'calloc', 
  'fabs|free', 
  'exit',  'getenv', 
  'bsearch',  'qsort', 'fabs', 'labs|ldiv', 'div', 
  'wctombs?', 'itoa',
  
  // stdio.h
  'fclose|fopen|freopen|feof|ferror|fflush|fgetpos|fgetc|fgets|fputc|fputs|ftell|fseek|fsetpos|fread|fwrite|fscanf|fputchar|fprintf',  'remove|rename|rewind', 'tmpfile|tmpnam',
  'clearerr',  
  'getc|getchar|gets',
  'vprintf|vfprintf|vsprintf|vsnprintf|vscanf|vfscanf|vsscanf',
  
  'perror|printf|putc|putchar|puts',
  'sprintf|snprintf|scanf|sscanf|setbuf|setvbuf',  
  'ungetc',
  
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
    
    $this->state_transitions = array(
      'GLOBAL'=> array('DOCCOMMENT', 'COMMENT', 'STRING', 'CHARACTER',
        'PREPROCESSOR', 'KEYWORD', 'NUMERIC', 'TYPE', 'OPERATOR'
      ),
      'INCLUDE' => array('STRING', 'INCLUDE_FILE', 'COMMENT'),
      'PREPROCESSOR' => array('INCLUDE', 'STRING', 'COMMENT'),
    );
    
    $this->state_type_mappings = array(      
      'INCLUDE' => null,
      'INCLUDE_FILE' => 'STRING'
      );
    
    
    $this->delimited_types = array(
      
      new LuminousDelimiterRule(0, 'DOCCOMMENT', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '%/\*[\*!].*?\*/%s', null, 'luminous_type_callback_doccomment'),
        
      new LuminousDelimiterRule(0, 'DOCCOMMENT', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '%//(?:/(?!/)|!).*$%m', null, 'luminous_type_callback_doccomment'),
        
      new LuminousDelimiterRule(0, 'COMMENT', 0,
        '/*', '*/', 'luminous_type_callback_comment'),
      
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '%//.*$%m', null, 'luminous_type_callback_comment'),
        
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '%#if\s+0\s+.*?#endif%m', null, 'luminous_type_callback_doccomment'), 
      
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/".*?(?<!\\\\)(?:\\\\\\\\)*"/', null, 
        'luminous_type_callback_cstring'),
        
      new LuminousDelimiterRule(0, 'CHARACTER', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        "/'.*?(?<!\\\\)(?:\\\\\\\\)*'/", null, 'luminous_type_callback_cstring'
      ),
        
      new LuminousDelimiterRule(0, 'INCLUDE', LUMINOUS_REGEX|LUMINOUS_CONSUME,
        "/include\s+/i", '/$/m'),
      new LuminousDelimiterRule(0, 'INCLUDE_FILE', 
        LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/(?<=&lt;).+?(?=&gt;)/'),
        
      new LuminousDelimiterRule(0, 'PREPROCESSOR', 
        LUMINOUS_REGEX|LUMINOUS_CONSUME,
        "/^[ \t]*#/m", "/$/m"),
   
    );
    
    

    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
  }
}
