<?php

/*
  Copyright 2010 Mark Watkinson

  This file is part of Luminous.

  Luminous is free software: you can redistribute it and/or
  modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Luminous is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Luminous.  If not, see <http://www.gnu.org/licenses/>.

*/


require_once('rule.class.php');
require_once('grammarutils.php');



/** 
 * \file grammar.class.php
 * \brief The LuminousGrammar definition.
 * \author Mark Watkinson
 */
 
 
/**
 * \class LuminousGrammar
 * \author Mark Watkinson
 * 
 * \brief A set of grammar rules by which to parse and highlight source code.
 *
 * LuminousGrammar is the abastract class from which all grammars should be 
 * subclassed. The members delimited_types and simple_types are of most 
 * interest to creating new grammars.
 * 
 * When creating rules bear in mind that the parser internally uses HTML-like 
 * tags and if you target these in a grammar, you will at best make the output 
 * wrong. The characters: <, > and & are all escaped to their entity equivalents
 * ('&lt;' '&gt;' and &amp;. If you are reading this in Doxygen's HTML output:
 *    '\&lt;', '\&gt;' and \&amp; )
 * 
 * Secondly bear in mind that the parser treats line endings as Unix, so don't
 * worry about specifying \\n\\r in a delimiter, just use \\n
 *  
 * In regular expression types, the syntax used is the same as in the preg_* 
 * (PCRE) family of PHP functions. It is HEAVILY recommended to use slash 
 *  delimiters (/).
 *
 * There are numerous examples in this file. 
 * 
 * 
 * \todo heredoc. 
 */


abstract class LuminousGrammar
{
  
  public $info = array(); /**< Information about the grammar and its author in 
    the form where the currently valid keys are:
      
      \li \c language (str),
      \li \c version (str),   
      \li \c author (array)
      
      author has the keys:
      \li \c name (str),  
      \li \c email (str),
      \li \c website (str),
      
      \note Language must be set, the others are optional.
    */


  public $escape_chars = array("\\");   /**< Escape chars recognised by the 
    language. \note In future This will be deprecated in favour of something 
    specific to an invidual rule */
  
  
  public $delimited_types = array(); /**< An array of LuminousDelimiterRule 
  objects. 
   \par
   These rules are parsed first and anything they match is removed from the 
   string. simple_types is then parsed afterwards. That means that comments, 
   strings, regex literals etc, should be placed here.
   \par
   In terms of precedence (in the case that 
   two rules match the same index in the input string), place prefixes of other 
   delimiters last. For example, a rule matching '///' should come before a
   rule matching '//'.
   
   \see LuminousDelimiterRule
  
  */
  
  public $simple_types = array(); /**< An array of LuminousSimpleRule objects.
    These are processed after $delimited_types.
    \see LuminousSimpleRule
    \see LuminousSimpleRuleList
  */

  
  
  public $ignore_outside = array(); /**< An array of LuminousDelimiterRules.
    These specify valid ranges for the language to be parsed, i.e. some 
    languages don't affect the whole file, like JavaScript in HTML, which should
    be parsed only inside script tags and php which should be 
    parsed only inside <?(php)? and ?> tags.
    
    Everything inside the tags is parsed and then removed from the string. What
    remains is passed to the child_grammar. This has the strange property that
    the child grammar is actually either a sibling or parent if the source 
    document is considered as a tree.
    e.g. the inheritance for php goes: php->javascript->css->html.
    
  */
  
  
  public $ignore_outside_strict = true; /**< If true, absence of ignore outside
    tags anywhere in the text means the whole text is ignored. Else, the whole
    text is deemed valid 
    \see $ignore_outside
  */
  
  /** Child grammar as another LuminousGrammar
    object. 
    \see $ignore_outside */
  public $child_grammar = null; 
    
  
  /** Sets the language to be case insensitive or not. Most languages are not.
   * If \c true, this will cause Luminous to append 'i' to any regular 
   * expressions, and to use case insensitive string methods. This WILL slow
   * down the process a lot, don't enable it unless you \em really need it 
   */ 
  public $case_insensitive = false;
  
  
  public $state_transitions = null;
  
  public $state_type_mappings = array();
  
  public function GetTransitions($statename)
  {
    if (isset($this->state_transitions[$statename]))
      return $this->state_transitions[$statename];
    return null;
  }
  
  public 
  function GetMapping($identifier)
  {
    if (array_key_exists($identifier, $this->state_type_mappings))
      return $this->state_type_mappings[$identifier];
    return $identifier;
  }
  
  
  /* 
   * everything below here is not read by Luminous, it is provided for 
   * convenience of grammar definition and may be read by
   *   SetSimpleTypeRules() to set up the simple_types array
   * populated with LuminousSimpleRule and LuminousSimpleRuleList objects.
   * But calling this function is optional, populating the array can
   * be done manually if one prefers. 
   * 
   */ 
    
