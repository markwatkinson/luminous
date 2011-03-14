<?php


/**
 * \file luminous_grammars.class.php
 * \brief Grammar lookup table definition.
 */ 
/**
 * \class LuminousGrammars
 * \author Mark Watkinson
 * \brief A glorified lookup table for languages to grammars.
 * One of these is instantiated in the global scope at the bottom of this source.
 * The parser assumes it to exist and uses it to look up grammars.
 * Users seeking to override grammars or add new grammars should add their
 * grammar into '$luminous_grammars'.
 *
 */

class LuminousGrammars
{
  private $lookup_table = array(); /**< 
    The language=>grammar lookup table. Grammar is an array with keys:
    grammar (the string of the grammar's class name),
    file (the path to the file in which its definition resides)
    dependencies (the language name for any grammars it this grammar 
      either depends on or needs to instantiate itself)
  */
  private $default_grammar = null; /**< 
    Language name of the default grammar to use if none is found
    for a particular language */

  private $descriptions = array();
  
  private $resolved_dependencies = array();

  /**
   * Adds a grammar into the table, or overwrites an existing grammar.
   *
   * \param language_name may be either a string or an array of strings, if
   *    multiple languages are to use the same grammar
   * \param $grammar the name of the LuminousGrammar object as string, (not an 
   * actual instance!)
   * \param lang_description a human-readable description of the language.
   * \param file the path to the file in which the grammar is defined.
   * \param dependencies optional, a string or array of strings representing
   *    the language names (given in another call to AddGrammar, as 
   *    language_name), on which the instantiation of this grammar depends.
   *    i.e. any super-classes, and any classes which this grammar instantiates
   *    itself.
   *
   */
  public function AddGrammar($language_name, $grammar, 
    $lang_description, $file=null, $dependencies=null)
  {
    $d = array();
    if (is_array($dependencies))
      $d = $dependencies;
    elseif ($dependencies !== null)
      $d = array($dependencies);
    
    $insert = array('grammar'=>$grammar, 
                    'file'=>$file, 
                    'dependencies'=>$d);
                    
    if (is_array($language_name))
    {
      foreach($language_name as $l)
      {
        $this->lookup_table[$l] = $insert;
        $this->AddDescription($lang_description, $l);
      }
    }
    else
    {      
      $this->lookup_table[$language_name] = $insert;
      $this->AddDescription($lang_description, $language_name);
    }
  }
  
  private function AddDescription($language_name, $language_code)
  {
    if (!isset($this->descriptions[$language_name]))
      $this->descriptions[$language_name] = array();
    $this->descriptions[$language_name][] = $language_code;
  }
  
  
  private function UnsetDescription($language_name)
  {
    foreach($this->descriptions as &$d)
    {
      foreach($d as $k=>$l)
      {
        if($l === $language_name)
          unset($d[$k]);
      }
    }    
  }

  /**
   * Removes a grammar from the table
   *
   * \param language_name may be either a string or an array of strings, each of
   *    which will be removed from the lookup table.
   */
  public function RemoveGrammar($language_name)
  {
    if (is_array($language_name))
    {
      foreach($language_name as $l)
      {
        unset($this->lookup_table[$l]);
        $this->UnsetDescription($l);
      }
    }
    else
    {
      $this->UnsetDescription($language_name);
      unset($this->lookup_table[$language_name]);
    }
  }

  /**
   * Sets the default grammar. This is used when none matches a lookup
   * \param grammar the LuminousGrammar object
   */
  public function SetDefaultGrammar($grammar)
  {
    $this->default_grammar = $grammar;
  }
  
  
  /**
   * Method which retrives the desired grammar array, and 
   * recursively settles the include dependencies while doing so.
   * \param language_name the name under which the gramar was originally indexed
   * \param default if true: if the grammar doesn't exist, return the default
   *    grammar. If false, return false
   * \return the grammar-array stored for the given language name
   * \internal
   * \see LuminousGrammars::GetGrammar
   */ 
  private function GetGrammarArray($language_name, $default=true)
  {
    $g = null;
    if (array_key_exists($language_name, $this->lookup_table))
      $g =  $this->lookup_table[$language_name];
    elseif($this->default_grammar !== null && $default === true)
      $g = $this->lookup_table[$this->default_grammar]; 
    
    if ($g === null)
      return false;

    // Break on circular dependencies.
    if (!isset($this->resolved_dependencies[$language_name]))
    {
      $this->resolved_dependencies[$language_name] = true;    
      foreach($g['dependencies'] as $d)
      {
        $this->GetGrammarArray($d, $default);
      }    
      if ($g['file'] !== null)
        require_once($g['file']);
    }
    return $g;
  }

  /**
   * Returns a grammar for a language
   * \param language_name the name under which the gramar was originally indexed
   * \param default if true: if the grammar doesn't exist, return the default
   *    grammar. If false, return false
   * \return The grammar, the default grammar, or false.
   */
  function GetGrammar($language_name, $default=true)
  {
    $resolved_dependencies = array();
    $g = $this->GetGrammarArray($language_name, $default);
    $resolved_dependencies = array();
    
    if ($g !== false)
      return new $g['grammar'];
    return false;
  }
  /**
   * Returns a list of known aliases for grammars. 
   * \return a list, the list is such that each item is itself a list whose
   *    elements are aliases of the same grammar. eg:
   * [  
   *    ['c', 'cpp'],
   *    ['java'],
   *    ['py', 'python']
   * ]
   * etc.
   * 
   */
  function ListGrammars()
  {
    $l = $this->descriptions;    
    return $l;
  }
  
}
