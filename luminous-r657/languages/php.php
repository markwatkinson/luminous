<?php

class LuminousGrammarPHP extends LuminousGrammar
{

  public $operators = array('\+', '\*', '-', '\/', '=', '&gt;', 
    '&lt;', '=', '!', '%', '&amp;', '\|', '~', '\^', '\[', '\]');  
  
  public $keywords = array(
    '&lt;\?(?:php)?|\?&gt;',
    'abstract|and|as',
    'break',
    'case|catch|cfunction|class|clone|const|continue',
    'declare|default|do',
    'echo|else(?:if)?|end(?:declare|for(?:each)?|if|switch|while)|extends',
    'final|for(?:each)?|function',
    'global|goto',
    'if|implements|instanceof|interface',
    'namespace|new',
    'old_function|or',
    'parent|private|protected|public',
    'return',
    'static|switch',
    'throw|try',
    'use',
    'var',
    'while',
    'xor',
    '\$this');
    

  public $function_regex =  '/\b%FUNCTION\b/x';     
  public $functions = array("include(?:_once)?", "require(?:_once)?",
    // array functions
    'array(?:_(?:(?:change_key_case)|(?:chunk)|(?:combine)|(?:count_values)|(?:diff(?:_(?:u?assoc|u?key))?)|(?:fill(?:_keys)?)|(?:filter)|(?:flip)|(?:intersect(?:_(?:u?assoc|u?key))?)|(?:key(?:s|_exists))|(?:map)|(?:merge(?:_recursive)?)|(?:multisort)|(?:pad)|(?:pop)|(?:product)|(?:push)|(?:rand)|(?:reduce)|(?:replace(?:_recursive)?)|(?:reverse)|(?:search)|(?:shift)|(?:slice)|(?:splice)|(?:sum)|(?:udiff(?:_u?assoc)?)|(?:uintersect(?:_u?assoc)?)|(?:unique)|(?:unshift)|(?:values)|(?:walk(?:_recursive)?)))?',
    // pcre functions
    'preg_(?:filter|grep|last_error|match(?:_all)?|quote|replace(?:_callback)?|split)',
    // magic methods
    '__(?:construct|destruct|call|callStatic|get|set|isset|unset|sleep|wakeup|toString|invoke|set_state|clone)',
    
    'ctype_(?:alnum|alpha|cntrl|digit|graph|lower|print|punct|space|upper|xdigit)
    |
    create_function',
    'define',
    'header',
    'implode|isset|is_(?:array|bool|callable|double|float|int(?:eger)?|long|null|numeric|object|real|resource|scalar|string)',
    'join',
    'explode',
    
  
    
    'str(?:_(?:getcsv|ireplace|pad|repeat|replace|rot13|shuffle|split|word_count))',
    'str(?:casecmp|chr|cmp|coll|cspn|ip_tags|ipcslashes|ipos|ipslashes|istr|len|natcasecmp|natcmp|ncasecmp|ncmp|pbrk|pos|rchr|rev|ripos|rpos|spn|str|tok|tolower|toupper|tr)',
    
    'substr(_compare|_count|_replace)?',
    'split',
    'trim',
    'unset',
    );
    
  public $types = array('false', 'null', 'true', 'NULL');
  public $oo_separators = array('::', '-&gt;');
  public $ignore_outside_strict = false;
  
  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('php');
    $this->SetInfoVersion('r657');    
    
    // I think the close tag is optional.
    $this->ignore_outside =  array(
      new LuminousBoundaryRule(LUMINOUS_REGEX, 
        "/&lt;\?(?:[=%]|php)?/", "/(?:\?&gt;|$)/"),      
    );
    
    

    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, "DOCCOMMENT", 0, "/**", "*/",
        'luminous_type_callback_doccomment'),
      new LuminousDelimiterRule(0, "COMMENT", 0, "/*", "*/",
        'luminous_type_callback_comment'),
        
      new LuminousDelimiterRule(0, "DOCCOMMENT", 
        LUMINOUS_REGEX|LUMINOUS_STOP_AT_END, '%///(?!/)%', '/$/m',
        'luminous_type_callback_doccomment'),
        
      new LuminousDelimiterRule(0, "COMMENT", 
        LUMINOUS_REGEX|LUMINOUS_STOP_AT_END, '%//%', '/$/m',
        'luminous_type_callback_comment'),
        
      new LuminousDelimiterRule(0, "COMMENT", 
        LUMINOUS_REGEX|LUMINOUS_STOP_AT_END, '%#%', '/$/m',
        'luminous_type_callback_comment'),
        
      new LuminousDelimiterRule(0, "STRING", 0, "'", "'",
        'luminous_type_callback_php_sstring'),
      new LuminousDelimiterRule(0, "STRING", 0, '"', '"',
      'luminous_type_callback_php_dstring'),
      new LuminousDelimiterRule(0, "STRING", 0, '`', '`',
      'luminous_type_callback_php_dstring'),
      
      new LuminousDelimiterRule(0, "HEREDOC", 
        LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS|LUMINOUS_COMPLETE, 
        '/(&lt;){3}[\s]*["\']?[\s]*/', null, 
        create_function('$str', 
        '$str = luminous_type_callback_generic_heredoc($str);
         $str = luminous_type_callback_php_dstring($str);
         return $str;')
         )
    );
    

    
    $this->child_grammar = new LuminousGrammarJavaScriptEmbedded();
    
    $this->simple_types[] = new LuminousSimpleRule(3, 'VARIABLE',
      LUMINOUS_REGEX, "/\\$\\$?[a-z_][a-z0-9_]*/i");
      
    $this->simple_types[] = new LuminousSimpleRule(4, 'USER_FUNCTION',
      LUMINOUS_REGEX, "/
      (
        (?:
          (?:^(?>\s*)      
            (?:
              (?:(?:abstract(?>\s+))?class)
              |
              (?:(?:(?:public|private|protected)(?>\s+))?function)
            )
          )
          |\sextends
        )
      (?>\s+)
      )(\w+)/mx", null, 2);
    
    $this->simple_types[] = luminous_generic_constant();
    $this->SetSimpleTypeRules();
    
    
  }
  
}
