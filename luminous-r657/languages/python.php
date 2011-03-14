<?php

class LuminousGrammarPython extends LuminousGrammar
{
  public $operators = array(
    '\band\b', 
    '\bnot\b',
    '\bi[ns]\b',
    '\bor\b',
    '\+', '\*', '-', '\/', '=', '&[gl]t;', '!', '%', '&amp;', '\|','~',
    '\^', '\[', '\]');
    
  public $keywords = array(
    'asert|as',
    'break',
    'class|continue',
    'del|def',
    'elif|else|except|exec',
    'finally|for|from',
    'global',
    'if|import',
    'lambda',
    'print|pass',
    'raise|return',
    'try',    
    'while',
    'yield'
    );
  
  // okay so these aren't really types but it makes it more colourful.
  public $types = array('Ellipsis', 'False', 'None', 'True',
    'buffer',
    'complex',
    'dict',
    'frozenset',
    'int|iter',
    'float',
    'long|list',
    'memoryview',
    'self|set|str',
    'tuple'
  );
  
  public $functions = array(
    'all|abs|any',
    'basestring|bin',
    'callable|chr|classmethod|cmp|compile',
    'dir|divmod',
    'enumerate|eval|execfile',
    'file|filter|format|frozenset',
    'getattr|globals',
    'hasattr|hash|help|hex',
    'id|input|is(?:instance|subclass)|iter',
    'len|locals',
    'map|max|min|memoryview',
    'next',
    'object|oct|open|ord',
    'pow|print|property',
    'range|raw_input|reduce|reload|repr|reversed|round',
    'setattr|slice|sorted|staticmethod|sum|super',
    'type',
    'unichar',
    'vars',
    'xrange',
    'zip',
    '__import__'
    );
    
  public $oo_separators = array('\.');
  
  
  // hurray for languages with easy to find grammars
  // http://docs.python.org/reference/lexical_analysis.html#numeric-literals
  // also: EPIC.
  public $numeric_regex = '
  /(?<![[:alnum:]_<$])
  (
    #hex
    (?:0[xX](?>[0-9A-Fa-f]+)[lL]*)
    |
    # binary
    (?:0[bB][0-1]+)
    |
    #octal
    (?:0[oO0][0-7]+)
    |
    # regular number
    (?:
      (?>[0-9]+)
      (?: 
        # long identifier
        [lL]
        |
        # Or a fractional part, which may be imaginary
        (?:
          (?:\.?(?>[0-9]+)?        
            (?:(?:[eE][\+\-]?)?(?>[0-9]+))?
          )[jJ]?
        )
      )?
    )
    |
    (
      # or only after the point, float x = .1;
      \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?(?>[0-9]+))?[jJ]?
    )
  )
  /x';
  
  
    
  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
          'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('py');
    $this->SetInfoVersion('r657');    
    
    $this->delimited_types = array(
      luminous_generic_shebang(),
      new LuminousDelimiterRule(0, 'HASH_COMMENT', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/#.*(?=$)/m'),
      
      new LuminousDelimiterRule(0, 'DOCCOMMENT', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/^[ \t]*("""|\'\'\').*?(?:(?<!\\\\)(?:\\\\\\\\)*\\1|\z)/sm'),
        
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/^[ \t]*("|\').*?(?<!\\\\)(?:\\\\\\\\)*\\1/ms'),   
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
        '/[ruRU]{0,2}("""|\'\'\').*?(?:(?<!\\\\)(?:\\\\\\\\)*\\1|$)/s', null, 
        'luminous_type_callback_pystring'),
        
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
        '/[ruRU]{0,2}("|\').*?(?<!\\\\)(?:\\\\\\\\)*\\1/s', null, 
        'luminous_type_callback_pystring'),      
      
      
      new LuminousDelimiterRule(3, 'IMPORT_LINE', 
        LUMINOUS_REGEX|LUMINOUS_CONSUME,
        "/\b(?:from|import)(?=\s)/m", "/(?=$)/m"),
        
      new LuminousDelimiterRule(3, 'FUNCTION_LINE', 
        LUMINOUS_REGEX|LUMINOUS_CONSUME,
        "/^(?>[ \t]*)(?:class|def)(?=\s)/m", "/(?=[\n\(])/"),
        
      new LuminousDelimiterRule(3, 'TYPE', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/(?<=[\s,\.])\w+/'),
        
      new LuminousDelimiterRule(3, 'USER_FUNCTION', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE, '/(?<=\s)\w+/'),
        
      // this fixes detection of strings/comments inside list/dicts, as the 
      // elements may be declared 1 per line
      // we can then say that inside these, quoted comments can't occur
      new LuminousDelimiterRule(0, 'CONTAINER', LUMINOUS_CONSUME, '[', ']'),
      new LuminousDelimiterRule(0, 'CONTAINER', LUMINOUS_CONSUME, '{', '}'),
      new LuminousDelimiterRule(0, 'CONTAINER', LUMINOUS_CONSUME, '(', ')'),
    );
    
    $this->state_transitions = array(
      'GLOBAL' => array('DOCCOMMENT', 'COMMENT', 'STRING', 'IMPORT_LINE', 
        'FUNCTION_LINE', 'SHEBANG', 'CONTAINER', 'HASH_COMMENT'),
      'IMPORT_LINE'=>array('IMPORT_LINE', 'TYPE', 'COMMENT', 'DOCCOMMENT',
       'HASH_COMMENT'),
      'FUNCTION_LINE'=>array('USER_FUNCTION'),
      'CONTAINER' => array('STRING', 'HASH_COMMENT', 'CONTAINER'),
    );
    $this->state_type_mappings = array(
      'IMPORT_LINE'=>null,
      'FUNCTION_LINE' => null,
      'CONTAINER' => null,
      
      'HASH_COMMENT' => 'COMMENT',
      
      );
    
    
    
    $this->simple_types[] = new LuminousSimpleRule(3, 'TYPE', LUMINOUS_REGEX,
      '/@(\w+\.?)+/');     
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    
  }
}

