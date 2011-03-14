<?php
/*
 * Perl Grammar for Luminous.
 *
 * TODO: named operators, variable interpolation
 *
 * XXX: perl is a massive, MASSIVE pain wrt its regular expression literals.
 * This grammar currently works for such amazing things as: 
 *  s/search/replace
 *  s?search?replace
 *  and even
 *  s{search}{replace}
 *
 *  but we cannot yet do
 *  s{search}[replace]
 *
 *  Some of this stuff needs porting into Luminous itself to make it 
 *  accessible to other grammars, and Luminous probably needs extending
 *  to deal with the above example.
 *
 *
 *  Also, the plain /regex/ delimiters fail the division test (i.e 1/2/1 would
 *  be recognised as 1 <REGEX>/2/</REGEX> 1).
 *
 *
 *
 *
 */

class LuminousGrammarPerl extends LuminousGrammar
{
  public $keywords = array("my", "print",
     "caller|continue",
     "delete|die|do|dump",
     "else|elsif|eval|exit",
     "for|foreach",
     "goto",
     "import|if",
     "last|local",
     "next",
     "our",
     "package|prototype",
     "redo|return|require",
     "sub",
     "unless|use",
     "wantarray|while",
     );
 
  public $ignore_outside_strict = false;
  
  public $oo_separators = array('::', '-&gt;');
  
  
  
  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('pl');
    $this->SetInfoVersion('r657');   


