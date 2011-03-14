<?php


/**
 * \file luminous_grammar_callbacks.php
 * \brief Callback functions utilised by the grammars.
 * 
 * \see LuminousTypeCallbacks
 * 
 * 
 */ 


/**
 * 
 *  \defgroup LuminousTypeCallbacks LuminousTypeCallbacks
 * 
 * 
 * The grammars can define a callback to execute when a particular block has 
 * been matched by Luminous. For example, after matching a string, it is a
 * callback's job to run through it and highlight any escape sequences or to 
 * do any other formatting that is too specific to expect Luminous to perform.
 * To some extent this tries to fake statefulness.
 * 
 * 
 * \note All callback functions take at least one argument, which is the 
 * string to be operated upon. If LuminousRuleFlag::LUMINOUS_MATCHES is specified,
 * they will also receive an array. See the flag's documentation. The functions
 * should return the new string.
 * 
 * 
 */ 


/** 
 * \ingroup LuminousTypeCallbacks
 * 
 * Constant just meaning 'until the end of line'. If you don't know why it's 
 * useful then you don't need to worry about it (used for Doxygen arg lists)
 */ 
define("LUMINOUS_EOL", 0xDEADBEEF);

/**
 * \ingroup LuminousTypeCallbacks
 * 
 * A very broad function where any character following a backslash is presumed 
 * to be an escape sequence. Unicode hex-strings are also supported (16 and 32 
 * bit)
 * 
 * 
 */

function luminous_type_callback_generic_string($str)
{
  if (strpos($str, '\\') === false)
    return $str;
  $str = preg_replace('/\\\
    (?:
      (?:u[a-f0-9]{4,8})
      |\d{1,3}
      |[\w\\\]
    )
    /xi', '<ESC>$0</ESC>', $str);   
  return $str;
}

/**
 * \ingroup LuminousTypeCallbacks
 * Highlights doubled up single quotes, as per escaping rules in SQL, MATLAB
 * \param str A reference to the matched string
 */

function luminous_type_callback_sql_single_quotes($str)
{
  if (isset($str[3]))
  {
    $str = $str[0] . str_replace("''", "<ESC>''</ESC>", 
      substr($str, 1, -1)) . $str[strlen($str)-1];
    $str = luminous_type_callback_generic_string($str);
  }
  return $str;
}


/**
 * \ingroup LuminousTypeCallbacks
 * Highlights escape sequences for a double quoted C string according to C's 
 * rules.
 * \param str A reference to the matched string
 */ 
function luminous_type_callback_cstring($str)
{
  $str = preg_replace('/\\\[0abctfnrv\\\"\']/', '<ESC>$0</ESC>', $str);  
  return $str;
}

/**
 * \ingroup LuminousTypeCallbacks
 * Highlights escape sequences for a Python string according to Python's rules
 * on raw/unicode strings.
 * \param str A reference to the matched string
 */ 
function luminous_type_callback_pystring($str)
{
  // raw string
  if (isset($str[1]) && strtolower($str[0]) == 'r'    
    && ($str[1] == '"' || $str[1] == "'")
    )
    return $str;
  
  $str = preg_replace('/\\\[u][0-9a-f]{4,8}/i', '<ESC>$0</ESC>', $str);  
  $str = preg_replace('/\\\[x][0-9a-f]{2}/i', '<ESC>$0</ESC>', $str);  
  $str = preg_replace('/\\\[0-8]{1,3}/', '<ESC>$0</ESC>', $str);  
  $str = luminous_type_callback_cstring($str);
  return $str;
}


/**
 * \ingroup LuminousTypeCallbacks
 * Checks for a string being a PHP PREG regex and highlights as necessary
 * \param str A reference to the matched string
 * \internal
 */
