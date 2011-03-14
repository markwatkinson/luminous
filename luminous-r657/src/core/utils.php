<?php

/**
 * \ingroup LuminousUtils
 * \internal
 * \brief Decodes a PCRE error code into a string
 * \param errcode The error code to decode (integer)
 * \return A string which is simply the name of the constant which matches the
 *      error code (e.g. 'PREG_BACKTRACK_LIMIT_ERROR')
 * 
 * \todo this should all be namespaced
 */ 
function pcre_error_decode($errcode)
{
  switch ($errcode)
  {
    case PREG_NO_ERROR:
      return 'PREG_NO_ERROR';
    case PREG_INTERNAL_ERROR:
      return 'PREG_INTERNAL_ERROR';
    case PREG_BACKTRACK_LIMIT_ERROR:
      return 'PREG_BACKTRACK_LIMIT_ERROR';
    case PREG_RECURSION_LIMIT_ERROR:
      return 'PREG_RECURSION_LIMIT_ERROR';
    case PREG_BAD_UTF8_ERROR:
      return 'PREG_BAD_UTF8_ERROR';
    case PREG_BAD_UTF8_OFFSET_ERROR:
      return 'PREG_BAD_UTF8_OFFSET_ERROR';
    default:
      return 'Unknown error code';
  }
}

/**
 * \ingroup LuminousUtils
 * \internal
 * \brief Simple string replace equivalent to preg_replace_callback
 * A string replace, which uses for the replacement a series of (individual)
 * calls to a callback function.
 * \param needle the search string
 * \param callback the callback function whose return shall be used as the 
 *  replacement. It shall receive exactly 1 argument, which is the search 
 *  needle. It will be called for every match, so if its output changes that 
 * will be caught by this function.
 * 
 * It must be callable by call_user_func. For references to an object,
 *  use array($obj, "methodName"). Otherwise use "funcname"
 * \param haystack the string in which to search and replace
 * \return the input string (haystack) with necessary replacements made
 */ 

function str_replace_callback($needle, $callback, $haystack)
{
  $index = -1;
  $last_index = 0;
  $str = "";
  $strlen_needle = strlen($needle);
  while( ($index = strpos($haystack, $needle, $index+1)) !== false)
  {
    $str .= substr($haystack, $last_index, $index-$last_index);
    $str .= call_user_func($callback, $needle);
    $last_index = $index + $strlen_needle;
  }
  $str .= substr($haystack, $last_index);
  return $str;  
}



/**
 * \ingroup LuminousUtils
 * \internal
 * \brief Determines if a character in a string is escaped.
 * Determines if a string index is an escaped character according to the given
 *      escape characters
 * \param str The string as a whole (or at least, up to and including index)
 * \param index the string index of the character to check
 * \param slashchars an array of legal escape characters (i.e. a backslash)
 * \return true|false
 *
 */ 
function is_escaped($str, $index, array $slashchars)
{
  $i = $index-1;
  
  // minor optimisation, but this function gets bombarded so much...
  if (isset($slashchars[1]))
  {
    while (($i >= 0) && in_array($str[$i], $slashchars))
      --$i;
  }
  elseif (isset($slashchars[0]))
  {
    $c = $slashchars[0];
    while (($i >= 0) && $str[$i] === $c)
      --$i;    
  }
  else
    return false;
  
  return (($index - $i - 1) & 1) === 1;  
}

/**
 * \ingroup LuminousUtils
 * \internal
 * \brief Matches a start-delimiter to an end-delimiter.
 * 
 * 
 * 
 * Matches a start delimiter to an end delimiter for the purpose of dynamic 
 * delimiter definitions. This usually means the funciton returns the start
 * delimiter it is given, but will match up open brackets  to close brackets 
 * and so forth. If the delimiter is to be used in a regex, it will be 
 * escaped as necessary.
 * 
 * \param delim the starter delimiter (character)
 * \param regex Set to true if the delimiter going to be used in a regex, false 
 *      otherwise (default: true)
 * 
 * \return The corresponding ending delimiter.
 * 
 * \todo complete the list for all regex unsafe chars.
 * 
 * 
 */ 
function match_delimiter($delim, $regex=true)
{
  $e = '';
  switch($delim)
  {
    case '(':         
      $e = ')';
      break;
    case '{': $e = '}';
    break;
    case '[': $e = "]";
    $esc = true;
    break;
    case '&lt;': $e = '&gt;';
    break;
    case '/':
    case '?':  
    case '+':
    case '*':
    case '.':  
    case '$':
    case '^':
    case '-':
    case '|':
      $e = $delim;   
      break;
      
    default:
      $e = $delim;
  }
  
  if ($regex)
    $e = preg_quote($e, '/');
  return $e;
}

function tag_block($tag, $lines, $separate_lines)
{
  if ($separate_lines === true)
    $lines = str_replace("\n", '</' . $tag . ">\n<" . $tag . '>', $lines);
  $lines = '<' . $tag . '>' . $lines . '</' . $tag . '>';
  return $lines;
}





function copy_string($str, $start, $end=null)
{
  if ($end === null)
    return substr($str, $start);
  return substr($str, $start, $end-$start);
}