    $regex_literal_lb_chars = '[\(\[,=:;\?!~]|^';
    $regex_lb_pattern = "(?:
      (?<=$regex_literal_lb_chars)
      |(?<=if)
      |(?<=elsif)
      |(?<=while)
      |(?<=unless)
      )";

    $regex_literal_flags = 'cgimosx';
    $regex_body = '
      (?:
        .*?
        (?<!\\\\)
        (?>(?:\\\\\\\\)*)
      )
      ';

    $simple_regex_literal_template = "
      /
        #(?<=$regex_literal_lb_chars)
      [\s]*
        (?<=[^\w\$%@]|^)
        (?:m|qr)
        %DELIM1
        $regex_body
        %DELIM2
        [$regex_literal_flags]*
      /x";

    $simple_regex_literal_template2 = "
      %
      $regex_lb_pattern
      [\s]*
      /$regex_body/
      
      [$regex_literal_flags]*
      %x";


    $complex_regex_literal_template = "
      /
      #(?<=$regex_literal_lb_chars)
      (?<=[^\w\$%@]|^)
      (?:s|tr|y)\s*
      %DELIM1
      $regex_body
      %DELIM2\s*%DELIM1
      $regex_body
      %DELIM2
      [cdsegimosx]*
      /x";       
  
    $regex_literal_delimiter_pairs = array(
      '((?!&)[\/\|@\?!\\\,\.\#\+\=\-_\^\$\%])' => '\\0',
      '\{' => '\}',
      '\[' => '\]',
      '\(' => '\)',
      '&lt;' => '&gt;'
    );

    $rules = array();

    foreach($regex_literal_delimiter_pairs as $d1=>$d2)
    {
      if ($d2 === '\\0')
      {
        $simple = str_replace('%DELIM1', 
          str_replace('\\0', '\\1', $d1),
          $simple_regex_literal_template);
        $simple = str_replace('%DELIM2', 
          str_replace('\\0', '\\1', $d2), $simple) . 's';

        $rules[] = new LuminousDelimiterRule(0, 'REGEX',
          LUMINOUS_REGEX|LUMINOUS_COMPLETE,
          $simple, null, 'luminous_type_callback_pcre_regex'
        );
      
      }

      $complex = $complex_regex_literal_template;
      if ($d2 === '\\0')
      {
        $complex = str_replace('%DELIM2\s*%DELIM1', '\\1', 
          $complex) . 's';
      }
      $complex = str_replace('%DELIM1', $d1, $complex);
      $complex = str_replace('%DELIM2', 
        str_replace('\\0', '\\1', $d2), $complex);

      $rules[] = new LuminousDelimiterRule(0, 'REGEX',
        LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        $complex, null, 'luminous_type_callback_pcre_regex'
      );
        
    }





    $rules[] = new LuminousDelimiterRule(0, 'REGEX', LUMINOUS_COMPLETE|
      LUMINOUS_REGEX, $simple_regex_literal_template2, null,
      'luminous_type_callback_pcre_regex');



    
   
    
    $this->delimited_types = array_merge($rules, array(
      new LuminousDelimiterRule(0, 'SHEBANG', LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
      "/^[\s]*#!.*/"),
      new LuminousDelimiterRule(0, "FUNCTION", LUMINOUS_REGEX, '/(?<!\$)`/', '/`/'),
      
      new LuminousDelimiterRule(0, "COMMENT", LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
      '/(?<![%@\$])#.*/', null),
      
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX,
        '/^=(?:pod|head\d|over|item|back|begin|end|for|encoding)\s/m',
        '/^=cut$/m'),
      
      new LuminousDelimiterRule(0, "STRING", LUMINOUS_REGEX, '/(?<!\$)"/', '/"/',
      'luminous_type_callback_perl_string'),
      new LuminousDelimiterRule(0, "STRING", LUMINOUS_REGEX, '/(?<!\$)\'/', '/\'/'),
      
      new LuminousDelimiterRule(0, "STRING",       
        LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS|LUMINOUS_COMPLETE|LUMINOUS_EXCLUDE,
        '/(?<![a-zA-Z0-9_\$])q[qxw]?[\s]*(?![a-zA-Z0-9])/',
        null, 'luminous_type_callback_perl_string'),
        new LuminousDelimiterRule(0, "VALUE", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
       "/&lt;(STDIN|STDOUT|STDERR|DATA)&gt;/"),

       // Perl is a massive pain for regular expressions. 
       /*new LuminousDelimiterRule(0, "REGEX", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/
          (?<=[\(\[,=:;\?!~]|^)
          [\s]* 
          (?:m|qr)?\/
            (?:.*?[^\\\])*?
          (?:\\\\\\\\)*\/
          [cgimosx]*
          /x', null, 'luminous_type_callback_generic_string'),        



       new LuminousDelimiterRule(0, "REGEX", LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/
          (?<=[\(\[,=:;\?!~]|^)
          [\s]* 
          (?:s|tr|y)\s*([\/|])
            (?:(.*?[^\\\])*?
          (?:\\\\\\\\)*\\1){2}
          [cdsegimosx]*
          /x',       
        null, 'luminous_type_callback_generic_string'),
        */
        new LuminousDelimiterRule(0, 'HEREDOC', 
          LUMINOUS_COMPLETE|LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS,
          '/(?:&lt;){2}["`]?(?=[[:alnum:]])/', null,
          create_function('$str', 
            '$str = luminous_type_callback_perl_string($str);
             $str = luminous_type_callback_generic_heredoc($str);
             return $str;')),
          
        new LuminousDelimiterRule(0, 'HEREDOC', 
          LUMINOUS_COMPLETE|LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS,
          '/(?:&lt;){2}[\']?(?=[[:alnum:]])/',
          null,
          'luminous_type_callback_generic_heredoc')          
        )
      );       

    $this->ignore_outside = array( 
      new LuminousBoundaryRule(LUMINOUS_REGEX, "/^/", 
      "/__(DATA|END)__/")
      );
      
      
      // this isn't really a type, but what else do you call it?
      $this->simple_types[] = new LuminousSimpleRule(3, 'TYPE',
        LUMINOUS_REGEX, '/&lt;[[:alnum:]_]+&gt;/');
      
      
      $this->simple_types[] = new LuminousSimpleRule(3, 'VARIABLE',
        LUMINOUS_REGEX, "/(?!<[a-z0-9_])[$%@][a-z0-9_]+/i");
        
      // Special variables
      // http://www.kichwa.com/quik_ref/spec_variables.html
      $this->simple_types[] = new LuminousSimpleRule(3, 'VARIABLE',
        LUMINOUS_REGEX, 
        '/\$(?:[\|%=\\-~\\^`\'\+_\.\/\\\,"#\$\?\*\[\];!@]|&amp;)/');        
        
      
      $this->simple_types[] = new LuminousSimpleRule(4, 'USER_FUNCTION',
        LUMINOUS_REGEX, '/(\bsub\s+)(\w+)/', null, 2);
      $this->SetSimpleTypeRules();
      $this->simple_types[] = luminous_generic_constant();
      
        

      $this->simple_types[] = new LuminousSimpleRule(3, 'VALUE',
        LUMINOUS_REGEX, "/__(DATA|END)__/");  
        
  }
  

}