function luminous_type_callback_pcre_regex($str)
{
  $flags = array();
  $matches = array();
  if (preg_match("/[[:alpha:]]+$/", $str, $matches))
  {
    $m = $matches[0];
    $flags = str_split($m);
    $str = preg_replace("/(?<![[:alnum:]\s])[[:alpha:]]+$/", 
      "<KEYWORD>$0</KEYWORD>", $str);
  }

  $str = preg_replace("/((?<!\\\)[\*\+\.|])|((?<![\(\\\])\?)/",
                         "<REGEX_OPERATOR>$0</REGEX_OPERATOR>",
                         $str);  
  $str = preg_replace("/(?<=\()\?(?:(?:[a-zA-Z:!|=])|(?:(?:&lt;)[=!]))/", 
    "<REGEX_SUBPATTERN>$0</REGEX_SUBPATTERN>",
    $str);
  $str = preg_replace("/(?<!\\\)[\(\)]/", 
  "<REGEX_SUBPATTERN_MARKER>$0</REGEX_SUBPATTERN_MARKER>",
    $str);
  $str = preg_replace("/(?<!\\\)[\[\]]/", 
  "<REGEX_CLASS_MARKER>$0</REGEX_CLASS_MARKER>",
    $str);    
  $str = preg_replace("/(?<!\\\)
    \{
      (
        ((?>\d+)(,(?>\d+)?)?)
        |
        (,(?>\d+))
      )
    \}/x", 
    
  "<REGEX_REPEAT_MARKER>$0</REGEX_REPEAT_MARKER>",
    $str);
  if (in_array('x', $flags))
  {
    $str = preg_replace('/(?<!\\\)#.*$/m', '<COMMENT>$0</COMMENT>',
       $str);
  }
  
  $tag = "REGEX";
  $str = tag_block($tag, $str, true);
  return $str;

}

function luminous_type_callback_generic_regex($str)
{
  $str = luminous_type_callback_generic_string($str);
  $str = luminous_type_callback_pcre_regex($str);
  return $str;
}

/**
 * \ingroup LuminousTypeCallbacks
 * Checks for a string being a PHP PREG regex and highlights as necessary
 * \param str A reference to the matched string
 * \internal
 */
function luminous_type_callback_php_regex($str)
{

  $matches = array();
  $str_cpy = substr($str, 1, strlen($str)-2);
  $str_cpy = trim($str_cpy);
  if (!isset($str_cpy[2]))
    return $str;  
  if (ctype_alnum($str_cpy[0]))
    return $str;  
  
  $flags = "imsxeADSUXJU";
  $str_cpy2 = rtrim($str_cpy, $flags);
  if ($str_cpy2[strlen($str_cpy2)-1] !== $str_cpy[0])
    return $str;
  
  
  if (preg_match("/^([^a-zA-Z0-9]).*\\1[$flags]*$/s", $str_cpy, $matches))
  {
    $str_cpy = luminous_type_callback_pcre_regex($str_cpy);
    $str = $str[0] . $str_cpy . $str[strlen($str)-1];
  
  }
  return $str;
}

/**
 * \ingroup LuminousTypeCallbacks
 * Highlights a string according to PHP single quoted string escaping rules, 
 * i.e. the only escape sequences are \\' and \\\\
 * 
 * \param str A reference to the matched string
 */
function luminous_type_callback_php_sstring($str)
{
  $str = luminous_type_callback_php_regex($str);
  
  $str = str_replace('\\\\', '<ESC>\\\\</ESC>', $str);    
  $str = str_replace("\\'", "<ESC>\\'</ESC>", $str);    
  return $str;
}

/**
 * \ingroup LuminousTypeCallbacks
 * Highlights a string according to PHP double quoted string escaping rules,
 * including variable interpolation.
 * \param str A reference to the matched string
 */ 
