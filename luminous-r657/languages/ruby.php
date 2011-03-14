<?php
class LuminousGrammarRuby extends LuminousGrammar
{
  public $operators =  array('(?<![a-zA-Z0-9])and(?![a-zA-Z0-9])', 
    '(?<![a-zA-Z0-9])not(?![a-zA-Z0-9])', '(?<![a-zA-Z0-9])in(?![a-zA-Z0-9])', 
    '(?<![a-zA-Z0-9])or(?![a-zA-Z0-9])', '\+', '\*', '-', '\/', '=',  '&gt;', 
    '&lt;', '=','!', '%', '&amp;', '\|', '~', '\^', '\[', '\]');
    
  public $keywords =  array(
    'BEGIN',
    'END',
    'alias',
    'begin|break',
    'case|class',
    'def(?:ined)?|do',
    'else(?:if)?|end|ensure',
    'for',
    'if',
    'module',
    'next',
    'redo|rescue|retry|return',
    'self|super',
    'then',
    'undef|unless|until',
    'when|while',
    'yield');
    
  public $types = array('false', 'nil', 'self', 'true', '__FILE__', '__LINE__',
    'TRUE', 'FALSE', 'NIL', 'STDIN', 'STDOUT', 'STDERR', 'ENV', 'ARGF', 'ARGV', 
    'DATA', 'RUBY_(?:VERSION|RELEASE_DATE|PLATFORM)');
    
  public $functions = array('puts', 'require');  
      
  public $oo_separators = array('\.');
  
  
  // adapted from Python's
  public $numeric_regex = '
  /(?<![\w_<$])
  (?:
    #control codes
    (?:\?(?:\\\[[:alpha:]]-)*[[:alpha:]])
    |
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
        # fraction
        (?:
          (?:\.?(?>[0-9]+)?        
            (?:(?:[eE][\+\-]?)?(?>[0-9]+))?
          )
        )
      )?
    )
    |
    (
      # or only after the point, float x = .1;
      \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?(?>[0-9]+))?
    )    
  )
  (?:_+\d+)*
  
  /x';
  
  
  public $heredoc_delims = array();
  public function heredoc_cb_cb($matches)
  {
    $this->heredoc_delims[] = $matches[3];
    return $matches[1] . $matches[2] . '<CONSTANT>' . $matches[3]
      . '</CONSTANT>' . $matches[4];
  }
  
  public function heredoc_cb($str)
  {
    $this->heredoc_delims = array();
    
    $s_ = explode("\n", $str, 2);
    $s_[0] = preg_replace_callback('/((?:&lt;){2}\-?)([\'"`]?)(\w+)(\\2)/',
      array($this, 'heredoc_cb_cb'), $s_[0]);
    $s_[1] = preg_replace("/^[ \t]*(?:(" 
                          . implode(')|(?:', $this->heredoc_delims)
                          . '))/m', 
                          '<CONSTANT>$0</CONSTANT>',
                          $s_[1]);
    $s_[1] = luminous_type_callback_cstring($s_[1]);
    return implode("\n", $s_);
  }
  
  
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('rb');
    $this->SetInfoVersion('r657');
    
    $regex_flags = "iomxuesn";
        
    // Ruby is complicated, apparently.
    // http://www.zenspider.com/Languages/Ruby/QuickRef.html#18
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, "DOCCOMMENT", 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE, "/^=begin(?:.*?)^=end/ms"),
        
      luminous_generic_comment_sl("#"),
      
      new LuminousDelimiterRule(0, "FUNCTION", 0, 
        '`', '`'),

      new LuminousDelimiterRule(0, "STRING_", 0, 
        '"', '"'),
      
      new LuminousDelimiterRule(0, "STRING", 0, 
        "'", "'"),

      new LuminousDelimiterRule(0, "REGEX",
        LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS,
        '/%r[\s]*/s', "[$regex_flags]*/", 'luminous_type_callback_pcre_regex'),
