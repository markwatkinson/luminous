<?php

/* 
 * Go language
 * http://golang.org/doc/go_spec.html
 * http://golang.org/src/cmd/gc/lex.c
 * 
 * 
 * TODO: Unicode support seems like a big deal
 * http://www.php.net/manual/en/regexp.reference.unicode.php
 */

class LuminousGrammarGo extends LuminousGrammar
{
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 
          'email'=>'markwatkinson@gmail.com',
          'website'=>'http://www.asgaard.co.uk')
    );
    $this->SetInfoLanguage('go');
    $this->SetInfoVersion('r657');
    
    
    // Adapted from the Python grammar.
    $this->numeric_regex =  '
/(?<![[:alnum:]_<$])
  (
    #hex
    (?:0[xX][0-9A-Fa-f]+)
    |
   
    #octal
    (?:0[0-7]+)
    |
    # regular number
    (?:
      (?>[0-9]+)
      (?: 

        # Or a fractional part, which may be imaginary
        (?:
          (?:\.?(?>[0-9]+)?        
            (?:(?:[eE][\+\-]?)?[0-9]+)?
          )[i]?
        )
      )?
    )
    |
    (
      # or only after the point, float x = .1;
      \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?[0-9]+)?[i]?
    )
  )
/x';


    $this->keywords = array('break', 'case', 'chan', 'const', 'continue',  'default', 
      'defer', 'else', 'false', 'fallthrough', 'for', 
      'func', 'go', 'goto', 'if', 'import', 'interface', 
      
      'iota', 'map', 'nil', 'package', 'range', 'return',
      'select', 
      'switch', 'type', 'true', 'var');

    $this->types = array(
      'any',
      'bool',
      'byte',
      'complex(64|128)?',
      'int(8|16|32|64)?',
      'float(32|64)?',
      'struct',
      'uint(8|16|32|64)?|uintptr'
      );
    
    $this->functions = array('append', 'cap', 'closed?', 'cmplx', 'copy', 'imag',
      'len', 'make', 'new', 'panic', 'print(?:ln)?', 'real', 'recover', 'sizeof');
    
    $this->oo_separators = array('\.');  
  
    $this->SetSimpleTypeRules();
    
    $this->delimited_types = array(
      luminous_generic_string('"', '"'),
      luminous_generic_string("`", "`"),
      new LuminousDelimiterRule(0, 'CHARACTER', 0, "'", "'", 
                                'luminous_type_callback_generic_string'),
      luminous_generic_comment_sl('//'),
      luminous_generic_comment("/*", "*/")
    );
    
    
    
    
    
  }
}