function luminous_type_callback_php_dstring($str)
{
  $str = luminous_type_callback_php_regex($str);
  
  
  if (strpos($str, '$') !== false)
  {
    // not sure if this escaping is right.  
    $str = preg_replace('/(?<![\{\\\])\$[a-z_]+[a-z0-9_]*(?!\})/i', 
      "<VARIABLE>$0</VARIABLE>", $str);
  }
  if (strpos($str, '{$') !== false)
  {
    $str = preg_replace('/\{\$[a-z_]+.*?\}/i', "<VARIABLE>$0</VARIABLE>",
      $str);    
  }
  if (strpos($str, '\\') !== false)
  {
    $str = preg_replace('/\\\
    (?:
      (?:[nrtvf\\\"\$])
      |
      (?:[0-7]{1,3})
      |
      (?:x[0-9A-Fa-f]{1,2})
    )/x',
    "<ESC>$0</ESC>", $str);
  }
  return $str;
  
}


/**
 * \ingroup LuminousTypeCallbacks
 * Performs Perl-like string interpolation
 * \param str A reference to the matched string
 */
function luminous_type_callback_perl_string($str)
{
  $str = preg_replace('/(?<!\$)\$[[:alnum:]_#]+/', "<VARIABLE>$0</VARIABLE>", 
    $str);
  $str = luminous_type_callback_generic_string($str);
  return $str;
  
}


/**
 * \ingroup LuminousTypeCallbacks
 * Performs bash variable interpolation.
 * \param str A reference to the matched string
 */ 
function luminous_type_callback_bash_string($str)
{
  $str = preg_replace('/(?<!$)\$[[:alnum:]_\-#\*@]+/', 
    "<VARIABLE>$0</VARIABLE>", $str);
  return $str;
    
}


/**
 * \ingroup LuminousTypeCallbacks
 * Highlights comment tags, TODO, FIXME, NOTE
 * \param str A reference to the matched string
 * 
 */
function luminous_type_callback_comment($str)
{
  $str = str_replace("NOTE", "<COMMENT_NOTE>NOTE</COMMENT_NOTE>", $str);
  $str = str_replace("FIXME", "<COMMENT_NOTE>FIXME</COMMENT_NOTE>", $str);    
  $str = str_replace("XXX", "<COMMENT_NOTE>XXX</COMMENT_NOTE>", $str);
  $str = str_replace("TODO", "<COMMENT_NOTE>TODO</COMMENT_NOTE>", $str);    
  $str = str_replace("BUG", "<COMMENT_NOTE>BUG</COMMENT_NOTE>", $str);  
  return $str;
  
}


/**
 * \ingroup LuminousTypeCallbacks 
 * 
 * Looks up valid JavaDoc/Doxygen tags and converts them to an integer which
 * represents how many word arguments they take
 * \param tag The tag name.
 * \return an integer or LUMINOUS_EOL if it runs to the next newline.
 * \internal
 */ 
function luminous_doxytag_arg_length($tag)
{
  $x = 0;
  
  switch(trim($tag))
  {
    case "fn":
    case "hideinitializer":
    case "internal":
    case "nosubgrouping":
    case "private":
    case "protected":
    case "public":
    case "showinitializer":
    case "else":
    case "endcond":
    case "endif":
    case "code":
    case "endcode":
    case "verbatim":
    case "endverbatim":
      $x = 0;
      break;
    
    case "addtogroup":
    case "category":
    case "class":
    case "def":
    case "defgroup":
    case "dir":
    case "enum":
    case "example":
    case "extends":
    case "file":
    case "headerfile":
    case "implements":
    case "ingroup":
    case "interface":
    case "namespace":
    case "memberof":
    case "package":
    case "page":
    case "relates":
    case "relatesalso":
    case "weakgroup":
    
    case "cond":
    case "elseif":
    case "exception":
    case "if":
    case "ifnot":
    case "par":
    case "param":
    case "tparam":
    case "retval":
    case "throw":
    case "throws":
    case "xrefitem":
      $x = 1;
      break;
      
    case "protocol":
    case "struct":
    case "union":
      $x = 3;
      break;
      
    case "mainpage":
    case "name":
    case "overload":
    case "property":
    case "typedef":
    case "var":    
    
    case "attention":
    case "author":
    case "brief":
    case "bug":
    case "date":
    case "deprecated":
    case "details":
    case "invariant":
    case "note":
    case "post":
    case "pre":
    case "remarks":
    case "ref":
    case "return":
    case 'returns':  
    case "sa":
    case "see":
    case "since":
    case "test":
    case "todo":
    case "version":
    case "warning":
      
    case "li":  
      $x = LUMINOUS_EOL;    
      break;
    default:
      $x = false;
      break;
  }
  return $x;
}


/**
 * \ingroup LuminousTypeCallbacks
 * Highlights a comment string according to Doxygen and Javadoc rules (callback
 *  to preg_replace_callback)
 * \param matches the regex match array.
 * \sa luminous_type_callback_doccomment
 * \internal
 */
function luminous_type_callback_doccomment_cb($matches)
{
  $lead = $matches[1];
  $tag_char = $matches[2];
  $tag = $matches[3];
  
  $line = "";
  if (isset($matches[4]))
    $line = $matches[4];
  
  $len = -1;
  // JSDoc-like  
  $l_ = ltrim($line);
  if (isset($l_[0]) && $l_[0] === '{') {
    $line = preg_replace('/({[^}]*})/', "<DOCPROPERTY>$1</DOCPROPERTY>", $line);
    return "$lead<DOCTAG>$tag_char$tag</DOCTAG>$line";
  }
  else  
    $len = luminous_doxytag_arg_length($tag);
  
  if ($len === false)
    return $matches[0];
  
  elseif($len === 0)
    return "$lead<DOCTAG>$tag_char$tag</DOCTAG>$line";
  elseif($len === LUMINOUS_EOL)
    return "$lead<DOCTAG>$tag_char$tag</DOCTAG><DOCSTR>$line</DOCSTR>"; 
  else
  {
    $l = explode(' ', $line);
    $start = "$lead<DOCTAG>$tag_char$tag</DOCTAG><DOCPROPERTY>";
    
    $j = 0;
    $c = count($l);
    for($i=0; $j<$len && $i<$c; $i++)
    {      
      $s = $l[$i];
      $start .= $s . ' ';
      unset($l[$i]);
      if (trim($s) != '')
        $j++;
    }
    $start .= "</DOCPROPERTY>";
    $start .= implode(' ', $l);    
    return $start;
  }
}

/**
 * \ingroup LuminousTypeCallbacks
 * Highlights a comment string according to Doxygen and Javadoc rules
 * \param str A reference to the matched string
 */
function luminous_type_callback_doccomment($str)
{
  if (strpos($str, '@') !== false || strpos($str, '\\') !== false)
  {
    $str = preg_replace_callback("/(^(?>[\/\*#\s]*))([\@\\\])([^\s]*)([ \t]+.*?)?$/m",
    'luminous_type_callback_doccomment_cb',
      $str);
  }
  if (strpos($str, '&lt;') !== false && strpos($str, '&gt;') !== false)
  {
    $str = preg_replace('/(&lt;\/?)(\S*?)(&gt;)/', '<HTMLTAG>$0</HTMLTAG>',
      $str);
  }
      
  $str = luminous_type_callback_comment($str);  
  return $str;
}


function luminous_type_callback_generic_heredoc($str)
{
  $delim = "";
  $matches = array();
  $top_line = explode("\n", $str, 2);  
  preg_match_all("/(&.+?;)|([[:alnum:]_]+)/", $top_line[0], $matches, 
    PREG_SET_ORDER);
  foreach($matches as $m)
  {
    if (!isset($m[2]))
      continue;
    $g = $m[2];
    $str = str_replace($g, "<CONSTANT>$g</CONSTANT>", $str);
  }
  return $str;
}



function luminous_type_callback_ruby_string($str)
{
  $str = preg_replace('/#\{.*?\}/', '<VARIABLE>$0</VARIABE>', $str);
  $str = luminous_type_callback_generic_string($str);  
  return $str;
}

