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



require_once('strsearch.class.php');
require_once('utils.php');
require_once('grammar.class.php');
require_once('rule.class.php');


/**
 * \file luminous.class.php
 *  
 * \brief The central luminous class and a few utility functions.
 * 
 * \defgroup LuminousUtils LuminousUtils
 * A set of ulities used by the Luminous class.
 * \internal
 */ 





/**
 * \class Luminous
 * 
 * \brief Luminous is an automaton which handles source code highlighting.

 * \see Luminous::ParseDelimiters 
 * \see Luminous::ParseRegex
 * 
 * \todo Some of these methods are missing documentation.
 * \todo probably should merge the non-stateful code into the stateful code.
 *       I don't think there's any specific advantage to keeping the old code.
 * 
 * \warning don't re-use luminous objects. 
 * 
 */

class Luminous
{
  /** Config options */
  
  /// See: \ref verbosity
  public $verbosity = 4;
  
  /**
   * Sets whether or not the input is already HTML entity escaped
   * \since 0.30
   */ 
  public $pre_escaped = false;
  
  
  /**
   * Sets whether or not to tag the input. This may be disabled in the case 
   * that callback functions are used to transform matched elements.
   * \since 0.5.0
   */
  
  public $tag_input = true;
  
  /**
   * Sets whether or not to treat lines as being separate entities regarding
   * tagging. i.e. in some situations it is desirable to close all open tags
   * at the end of a line and open them again at the start of the next line
   * (for example if the output will be used inside HTML where each line 
   * appears in a different element.).
   * Other times, this is not important.
   * 
   * \since 0.5.0
   */
  public $separate_lines = true;

  
 
  /**
   * Not yet supported
   * \todo this.
   */  
  public $lang_specific_classnames = false;
  
  public $language = null;
  
  
  
  
  
  
  private $input_src = ""; /**< Input - a source string */
  private $output = ""; /**< Output - a tagged source string */
  private $grammar; /**< Grammar - langauge rules */

  /** State related data */
  private $index = 0; /**< The input string pointer */
  private $open_tag = null; /**< The currently open delimiter */
  private $open_index = null; /**< Index of the currently open delimiter */
  private $open_delim_len = null; /**< Length of the currently open delimiter */
  private $close_delim = null; /**< The corresponding closing delimiter which is being  
      searched for */

  /** Extractions/aliasing data */
  private $html_extractions = array(); /**< Alias to representation lookup */
  

  private $num_extractions = 0; /// Number of extractions (aliases) performed 
  private $extractions_offset = 0;  /**< Alias ID to start at (so as not to 
    collide with existing alises from other parsers */
  

  private $callback_data = null; /**< Somewhere to put data to be accessible 
    from a callback function whose arg list does not allow passing it */
  private $callback_data2 = null; ///sometimes one just isn't enough.
   
  /// Luminous sticks a newline at the end of the string is none exists, 
  /// we record here whether we need to remove it again afterwards.
  private $append_newline = false;

  /** Optimisations */
  private $starts = array(); /// indices of possible start tags.
  private $ends = array(); /// indices of possible end tags
  private $ends_length = array(); /// lengths of end tags (references $ends)
  private $ends_excludes = array(); /// Which end tags should be excluded
  private $num_ends = 0; /// count($this->ends)
  
  private $last_str_index_gne = 0; 
  private $last_arr_index_gne = 0;

  private $last_str_index_gns = 0;
  private $last_arr_index_gns = 0;

  /// true if there are no ignore tags to worry about
  private $parse_all = false;
  
  /// count($this->grammar->escape_chars)
  private $num_escape_chars = 0;
  
  /// Tries to avoid unnecessary calls to is_escaped
  private $escape_cache = array();
  
  /**
   * delimiter rules from the grammar are copied here so the array can be 
   * changed, as a non-hit of a rule anywhere in the string means it's best to 
   * remove it so we won't iterate over it again
   */ 
  private $local_rules = array();
  private $local_simple_types = array();
  
  
  
  
  private $strsearch_opening_delimiters = null; // LuminousStringSearch object.
  private $strsearch_closing_delimiters = null;
  
  
  /** safe mode is provided to try to prevent DOS attacks, it will limit the
   * level of nesting allowed to 10
   */
  private $safe_mode = true;
  
  
  
  public function Debug()
  {
    debug_print_backtrace();
    
    echo "LUMINOUS INFO:\n";
    echo "Stack:\n";
    print_r($this->stack);
    echo "Index: {$this->index}\n";
  }
  
  
  
  
  // State related code.
  
  private $stateful = false;  
  private $stack = array();
  private $state_tokens = array();
  
  private $state_tokens_compiled = array();
  private $state_output_buffer = "";
  private $state_tokens_cached = array();
  
  private $state_mapping_cache = array();


  function SetupStates()
  {
    $this->stateful = true;
    
    foreach($this->local_rules as $l)
    {
      if (!isset($this->state_tokens_compiled[$l->name]))
        $this->state_tokens_compiled[$l->name] = array();
      $this->state_tokens_compiled[$l->name][] = $l;
    }
  }
  
  
  function GetState()
  {
    if (!isset($this->stack[0]))
      return null;
    
    return $this->stack[count($this->stack)-1];
    
  }
  
  function GetStateName()
  {
    if (!isset($this->stack[0]))
      return 'GLOBAL';
    
    return $this->stack[count($this->stack)-1]['state'];
  }
  