//         
      new LuminousDelimiterRule(0, 'STRING_',
        LUMINOUS_DYNAMIC_DELIMS|LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/%[xWQ]/', null),
                
        
      /* XXX:  
       * this is terrible, but if we leave the delimiter as 'not alnum',
       *  it starts to pick up %= and stuff which is used as a delimiter in 
       * web-ruby like <?php in php. So we also specify no =s, no &s and no 
       * spaces
       */ 
      new LuminousDelimiterRule(0, "STRING", 
        LUMINOUS_DYNAMIC_DELIMS|LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        "/%(?:[wq]|(?![=&\s[:alnum:]]))/", null),
        

      // Ruby figures out whether something's a regex or not by whether it 
      // occurs in operator or operand position. I don't think we can easily 
      // do this here but we can check for preceding functions which we know
      // take regexes, and we can also look for preceding symbols that imply
      // operand position. But this is not exhaustive.
      
      new LuminousDelimiterRule(0, "REGEX", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      "/
        (?:
          (?<=[\(\[+-\/*%,=;\?!~]|^)
          #some methods
          |(?<=sub\s)
          |(?<=scan\s)
          |(?<=split\s)
          |(?<=sub!\s)
          |(?<=scan!\s)
          |(?<=index\s)
          |(?<=match\s)
          #some keywords
          |(?<=case)
          |(?<=end)
          |(?<=if)
          |(?<=or)
          |(?<=and)
          |(?<=when)
          |(?<=print)
          
        )
        [\s]* 
        \/
          (?:.*?[^\\\])*?
          (?:\\\\\\\\)*\/
        [$regex_flags]*/x",
        null,
        'luminous_type_callback_pcre_regex'
      ),
      

      
      new LuminousDelimiterRule(0, 'HEREDOC', 
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,        
        "/
         (?:&lt;){2}
         (?: .* (?:&lt;){2})?
         \-?
         [\"'`]?
         (\w+)
         (?s:.*? ^[ \t]* \\1)
         /mx",
        null,
        array($this, 'heredoc_cb')/*
        create_function('$str',
          '
          $str = luminous_type_callback_generic_heredoc($str);
          $str = luminous_type_callback_cstring($str);
          return $str;
          ')*/
          ),
         
      new LuminousDelimiterRule(0, 'INTERP', LUMINOUS_CONSUME, '#{', '}'),
      
      new LuminousDelimiterRule(3, 'VARIABLE',
        LUMINOUS_REGEX|LUMINOUS_COMPLETE, "/(?!<[a-z0-9_\\$])(?:@@?|\\$)[a-z0-9_]+/i"),
      

      new LuminousDelimiterRule(3, 'VARIABLE',
      LUMINOUS_REGEX|LUMINOUS_COMPLETE, '/
\$
  (?:
    (?:[!@`\'\+1~=\/\\\,;\._0\*\$\?:"])
    |
    (?: &(?:amp|lt|gt); )
    |
    (?: -[0adFiIlpvw])
    |
    (?:DEBUG|FILENAME|LOAD_PATH|stderr|stdin|stdout|VERBOSE)
  )/x'),      
        
    );
    
    
    $this->state_transitions = array(
      'GLOBAL' => array('VARIABLE', 'DOCCOMMENT', 'COMMENT', 'FUNCTION', 
        'REGEX', 'STRING_', 'STRING','HEREDOC'),
      'HEREDOC' => array('VARIABLE', 'INTERP', 'COMMENT'),
      'STRING_' => array('INTERP'),
      'FUNCTION'=>array('INTERP'),
      'INTERP' => '*'
    );
    $this->state_type_mappings = array(
      'STRING_' => 'STRING',
      'INTERP' => 'VARIABLE'
    );
      
    
    
    
    
  
    $this->simple_types[] = new LuminousSimpleRule(4, 'USER_FUNCTION',
      LUMINOUS_REGEX, '/(\b(?:def|class)\s+)(\w+)/', null, 2);
    $this->SetSimpleTypeRules();
    $this->simple_types[] = luminous_generic_constant();
    
  }
}
