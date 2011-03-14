<?php
class LuminousGrammarBash extends LuminousGrammar
{
  
  function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('sh');
    $this->SetInfoVersion('r657');        
    
    $this->keyword_regex = '/(?<![a-zA-Z0-9_$\.])(?:%KEYWORD)(?![a-zA-Z0-9_\.])/';
    
    
    $this->numeric_regex = '
    /(?<![[:alnum:]_<$\.\-])  
    (
      #hex 
      (?:0[xX][0-9A-Fa-f]+)
    |
      # regular number
      (?:
        [0-9]+
        (?:
          # fraction
          \.[0-9]+([eE]?[0-9]+)?
        )?
      )
      |
      (?:
        # or only after the point, float x = .1;
        \.[0-9]+(?:[eE]?[0-9]+)?
      )
    )
    (?![[:alnum:]_<$\.\-]) 
    /x';
    
    
    $this->keywords = array_merge($this->keywords, 
      array("case", "do", "done", "elif", "else", "esac", "fi", "for",
    "function", "if", "in", "select", "then", "time", "until", "while")
    );
    
    $this->operators = array();
    
    // just some common ones or I'll be here all year.
    $this->functions = array("awk", "cat", 'cd', "chmod", "chown", "cp", "cut", 
      "date", "diff", "echo", "egrep", "env", "eval", "exit", "export", "file",
      "find", "ftp", "gawk", "grep", "gzip", "head", "help", 'join', 'less', 
      'ln', 'ls', 'mkdir', 'mv', 'ps', 'pwd', 'read', 'rename', 'rm', 'rmdir', 
      'sed', 'sleep', 'sort', 'ssh', 'sudo', 'su', 'tail', 'tar', 'time',
      'touch', 'which', 'zip'); 
    
    $this->delimited_types = array(
      luminous_generic_shebang(),
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      '/(?<!\$)#.*$/m'),
      luminous_generic_string('"', '"', 'luminous_type_callback_bash_string'),
      luminous_generic_string("'", "'", 'luminous_type_callback_bash_string'),

      //obviously these are function calls, but it looks prettier if they're not.
      // and it's easier to read. Perhaps need a new identifier.
      new LuminousDelimiterRule(0, "TYPE", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      '/\$\([^\(]+\)/', null, 'luminous_type_callback_bash_string'),
      
      new LuminousDelimiterRule(0, 'FUNCTION', 0, '`', '`',
        'luminous_type_callback_bash_string'),
        
      new LuminousDelimiterRule(0, 'HEREDOC', 
        LUMINOUS_COMPLETE|LUMINOUS_DYNAMIC_DELIMS|LUMINOUS_REGEX, 
        '/(?:&lt;){2}[\-\\\]?\s*[\'"]?/', null,
        create_function('$str', 
        '$str = luminous_type_callback_generic_heredoc($str);
        $str = luminous_type_callback_bash_string($str);
        return $str;
        '))
      );
      
    $this->simple_types[] =     
      new LuminousSimpleRule(3, "VARIABLE", LUMINOUS_REGEX,
        '/[\w\-]+?[\s]*(?=[=])/m');
    $this->simple_types[] =     
        new LuminousSimpleRule(3, "VARIABLE", LUMINOUS_REGEX,
        '/\${.*?}/m');
        
    $this->simple_types[] =         
      new LuminousSimpleRule(3, 'VARIABLE', LUMINOUS_REGEX,
        "/\\$(?:(?:[[:alnum:]_-]+)|[#\*\?])/");
        
    // yes this again. ./
    $this->simple_types[] = 
      new LuminousSimpleRule(2, 'TYPE', LUMINOUS_REGEX, 
        '/(?<![[:alnum:]_\$\.])\.\/.*?[^\s]([\s]|$)/');
         
    $this->SetSimpleTypeRules();
  }
}