  function LoadTokensForState($state)
  {
    
    
    if (isset($this->state_tokens_cached[$state]))
    {
      $this->state_tokens = $this->state_tokens_cached[$state];
      return;
    }    
    $t = $this->grammar->GetTransitions($state);
    
    if ($t === null)
      $this->state_tokens = array();
    elseif($t === '*' || $t === '!')
    {
      $this->state_tokens = $this->local_rules;
        
      if ($t === '!')
      {
        foreach($this->state_tokens as $i=>$t)
        {
          if ($t->name === $state)
            unset($this->state_tokens[$i]);
        }
      }        
    }
    elseif (is_array($t)) 
    {      
      $this->state_tokens = array();
      
      foreach($t as $state_name)
      {
        if (isset($this->state_tokens_compiled[$state_name]))                  
          $this->state_tokens = array_merge(
            $this->state_tokens,
            $this->state_tokens_compiled[$state_name]
        );
      }
    }
    else
      die('State transitions error');
    $this->state_tokens_cached[$state] = $this->state_tokens;
    
  }  
  
  /**
   * 
   * \brief Gets the end index of a state.
   *    returns the currently suspected ending index of a state. The end
   *    may not be correct as there may be more rules to consider. If the end
   *    is given as an index x and then the next state begins at index y, 
   *    x is the true end if x \< y. If x \>= y then y represents a nested state,
   *    and x will have to be recalculated after state y is closed.
   * 
   * 
   * \return the index 
   * \param state the state array (as stored on the stack)
   * \param real_end if a state ends with the sequence 'xyz' and the sequence
   *    abcxyz is encountered, it may sometimes be preferable to determine
   *    the index of 'c', or in other situations, the index of 'z'. 
   *    real_end=true includes the delimiter xyz, real_end=false does not.
   */
  function GetStateEnd($state, $real_end=true)
  {
    $state_rule = $state['rule'];
    $regex = $state_rule->type & LUMINOUS_REGEX;
    /* we don't want to find a finish on the same index as start because
     * otherwise a string like "hello" may be detected as 
     * <str>"</str>hello<str>"</str>
     */
    $finish = max($this->index-1, $state['start']);
    $match = $state_rule->delim_2;
    
    $escaped = true;
    while($escaped)
    {      
        
      if ($regex)
        $finish = $this->strsearch_closing_delimiters->PregSearch(
          $state_rule->delim_2, 
          $finish+1,
          $match);
      else
        $finish = $this->strsearch_closing_delimiters->StrSearch(
          $state_rule->delim_2, 
          $finish+1
          );
          
      if ($finish === false)
        break;
      
      $escaped = $this->CharIsEscaped($finish);
    }
    
    if ($state_rule->type & LUMINOUS_STOP_AT_END)
    {
      $end = $this->Get_Next_End($this->index);
      if ($end !== null && ($finish===false || $end < $finish))
        return $end;
    }    
    if ($finish === false)
      return strlen($this->input_src);
    
    if ($real_end)
      $finish += strlen($match);
    
    return $finish;
  }
  