  public $operator_pattern = '/(?:%OPERATOR)+/'; /**<  Suggested pattern to 
    match operators. %OPERATOR is a placeholder.
    \note This property is not directly read by luminous. */
    
  /** Suggested operators */  
  public $operators = array('\+', '\*', '-', '\/', '=', '&gt;', '&lt;', '=',
  '!', '%', '&amp;', '\|', '~', '\^', '\[', '\]');
//   public $operators = array('(?:[\+\*\-\/=!%\|~\^\[\]]|(?:&(?:[gl]t|amp);))+');

  
  public $numeric_regex = LUMINOUS_C_NUMERIC_REGEX;  /**< Suggested pattern 
    to match literal numerical types. 
    \note This property is not directly read by luminous.
    */

  /** Suggested pattern to match language keywords. %KEYWORD is a placeholder
   \note This property is not directly read by luminous.
   */
  public $keyword_regex =  '/(?<![a-zA-Z0-9_$])(?:%KEYWORD)(?![a-zA-Z0-9_])/';
    

  /** Suggested pattern to match type declarations. %TYPE is a placeholder 
   * \note This property is not directly read by luminous.   
   */
  public $type_regex =  '/(?<![a-zA-Z0-9_$])(?:%TYPE)(?![a-zA-Z0-9_])/';
  
  
   /** Suggested pattern to match function names. %FUNCTION is a placeholder
    * \note This property is not directly read by luminous.    
    */  
  public $function_regex =  '/(?<![a-zA-Z0-9_$])(?:%FUNCTION)(?![a-zA-Z0-9_])/';     
    
  //!  \note This property is not directly read by luminous.
  public $keywords = array(); 
  //!  \note This property is not directly read by luminous.
  public $functions = array();
  //!  \note This property is not directly read by luminous.
  public $types = array();
  //!  \note This property is not directly read by luminous.
  public $oo_separators = array();
  
  
  
  
  
  
  
  
  
  /**
   * \brief Convenience function for configuring the simple_types array.
   * \sa LuminousGrammar::simple_types
   * 
   * Sets the 'simple_types' for the following types from their class members,
   *    using the placeholder:
   * \li OO Separators: LuminousGrammar::$oo_separators
   * \li NUMERIC, LuminousGrammar::$numeric_regex
   * \li TYPE, LuminousGrammar::$types, LuminousGrammar::$type_regex, '\%TYPE'
   * \li KEYWORD, LuminousGrammar::$keywords, LuminousGrammar::$keyword_regex, '\%KEYWORD'
   * \li FUNCTION, LuminousGrammar::$functions, LuminousGrammar::$function_regex, '\%FUNCTION'
   * \li OPERATOR, LuminousGrammar::$operators, LuminousGrammar::$operator_pattern, '\%OPERATOR'
   */ 
  protected function SetSimpleTypeRules()
  {
    $grammar = &$this;
    
    if ($grammar->numeric_regex !== null)
      $grammar->simple_types[] = new LuminousSimpleRule(1, "NUMERIC", 
        LUMINOUS_REGEX, $grammar->numeric_regex);  
    
    if (count($grammar->types))
      $grammar->simple_types[] = new LuminousSimpleRuleList(2, "TYPE", 
        LUMINOUS_REGEX, $grammar->types, $grammar->type_regex, "%TYPE");
          
    if (count($grammar->keywords))        
      $grammar->simple_types[] = new LuminousSimpleRuleList(1, 'KEYWORD', 
        LUMINOUS_REGEX, $grammar->keywords, 
        $grammar->keyword_regex, '%KEYWORD');
    
    if (count($grammar->functions))
      $grammar->simple_types[] = new LuminousSimpleRuleList(2, "FUNCTION", 
        LUMINOUS_REGEX, $grammar->functions, $grammar->function_regex, 
        "%FUNCTION");
        
    foreach($this->oo_separators as $oo)
    {
      $this->simple_types[] = new LuminousSimpleRule(4, 'OBJ', LUMINOUS_REGEX,
      "/(?>[a-zA-Z0-9_]+)(?=(?:$oo))/");
      
      $this->simple_types[] = new LuminousSimpleRule(4, 'OO', LUMINOUS_REGEX,
      "/(?<=(?:$oo))(?:[a-zA-Z0-9_]+)/");
    }      
        
    if (count($grammar->operators))
      $grammar->simple_types[] = new LuminousSimpleRuleList(4, 'OPERATOR', 
        LUMINOUS_REGEX, $grammar->operators, $grammar->operator_pattern, 
        '%OPERATOR');
  }
  
  

  protected function SetInfoLanguage($language)
  {
    $this->info['language'] = $language;
  }
  protected function SetInfoVersion($version)
  {
    $this->info['version'] = $version;
  }
  
  
  protected function SetInfoAuthor(array $author)
  {
    $this->info['author'] = $author;
  }
  protected function SetInfoWebsite($website)
  {
    $this->info['website'] = $website;
  }
  
  
  public function SetRuleset(&$src)
  {
  }
  
}

