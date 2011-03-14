<?php

/// \todo this could do with some work, but I don't know Pascal.
class LuminousGrammarPascal extends LuminousGrammar

{
  public $case_insensitive = true;
  
  public $operators = array('(?<![a-zA-Z0-9_])and(?![a-zA-Z0-9_])', 
  '(?<![a-zA-Z0-9_])div(?![a-zA-Z0-9_])', 
    '(?<![a-zA-Z0-9_])in(?![a-zA-Z0-9_])',    
    
    '(?<![a-zA-Z0-9_])sh[lr](?![a-zA-Z0-9_])',        
    '(?<![a-zA-Z0-9_])not(?![a-zA-Z0-9_])',  
    '(?<![a-zA-Z0-9_])or(?![a-zA-Z0-9_])',
    '(?<![a-zA-Z0-9_])mod(?![a-zA-Z0-9_])',
    
    '(?<![a-zA-Z0-9_])xor(?![a-zA-Z0-9_])',
    
    '\+', '\*', '-', '\/', '=', '&[gl]t;', '=','!', '%', '&amp;', '\|','~',
    '\^', '\[', '\]');
    
  public $keywords = array(
    'absolute|abstract|all|and_then|array|as|asm',
    'begin|bindable',
    'case|class|const|constructor',
    'destructor|dispose|do|downto',
    'else|end|except|exit|export|exports',
    'false|file|finalization|finally|for|function',
    'goto',
    'if|implementation|import|inherited|initialization|inline|interface|is',
    'label|library',
    'module',
    'new|nil',
    'object|of|on|only|operator|or_else|otherwise',
    'packed|pow|procedure|program|property|protected',
    'qualified',
    'raise|record|repeat|restricted',
    'set',
    'then|threadvar|to|true|try|type',
    'unit|until|uses',
    'value|var|view|virtual',
    'while|with',
    'xor');   
  
  
  public $types = array('String', 'Integer', 'Real', 'Boolean', 'Character',
    'Byte(?:Int)?', 'Short(?:Int|Word|Real)', 'Word', 'Med(?:Int|Word)', 
    'Long(?:est)?(?:Int|Word|Real)', 'Comp', 'Smallint', 'SizeType',
    'Ptr(?:DiffType|Int|Word)',
    'Int64', 'Cardinal', 'QWord', 'ByteBool', 'WordBool', 'LongBool', 'Char');
    
  // http://www2.toki.or.id/fpcdoc/ref/refse64.html#x151-15700013.3
  public $functions = array('abs|addr|append|arctan|assert|assign(:ed)?',
    'binstr|block(?:read|write)|break',
    'chdir|chr|close|compare(?:byte|char|d?word)|concat|copy|cos|cseg',
    'dec|delete|dispose|dseg',
    'eo(?:f|ln)|erase|exclude|exit|exp',
    'file(?:pos|size)|fill(?:byte|char|d?word)|flush|frac|freemem',
    'get(?:dir|mem|memorymanager)',
    'halt|hexstr|hi|high',
    'inc(?:lude)|index(?:byte|char|d?word)',
    'insert|ismemorymanagerset|int|ioresult',
    'length|ln|lo|longjmp|low|lowercase',
    'mark|maxavail|memavail|mkdir|move|movechar0',
    'new',
    'odd|octstr|ofs|ord',
    'paramcount|paramstr|pi|pos|power|pred|ptr',
    'random(?:ize)?|read(?:ln)?|real2double|release|rename|reset|rewrite|rmdir|round|runerror',
    'seek(?:eof|eoln)?|seg|setmemorymanager|setjmp|setlength|setstring|settextbuf|sin|sizeof|sptr|sqrt?|sseg|str|stringofchar|succ|swap',
    'trunc(?:ate)?',
    'upcase',
    'val',
    'write(?:ln)?');

  public function __construct()
  {
    $this->SetInfoLanguage('pascal');
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');          
    
    $this->delimited_types = array(
      luminous_generic_comment('{', '}'),
      luminous_generic_comment('(*', '*)'),
      luminous_generic_comment_sl('//'),
      
      luminous_generic_sql_string("'"),
      luminous_generic_sql_string('"')
    );
    
    $this->SetSimpleTypeRules();
    
  }
  
  
  
}