  function PushState($state, $rule, $start, $s_delim)
  {    
//     echo "Pushing state: $state<br>";
    $finish = ($rule->type & LUMINOUS_COMPLETE)? $start+strlen($s_delim) : null;
    
    $s = substr($this->input_src, $this->index, $start-$this->index);
    
    if (!count($this->stack))
      $this->output .= $s;
    else    
      $this->state_output_buffer .= $s;
    
    $rule_ = $rule;
    if ($rule->type & LUMINOUS_DYNAMIC_DELIMS)
    {
      $rule_ = clone $rule;
      $dd = $this->GetDynamicDelimAtIndex($start + strlen($s_delim)); 
      $rule_->delim_2 = $this->MatchDynamicDelim($s_delim, $rule_->delim_2, 
        $dd,
        $rule_->type);
      $finish = null;
#     echo "$dd => {$rule_->delim_2}<br>";
    }
    
    
    $array = array('state' => $state, 
                    'start' => $start, 
                    'finish' => $finish, 
                    'rule' => $rule_, 
                    'buffer_index' => strlen($this->state_output_buffer)
                    );

    // protection vs infinite loops
    $i=count($this->stack)-1;
    for(; $i >= 0; $i--)
    {
      $s_ = $this->stack[$i];
      if ($s_['start'] !== $start)
        break;
      if ($s_['state'] === $state)
      {
        $this->state_output_buffer .= $this->input_src[$this->index++];
        return;
      }
    }
    
    $this->stack[] = $array;
    
    if (
      ($rule->type & LUMINOUS_CONSUME) && !($rule->type & LUMINOUS_COMPLETE)
     || $rule->type & LUMINOUS_DYNAMIC_DELIMS)
    {
//       echo $rule->type . '<br>';
      $start += strlen($s_delim);
      $this->state_output_buffer .= $s_delim;
    }    
      
    assert($start >= $this->index);
//     echo "{$this->index}...$start<br>";
    $this->index = $start;
  }
  
  
  
  
  function PopState($index=null)
  {    
    $completed_state = array_pop($this->stack);
    
    if ($index !== null)
      $completed_state['finish'] = $index;
    
    if ($completed_state['finish'] === null)       
      $completed_state['finish'] = $this->GetStateEnd($completed_state);
    
    /* XXX: this indicates a problem with the grammar's rule - it's catching 
     * zero length matches, which means that the index pointer won't be
     * progressed, which means infinite loop.
     * so we discard the rule.
     */    
    if($completed_state['finish'] - $completed_state['start'] == 0)
    {
      $this->DiscardStateRule($completed_state['rule']);
      return;
    }
    
    $s = substr($this->input_src, $this->index, 
                $completed_state['finish'] - $this->index);
    $this->state_output_buffer .= $s;
    
    $s_n = $completed_state['state'];
    if (isset($this->state_mapping_cache[$s_n]))
      $tag = $this->state_mapping_cache[$s_n];
    else    
    {
      $tag = $this->grammar->GetMapping($s_n);
      if ($tag === null)
        $tag = false;
      $this->state_mapping_cache[$s_n] = $tag;
    }
    
    $rule = $completed_state['rule'];
    
    $callback = $rule->callback;
    
    if ($callback !== null || ($tag !== false && $this->tag_input))
    {
      $b_i = $completed_state['buffer_index'];
      // we split the string output buffer into two, the latter of which is the 
      // code matched by THIS rule, from its opening point.
      $buffer_0 = substr($this->state_output_buffer, 0, 
                        $b_i);
      $buffer_1 = substr($this->state_output_buffer, 
                        $b_i);
      
      if ($callback !== null)
      {
        if (isset($rule->match_array))
          $buffer_1 = call_user_func($callback, $buffer_1, $rule->match_array);          
        else 
          $buffer_1 = call_user_func($callback, $buffer_1);
      }
      
       // false indicates a dummy state, which we don't tag.
      if ($tag !== false && $this->tag_input) 
      {
        if (strpos($buffer_1, '<') === false)
          $buffer_1 = tag_block($tag, $buffer_1, $this->separate_lines);
       else 
        {
          // we split this to avoid overwriting prior replacements.
          $b1_ = preg_split('/(<&R_[0-9]+>)/', $buffer_1, -1, 
            PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
          foreach($b1_ as &$b_)
          {
            if ($b_[0] !== '<')
              $b_ = tag_block($tag, $b_, $this->separate_lines);
          }
          $buffer_1 = implode('', $b1_);
        }
      }
       
      // Hide the string if there are only null states below
      $null = true;

      foreach($this->stack as $s)
      {
        $null = $this->grammar->GetMapping($s['state']) === null;
        if (!$null) break;
      }
      
      if ($null)
        $buffer_1 = $this->AddReplacement($buffer_1);
      
      $this->state_output_buffer = $buffer_0 . $buffer_1;
    }
    
    if (empty($this->stack))
    {
      $this->output .= $this->state_output_buffer;
      $this->state_output_buffer = "";
    }
    assert($this->index <= $completed_state['finish']) or die("{$this->index} > {$completed_state['finish']}");
    $this->index = $completed_state['finish'];
    
    
  }  
  
  function DiscardStateRule($rule)
  {
    foreach($this->state_tokens_cached as $k=>&$v)
    {
      foreach($v as $i=>$r)
      {
        if ($r == $rule)
        {
          unset($this->state_tokens_cached[$k][$i]);
        }
      }
    }
  }
  
  
  




  
  /**
   * Constructor. Nothing interesting here.
   * \param src the source string (optionally set this later with 
   *    SetSource)
   * \see Luminous::SetSource
   */ 
  function __construct($src = null)
  {
    if ($src != null)
      $this->input_src = $src;
  }
  
  /**
    * Sets the input source string
    * \param src the string of source code to parse
    */
  public 
  function SetSource($src)
  {
    $this->input_src = $src;
  }

  /**
   * \internal
   * Sets the extraction offset to start at.  This is for recursive calls on
   * the same string,  so that different parser objects do not collide and
   * overwrite each other's changes with their own
   * \param num The base number to start from.
   */

  public 
  function SetExtractionsOffset($num)
  {
    $this->extractions_offset = $num;
  }

  /**
   * Sets Luminous's grammar
   * \param grammar the LuminousGrammar object
   */

  public 
  function SetGrammar(LuminousGrammar $grammar)
  {
    $this->grammar = $grammar;
    $this->grammar->SetRuleset($this->input_src);
    $this->num_escape_chars = count($this->grammar->escape_chars);
    $this->local_rules = $grammar->delimited_types;
    $this->local_simple_types = $grammar->simple_types;
        
    foreach($this->local_simple_types as $k=>$r)
    { 
      if ($r->verbosity > $this->verbosity)
        unset($this->local_simple_types[$k]);
    }
    foreach($this->local_rules as $k=>$r)
    {
      if ($r->verbosity > $this->verbosity)
        unset($this->local_rules[$k]);
        
    }
        
//     foreach($this->local_simple_types as $k=>$s)
//     {
//       $r = $s;
//       $text = $s->text;
//       if ($s & LUMINOUS_LIST)
//       {
//         foreach($rule->values as $v)
//           if ($rule->replace_str !== null)
//       }
//       $r = new LuminousDelimiterRule(0, $s->name,
//         $s->type | LUMINOUS_COMPLETE, $s->text);
// //       $r->type |= LUMINOUS_COMPLETE;
//       $this->local_rules[] = $r;
//       
//       unset($this->local_simple_types[$k]);
//     }
    
    
    
    if ($this->grammar->state_transitions !== null)
      $this->SetupStates();
    
    if (isset($this->grammar->info['language']) && 
      strlen($this->grammar->info['language']))
      $this->language = strtoupper($this->grammar->info['language']);
    else
      $this->language = "oops";
  }


  


  /**
   * Determines if a character at a given index is escaped or not
   *  \param index the index of the character. If null, the current $this->index
   * is used
   *  \return true or false
   */
  private 
  function CharIsEscaped($index=null)
  {
    $index = ($index !== null)? $index : $this->index;
    if (isset($this->escape_cache[$index]))
      return $this->escape_cache[$index];
    
    $e = is_escaped(
      $this->input_src, 
      $index,
      $this->grammar->escape_chars);
    $this->escape_cache[$index] = $e;
    return $e;
  }
  


  /**
   * Adds a 'replacement' to the internal data. The given string isreplaced by
   * some pattern, and the alias lookup stored internally
   * \param replacement the string to be replaced
   * \param tag the type (tag) of the data which is being stored. This may be 
   *    null but if the data is all of one type, it really shouldn't be.
   * \return the alias which now represents the string.
   */  
  public 
  function AddReplacement($replacement, $tag = null)
  {
    $i = $this->num_extractions+$this->extractions_offset;
    $pattern = '<&R_'. $i . '>';
    if ($tag !== null)
    {
      if ($this->tag_input);
        $replacement = tag_block($tag, $replacement, $this->separate_lines);
    }
    $this->html_extractions[$i] = $replacement;
    
    
    ++$this->num_extractions;
    return $pattern;
  }
  
  
  
  private 
  function DoNestedReplacements($rule, $start, $end)
  {    
    $callback = $rule->callback;
    $tag = $rule->name;
    
    $substr = substr($this->input_src, $start, $end-$start);
    $args = array(null, null);
    $use_cb = ($callback !== null && $this->verbosity >= 3);
    $s = preg_split('/(<&R_[0-9]+>)/', $substr, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
    foreach($s as &$s_)
    {
      if ($s_[0] === '<')
        continue;
      if ($use_cb)
      {
        $args[0] = $s_;
        $args[1] = isset($rule->match_array)? $rule->match_array : null;
        $s_ = call_user_func_array($callback, $args);
      }
      $s_ = tag_block($tag, $s_, $this->separate_lines);
    }    
    $this->output .= $this->AddReplacement(implode('', $s), null); 
  }
  
  /**
   * \param rule the current rule for which to search for an ending
   * \param match_txt the text of the next match, returned as a reference
   * \return the index of the next matching delimiter for the given rule_text
   */
  private function GetNextEndDelim($rule, &$match_txt)
  {
    
    $rule_type = $rule->type;
    
    $index = $this->open_index + $this->open_delim_len - 1;
    $regex = $rule_type & LUMINOUS_REGEX;
    $m_ = ($regex)?null : $this->close_delim;
    $end = false;
    while(1)
    {
      if ($regex)
        $end = $this->strsearch_closing_delimiters->PregSearch($this->close_delim, $index+1, $m_);
      else
        $end = $this->strsearch_closing_delimiters->StrSearch($this->close_delim, $index+1);
      
      if ($end !== false && $this->CharIsEscaped($end))
        ++$index;
      else
        break;
    }
    $match_txt = $m_;
    return $end;
  }

  
  /**
   * Finds the corresponding ending delimiter to the currently open starting
   * delimiter.
   * It also completes the process of extracting a delimited type and moves the
   * input pointer along appropriately.
   */
  private 
  function DoEndingDelim($rule, $end=false)
  {
    $rule_type = $rule->type;    

    $match_txt = "";
    
    if ($end === false)
      $end = $this->GetNextEndDelim($rule, $match_txt);
    if ($end === false)
      $end = strlen($this->input_src);
    
    $stop_at_end = ($rule_type & LUMINOUS_STOP_AT_END);    
    $stopping_at_end = false;
    if ($stop_at_end)
    {
      $close = $this->Get_Next_End($this->open_index);
      if ($end === false || ($close !== null && $close <= $end) )
      {
        $end = $close;

        if (
          !$this->ends_excludes[$close])
        {
          $end -= $this->ends_length[$close];
          
          // This is probably not the right way to handle this situation
          // there might be a bug elsewhere that causes this to happen.
          $end = max($this->open_index+1, $end);
        }
        $stopping_at_end = true;
      }
    }
    

    $match_len = strlen($match_txt);
    
    $t = $this->open_tag;

    if (!$stopping_at_end && !($rule_type & LUMINOUS_EXCLUDE))
      $end += $match_len;
    $this->DoNestedReplacements($rule, $this->open_index, $end);

    if ($rule_type & LUMINOUS_EXCLUDE)
    {
      $this->output .= $this->AddReplacement(substr($this->input_src, $end, $match_len));
      $end += $match_len;        
    }

    $this->index = $end;
    return true;
  }




  /**
   * Escapes the input string to make it suitable for processing.
   * This should be called once and only once on any input. Child grammars 
   * shouldn't escape the string again.
   */
  public 
  function EscapeInput()
  {
    
    $this->input_src = str_replace("&", "&amp;", $this->input_src);
    $this->input_src = str_replace(">", "&gt;", $this->input_src);
    $this->input_src = str_replace("<", "&lt;", $this->input_src);
    
    // DOS line endings
    $this->input_src = str_replace("\r\n", "\n", $this->input_src);
    
    // Mac line endings?
    $this->input_src = str_replace("\r", "\n", $this->input_src);
    
    if (strlen($this->input_src) && $this->input_src[strlen($this->input_src)-1]
      != "\n")
    {
      $this->input_src .= "\n";
      $this->append_newline = true;
    }
  }
  
  
  
  
  private function
  GetDynamicDelimAtIndex($index)
  {
    $dyn_delim = $this->input_src[$index];
    
    // don't split entities
    if($dyn_delim === '&')
    {
      preg_match('/^&[^;]+;/', 
                 substr($this->input_src, $index), $m_);
      $dyn_delim = $m_[0];
    }
    
    elseif (ctype_alnum($dyn_delim) || $dyn_delim === '_')
    {
      
      if (preg_match('/^[a-zA-Z_0-9]+/', 
                     substr($this->input_src, $index), 
                     $m_)
        )
        $dyn_delim = $m_[0];
    }
    return $dyn_delim;
  }
  
  private function
  MatchDynamicDelim(&$s_delim, $e_delim, $dyn_delim, $rule_type)
  {
    $s_delim .= $dyn_delim;
    
    if ($rule_type & LUMINOUS_REGEX)
    {
      $e = match_delimiter($dyn_delim, true);
      
      if ($rule_type & LUMINOUS_COMPLETE)
        $e_delim = '/' . $e . '/';
      else
        $e_delim = '/(' . $e . ')' . $e_delim;
    }
    else
      $e_delim = match_delimiter($dyn_delim, false);
    
    return $e_delim;
  }

  private 
  function GetStartDelim(&$match_rule, &$s_delim, &$matches=null)
  {    
    $rules = null;
    if ($this->stateful)
      $rules = $this->state_tokens;
    else
      $rules = $this->local_rules;
    
    $next = -1;
    
    foreach ($rules as $key=>$rule)
    {
      $type = $rule->type;        
      $opener = $rule->delim_1;
      $rule_text = $rule->delim_1;
      
      $r = false; // index of the next match.
      $false = false;
      
      $m_ =  ($type & LUMINOUS_MATCHES)? null : false;
      
      if ($type & LUMINOUS_REGEX)
        $r = $this->strsearch_opening_delimiters->PregSearch($opener, 
              $this->index, 
              $rule_text,
              $m_
            );
      else
        $r = $this->strsearch_opening_delimiters->StrSearch($opener,
               $this->index,
               ($next === -1)? null
                 : $next
             );

      if ($r === false) // no match.
      {
        if ($this->stateful)
          $this->DiscardStateRule($rule);
        else
          unset($this->local_rules[$key]);
        continue;
      }

      if ($next == -1 || $r < $next)
      { 
        $next = $r;
        $s_delim = $rule_text;
        $match_rule = $rule;
        
        if (($rule->type & LUMINOUS_COMPLETE) && $m_ !== null && $m_ !== false)
          $matches = $m_;
        
        // no point continuing if we've got an instant adjacent match
        if ($next === $this->index)
          return $next;
      }
    }
    return $next;
  }
  
  /**
   * Parses delimited types according to the grammar rules
   */
  private 
  function ParseDelimiters()
  {

    $strlen = strlen($this->input_src);
    $this->index = 0;
    $last_end = -1;

    if(count($this->starts))
      $this->index = $this->starts[0];

    $this->output = substr($this->input_src, 0, $this->index);
    
    $this->output .= "<&START>";

    $start_open = true;
    
    $last_index = $this->index-1;
    
    while ($this->index < $strlen)
    {
//       echo '<pre>';
//       echo "{$this->index}\n";
//       print_r($this->stack);
//       echo '</pre>';

      /* XXX: These are just safety checks. Triggering one probably indicates
       * a bug in a grammar.
       * TODO handle them better.
       */
      if (!$this->stateful && $this->index <= $last_index)
      {
        // This PROBABLY indicates a zero-length match, which is a grammar bug.
        // TODO handle this when the rule is 'completed', then discard the rule.
        if($this->index === $last_index)
        {
          $this->index++;
          continue;
        }
        die("Looks like the parser hit an infinite loop. This shouldn't have happened.<br>Index: {$this->index}");
      }
      /* the stateful parser can legally hit the same index more than once,
       * as long as it has different transitions available to it.
       * 
       * zero length matches for the stateful engine are handled in PopState.
       */
      if($this->stateful && $this->index < $last_index)
      {
         die("Looks like the parser hit an infinite loop. This shouldn't have happened. State:<pre>" . print_r($this->stack, true) 
           . "</pre><br>Index: {$this->index}<br>");
      }
      
      
      $last_index = $this->index;
      
      if ($this->stateful)
      {
        $this->LoadTokensForState($this->GetStateName());
      }
      
      
      $next = $strlen;
      $s_delim = null;
      $e_delim = null;
      $open_tag = null;
      $match_array = null;
      $next = $this->GetStartDelim($rule, $s_delim, $match_array);
      
      if ($match_array !== null)
        $rule->match_array = $match_array;
      
      if ($this->stateful && !empty($this->stack))
      {
        $state = $this->GetState();
//         echo "State {$state['state']}<br>";
        $finish = $state['finish'];
        $stretchy = false;
        if ($finish === null)
        {
          // if finish is null the state is 'stretchy', i.e. the state can be
          // extended by child-states.
          $stretchy = true;
          $finish = $this->GetStateEnd($state, false);
        }        
        // overlapping is defined as when one rule intersects with another but 
        // does not fully engulf it. Think of it as being like malformed xml.
        // Does not apply to stretchy states.
        $overlapping = false;        
        if (!$stretchy && $next !== -1 
          && ($rule->type & LUMINOUS_COMPLETE 
              && !($rule->type && LUMINOUS_DYNAMIC_DELIMS)
             )
         )
        {
          // rule ends at
          $e = $next + strlen($s_delim);
          if ($next < $finish && $e > $finish)
            $overlapping = true;
        }
        // this condition is a mess.
        if (
          ($this->safe_mode && count($this->stack) >= 20)
          ||
          $next == -1 
          || $overlapping 
          || ($finish < $next && $stretchy) 
          || ($finish <= $next && !$stretchy)
        )
        {
          if (!empty($this->stack))
          {
            $this->PopState();
            continue;
          }
          else
          {
            $this->output .= copy_string($this->input_src, $this->index);
            break;
          }
        }
      }
      
      // Don't parse the delimiters if it comes after an 'END'
      if (!$this->parse_all)
      {
        $e = $last_end;
        if ($e !== null && $e <= $this->index)
        {
          $e = $this->Get_Next_End();
          $last_end = $e;
        }        
        if ($e !== null 
          && ($e <= $next || $next == -1))
        {
          $s = substr($this->input_src, $this->index, $e-$this->index);
          $this->output .= "$s<&END>";
          $this->index = $e;
          $st = $this->Get_Next_Start($e);
          $start_open = false;
          if ($st !== null)
          {
            $s = substr($this->input_src, $e, $st-$e);
            $this->output .= "$s<&START>";
            $this->index = $st;
            $start_open = true;
            continue;
          }
          else
          {
            $this->output .= substr($this->input_src, $this->index);
            while (count($this->stack))
              $this->PopState();
            break;
          }
        }
      }
      
      if ($next >= $strlen || $next < 0)
      {
        if ($this->stateful && !empty($this->stack))
        {
          $this->PopState();
          continue;
        }
        $this->output .= substr($this->input_src, $this->index);
        break;
      }
      
      if ($this->stateful)
      {
        $this->PushState($rule->name, $rule, $next, $s_delim);
        continue;
      }
      
      
      // trim whitespace from the start of the match, which prevents 
      // 'background colour' highlighting from bleeding on a rule which may have
      // had to specify whitespace as a captured match due to fixed-length 
      // lookbehind assertions
      if (strlen($s_delim) && ctype_space($s_delim[0]))
      {
        $t = ltrim($s_delim);
        $dist = strlen($s_delim) - strlen($t);
        $s_delim = $t;
//         $this->output .= substr($this->input_src, $this->index, $dist);
        $next += $dist;
      }
      
      $next_rule_type = $rule->type;
      $next_rule_name = $rule->name;
      $e_delim = $rule->delim_2;
      $open_tag = $rule->name;
      
//       echo "DELIM: $s_delim\n";          
      
      if ($next_rule_type & LUMINOUS_DYNAMIC_DELIMS)
      {
        $dyn_delim = $this->GetDynamicDelimAtIndex($next+strlen($s_delim));
      }
      
      // if we get to here we're parsing the delimiters.
        
      $this->output .= substr($this->input_src, $this->index, 
                              $next - $this->index);
      
      // complete match, no need to search for an ending.
      if (($next_rule_type & LUMINOUS_COMPLETE)
        && !($next_rule_type & LUMINOUS_DYNAMIC_DELIMS))
      {
        $s = strlen($s_delim);
        $this->DoNestedReplacements($rule, $next, $next+$s);
        
        $this->index = $next + $s;
      }
      // uh oh
      else
      {
        if ($next_rule_type & LUMINOUS_DYNAMIC_DELIMS)
        {
          $e_delim = $this->MatchDynamicDelim($s_delim, $e_delim, $dyn_delim,
            $next_rule_type);
        }
        

        if ($this->CharIsEscaped($next))
        {
          $this->index = $next+1;
          continue;
        }
        
        $this->open_delim_len = strlen($s_delim);
        
        if ($next_rule_type & LUMINOUS_EXCLUDE)
        {
          $inc = strlen($s_delim);
          $this->output .=  $this->AddReplacement(
            substr($this->input_src, $next, $inc));

          
          $next += $inc;
          $this->open_delim_len = 0;
        }
        
        $this->index = $next;
        $this->open_tag = $next_rule_name;
        $this->open_index = $next;
        
        $this->close_delim = $e_delim;
        
        if (!$this->DoEndingDelim($rule))
        {
          $this->output .= $this->input_src[$this->index];
          $this->index = $next+1;
          continue;
        }
      }
      
      
      if ($next_rule_type & LUMINOUS_END_IS_END)
      {
        $this->output .= '<&END>';
        $start_open = false;
        
        $st = $this->Get_Next_Start($this->index);
    
        if ($st !== null)
        {
          $s = substr($this->input_src, $this->index, $st-$this->index);
          $this->output .= "$s<&START>";
          $this->index = $st;
          $start_open = true;
          continue;
        }
        else
        {
          $this->output .= substr($this->input_src, $this->index);
          break;
        }
      } 
    }
    
    while (count($this->stack))
      $this->PopState();

    if ($start_open)
      $this->output .= "<&END>";


  }


  /**
   *    Callback function for a preg_replace_callback in ParseRegex()
  */
  private 
  function Parse_Regex_Replace_Callback($matches)
  {     
    // need to exclude <>s
    
    $group_no = $this->callback_data2->group;
    if (!isset($matches[$group_no]))
    {
      trigger_error("Grammar error {$this->grammar->info['language']}: No such captured group '$group_no' in rule 
      {$this->callback_data2->name}");
      return $matches[0];
    }
    $g = $matches[$group_no];
    
    if (strpos($g, '<') !== false)
    {

      $g_split = preg_split('/(<&R_\d+>)/', $g, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
      foreach($g_split as &$g_)
      {
        if ($g_[0] !== '<')
          $g_ = tag_block($this->callback_data, $g_, $this->separate_lines);
      }
      $g = $this->AddReplacement(implode('', $g_split), null);
      
    }
    else
      $g = $this->AddReplacement($g, $this->callback_data);
    
    if ($group_no !== 0)
    {
      $pre = "";
      $post = "";
      $c = count($matches);
      for ($i=1; $i<$group_no; $i++)
        $pre .= $matches[$i];
      
      for ($i=$group_no+1; $i<$c; $i++)
        $post .= $matches[$i];
      
      $s = $pre . $g . $post;
      
      if ($this->callback_data2->consume_other_groups)
        $s = $this->AddReplacement($s);
      return $s;
    }
    return $g;
    
  }

  private 
  function Parse_Regex_Wrapper_Callback($matches)
  {
    return $this->AddReplacement($this->ParseRegex($matches[1]));
  }
  
  private 
  function DoSimpleRule($rule, $needle, $str)
  {
    $cb_array = array($this, 'Parse_Regex_Replace_Callback');
    $type = $rule->type;
    $regex = ($type & LUMINOUS_REGEX) > 0;
    $name = $rule->name;
    $this->callback_data = $rule->name;
    $this->callback_data2 = $rule;
    
    $num_extractions_start = $this->num_extractions;
    if ($regex)
    {
      if ($this->grammar->case_insensitive === true)
        $needle .= 'i';      
      $str1 = false;
      $str1 = preg_replace_callback($needle, $cb_array, $str);      
      if ($str1 === null)
      {
        while ($this->num_extractions > $num_extractions_start)
        {
          array_pop($this->html_extractions);
          $this->num_extractions--;
        }
        trigger_error("PCRE error: " . pcre_error_decode(preg_last_error()));
      }
      else if ($str1 !== false)
        $str = $str1;
    }
    else
    {            
      $str_rep = ($this->grammar->case_insensitive)?'str_ireplace' : 
      'str_replace';        
        // need a str_replace_callback!          
        $str = $str_rep($needle, 
                        "<$name>$needle</$name>",
                        $str);
    }
    return $str;
  }
  
  
  /**
   * Deals with SimpleRules
   * \param str the input string to parse.
   * \return the input tagged according to the given simple rules.
   */ 
  private 
  function ParseRegex($str)
  {
    // in some versions of PHP this seems to be 'self::Parse_Regex_Replace_Callback'
    // in others, it's this
    $cb_array = array($this, 'Parse_Regex_Replace_Callback');
    foreach($this->local_simple_types as $rule)
    {
      $name = $rule->name;
      $this->callback_data = &$rule->name;
      $type = $rule->type;
      
      $regex = ($type & LUMINOUS_REGEX) > 0;
      $literal = !isset($rule->replace_str);
      
      $needle = &$rule->text;
      
      $list = ($type & LUMINOUS_LIST) > 0;
      $str_start = $str;
      $num_extractions_start = $this->num_extractions;
      if ($list)
      {
        foreach($rule->values as $v)
        {
          if ($rule->replace_str !== null)
            $v = str_replace($rule->replace_str, $v, $needle);
          $str = $this->DoSimpleRule($rule, $v, $str);         
        }
      }
      else
        $str = $this->DoSimpleRule($rule, $needle, $str);         
    }
    return $str;
  }


  private 
  function Get_Next_End($index=null)
  {
    if ($index === null)
      $index = $this->index;
    if (!$this->num_ends)
      return null;
    $i = 0;
    if ($index >= $this->last_str_index_gne)
      $i=$this->last_arr_index_gne;
    
    $num = $this->num_ends;
    
    for($i; $i<$num; $i++)
    {
      $s = $this->ends[$i];
      if ($s >= $index)
      {
        $this->last_str_index_gne = $s;
        $this->last_arr_index_gne = $i;
        return $s;
      }
    }
    return null;
  }

  private 
  function Get_Next_Start($index=null)
  {
    if ($index === null)
      $index = $this->index;

    $i = 0;
    if ($index >= $this->last_str_index_gns)
      $i=$this->last_arr_index_gns;

    $num = count($this->starts);

    for($i; $i<$num; $i++)
    {
      $s = $this->starts[$i];
      if ($s >= $index)
      {
        $this->last_str_index_gns = $s;
        $this->last_arr_index_gns = $i;
        return $s;
      }
    }
    return null;
  }  




  private 
  function DoStartEnds()
  {

    $s_cache = array();
    $e_cache = array();
    $merge = false;
    $num=0;
    
    foreach ($this->grammar->ignore_outside as &$ignore)
    {
      
      $s = $ignore->delim_1;
      $e = $ignore->delim_2;

      $s_matches = array();
      $e_matches = array();
      if ($ignore->type & LUMINOUS_REGEX)
      {
        if ($this->grammar->case_insensitive)
        {
          $s .= 'i';
          $e .= 'i';
        }
          
        preg_match_all($s, $this->input_src, $s_matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
        preg_match_all($e, $this->input_src, $e_matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

        foreach($s_matches as $match)
        {
          $group = $match[0][0];
          $offset = $match[0][1];
          if ($ignore->type & LUMINOUS_EXCLUDE)
            $offset += strlen($group);
          $this->starts[] = $offset;
          $s_cache[$offset] = $num++;
        }
        foreach($e_matches as $match)
        {
          $group = $match[0][0];
          $offset = $match[0][1];
          if (!($ignore->type & LUMINOUS_EXCLUDE))
            $offset += strlen($group);
          if (isset($s_cache[$offset]))
          {
            // zero length match.
            unset($this->starts[$s_cache[$offset]]);
            $merge = true;
          }
          else
          {
            $this->ends[] = $offset;
            $this->ends_length[$offset] = strlen($group);
            $this->ends_excludes[$offset] = ($ignore->type & LUMINOUS_EXCLUDE) > 0;
          }
        }
      }
      else
      {
        $pos = -1;
        $strpos = ($this->grammar->case_insensitive)?'stripos' : 'strpos';        
        
        while ( ($pos = $strpos($this->input_src, $s, $pos+1)) !== false )
        {
          $this->starts[] = $pos;
          $s_cache[$pos] = $num++;
        }
        $pos=-1;
        while ( ($pos = $strpos($this->input_src, $e, $pos+1)) !== false )
        {
          if (isset($s_cache[$pos]))
          {
            // zero length match.
            unset($this->starts[$s_cache[$pos]]);
            $merge = true;
          }
          else
          {
            $this->ends[] = $pos;
            $this->ends_length[$pos] = strlen($e);
            $this->ends_excludes[$pos] = ($ignore->type & LUMINOUS_EXCLUDE) > 0;
          }
        }
      }
    }
    $this->num_ends = count($this->ends);
    if ($merge)
      $this->starts = array_merge($this->starts);

  }
  
  
  
  private 
  function SplitStartEnds()
  {
    // Here we're going to split up the <&START>(.*?)<&END>/s blocks, but
    // on largeish source files (say 200k) this may trigger the backtrack
    // limit in PCRE, so instead we do it the old fashioned way.
    // we send everything between start/end through a 'parse me'
    // function.
    $split = array();
    $p = -1;
    $last_p = 0;
    $strlen_start = strlen('<&START>');
    $strlen_end = strlen('<&END>');
    while (($p = strpos($this->output, '<&START>', $last_p)) !== false)
    {
      $split[] = substr($this->output, $last_p, $p-$last_p);
      
      // This check should be redundant.
      if (($e = strpos($this->output, '<&END>', $p)) !== false)
      {
        $lower_bound = $strlen_start + $p;
        $length = $e-$lower_bound;
        $split[] = $this->Parse_Regex_Wrapper_Callback(
            array(false, substr($this->output, $lower_bound, $length))
        );
        $p = $e + $strlen_end;
      }
      
      $last_p = $p;
    }
    $split[] = substr($this->output, $last_p);
    
    $this->output = implode('', $split);    
  }
  
  
  


  /**
   * Handles the parsing process. It's recommended to use Easy_Parse instead,
   * which wraps this function (and others).
   * \return a string which is formatted with an internal tagging spec.
   *    But the caller should not worry about that, it's only for recursion.
   *
   * \throw Exception in the event that the PCRE module fails somehow. The
   *    error is given in the exception message, but this is likely down to an
   *    exceptionally large string which is triggering the PCRE backtrack limit.
   *    The parser should survive, but the string will not by syntax highlighted
   * 
   */

    
  public 
  function Parse_Full()
  {
    $this->DoStartEnds();


    $pos = -1;
    
    $parse_nothing = false;
    $parse_everything = false;
    
    // no legal start/end
    if (count($this->grammar->ignore_outside) &&
      (!count($this->starts) || !count($this->ends)
        )
      )
    {
      // Strict mode - don't parse anything
      if ($this->grammar->ignore_outside_strict)
      {
        $parse_nothing = true;
      }
    }

    if (!count($this->grammar->ignore_outside))
    {
      $parse_everything = true;
    }

    if ($parse_nothing)
    {
      $this->output = $this->input_src;
    }
    else
    {
      if ($parse_everything)
        $this->parse_all = true;
      
      $this->ParseDelimiters();

      if ($parse_everything)
      {
        $this->output = str_replace("<&START>", "", $this->output);
        $this->output = str_replace("<&END>", "", $this->output);
        $this->output = $this->Parse_Regex_Wrapper_Callback(array(0, $this->output));
      }
      else
        $this->SplitStartEnds();
    }
    
    if ($this->output === null && preg_last_error() !== PREG_NO_ERROR )
    {
      throw new Exception("PCRE error: " . pcre_error_decode(preg_last_error()));
      $this->output = $this->input;
      return $this->input;
    }
    

    if ($this->grammar->child_grammar !== null)
    {
      $highlighter = new Luminous();
      $highlighter->verbosity = $this->verbosity;
      $highlighter->SetSource($this->output);
      $highlighter->SetGrammar($this->grammar->child_grammar);      
      $highlighter->SetExtractionsOffset(($this->num_extractions+$this->extractions_offset));
      $highlighter->FinaliseSetup();
      $this->output = $highlighter->Parse_Full();
    }
    
    $this->DoReplacements();
    return $this->output;
  }


  private 
  function Replacements_cb($matches)
  {
    $i = $matches[1];
    if (isset($this->html_extractions[$i]))
    {
      $this->num_extractions--;
      return $this->html_extractions[$i];
    }
    else return $matches[0];
  }
  
  

  
  private 
  function DoReplacements()
  {
    while($this->num_extractions > 0)
    {
      $num_start = $this->num_extractions;
      $this->output = preg_replace_callback("/<&R_([0-9]+)>/",
        array($this, "Replacements_cb"),  $this->output);
        
      $num_end = $this->num_extractions;  
      if ($num_start === $num_end)
      {
        trigger_error("The parser was unable to perform all substitutions 
        ($num_end are missing). If this is the only error, please ensure your 
          rules do not target the parser's internal tagging system. 
          See the doxygen API docs for details on what you've probably done wrong.
          Output is probably malformed");
          
        return $this->output;
      }
        
    }
    
    return $this->output;
  }

  /**
   * Recommended method to complete the entire parsing process. Takes in a 
   * source string and its language or gramamr and returns to you a formatted 
   * HTML string (we hope).
   * 
   * \param source_string: The source to parse, as a string
   * \param grammar The LuminousGrammar to apply to the source.
   * \return return A string which is 'tagged' by Luminous's spec. This should
   * then be given to an instance of LuminousFormatter to be turned into 
   * something more universal.
   * \throw Exception if no suitable grammar is given.
   * 
  */

  public 
  function Easy_Parse($source_string, LuminousGrammar $grammar)
  {
    if (!is_subclass_of($grammar, 'LuminousGrammar'))
      throw new Exception("Bad grammar");
    $this->SetSource($source_string);
    
    if (!$this->pre_escaped)      
      $this->EscapeInput();
    
    $this->SetGrammar($grammar);
    
    $this->FinaliseSetup();
    
    $this->Parse_Full();
    if ($this->append_newline)
    {
      $pos = strrpos($this->output, "\n");
      if ($pos !== false)
      {
        $s1 = substr($this->output, 0, $pos);
        $s2 = (($pos+1)<strlen($this->output))? substr($this->output, $pos+1) : "";
        $this->output = $s1 . $s2;
      }
    }
//     $this->output = preg_replace("/(<LANG_.*?)\n/s", "$1", $this->output);
    return $this->output;
  }
  
  
  
  
  
  private 
  function FinaliseSetup()
  {
    $this->strsearch_opening_delimiters = new LuminousStringSearch(
      $this->input_src, !$this->grammar->case_insensitive
    );
    $this->strsearch_closing_delimiters = new LuminousStringSearch(
      $this->input_src, !$this->grammar->case_insensitive
      );    
  }
  
  
  
}

