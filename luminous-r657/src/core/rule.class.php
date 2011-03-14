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

/** 
 * \defgroup LuminousRuleFlag LuminousRuleFlag
 * \brief flags used in rule definitions
 * 
 * The flags allow the caller to specify various information about the rules
 * they have defined allowing the rule objects to behave very flexibly. Flags
 * can be combined with a logical or operation.
 * 
 * \warning Some of these flags have subtly different meanings dependent upon 
 * whether they're applied to a delimited type rule, or an LuminousBoundaryRule
 * (ignore-outside) delimiter set. Some flags may not make sense if applied to 
 *  a simple rule. 
 * Certain combinations of flags may be nonsensical, for example 
 * LUMINOUS_EXCLUDE|LUMINOUS_COMPLETE.
 * 
 */ 

/**
 * \ingroup LuminousRuleFlag 
 * %LuminousDelimiterRule: Delimiters are consumed as part of the type matching process,
 * but they themselves are not highlighted as being that type.
 * 
 * %LuminousBoundaryRule:  Doesn't 'consume' the deimiters; the delimiters will not
 * be removed from the string. This means a child grammar can still see them. 
 * 
 */
define("LUMINOUS_EXCLUDE", 0x01); 
  
  
/**
 * \ingroup LuminousRuleFlag 
 *  Complete - the rule is a regex and matches a full start-to-end pattern. 
 * In this case, delim_2 is ignored as delim_1 represents the whole match.
 * 
 * In the case of stateful rules, this may only be used in 'unbreakable' rules,
 * i.e. if your rule has legal state transitions from itself, and those 
 * sub-states could feasibly contain the ending delimiter, then do not use this 
 * flag because %Luminous needs to evaluate where the end of the state is 
 * after it exhausts all sub-states.
 * 
 * 
 * This flag is only used in LuminousDelimiterRule objects.
 */
define("LUMINOUS_COMPLETE", 0x02); 
  
/**
 * \ingroup LuminousRuleFlag 
 * The rule's delimiters are regexes, not simple strings.
 */ 
define("LUMINOUS_REGEX", 0x04); 



/**
 * \ingroup LuminousRuleFlag 
 * 
 * The rule has user defined delimiters.
 * This means the character or string* immediately following delim_1's match, 
 * DELIM, is 
 * used as the ending delimiter for the type as well. If delim_2 is set, 
 * the ending match pattern will be concatenated as so: 
 * 
 *  <code>(DELIM) . delim_2</code>
 * 
 * If LUMINOUS_REGEX is set, do not set the leading '/' delimiter in delim_2
 * for the full type, e.g: 
 * 
\code
new LuminousDelimiterRule("REGEX",  LUMINOUS_REGEX|LUMINOUS_DYNAMIC_DELIMS, "/%r/", "[iomx]* /");
\endcode  
 * 
 *  may match the string:
 * 
 *   <code>%r{this is a regular expression}i</code>
 * 
 *  The '{' is identified as the delimiter, it is matched internally to '}' and
 *  the parser then searches for the first (non-escaped) occurrance of:
\code
/(\\{)[iomx]* /
\endcode
 *  Everything between the start of the first match and the end of the end-match 
 *  is taken as being the given type. 
 *
 *
 * \note \li If the following character is not alphanumeric (or _), it is used as the 
 *  delimiter. If it is alphanumeric (or _), then the delimiter is taken as
 *  being the string up to the first non [a-zA-z0-9_] character. This second 
 * case is for heredoc type constructs. 
 * \li this cannot currently be used to match the perl-like construct 
 *      s{..}{..},  %Luminous just isn't flexible enough for it. 
 * \li This is not yet available for stateful matching.
 * 
 */ 
define("LUMINOUS_DYNAMIC_DELIMS", 0x08); 


/**
 * \ingroup LuminousRuleFlag 
 * 
 * The script's closing tag also acts as an end delimiter. e.g. given the
 * settings $delim_1 = '//' and $delim_2 = "\n", the PHP code:
 * 
 * <code>  
 *  // a comment ?>
 * </code>
 * 
 * is a legal comment because the ?> acts as a delimiter and ends the comment 
 *  even though a newline has not been encountered. (the ?> is not consumed)  
 * 
 * 
 */  
define("LUMINOUS_STOP_AT_END", 0x10); 


/**
 * \ingroup LuminousRuleFlag
 * The end delimiter also marks the end of the languages's legal parsing range.
 * This is necessary when a type's end delimiter is the same as an 'ignore 
 * outside' end delimiter, and this type would consume it, meaning that it would
 * be missed by Luminous that we had exited the legal parsing range.
 * 
 * An example is an HTML comment, &lt;!-- comment //--&gt;, the last character
 * also means 'stop parsing now'.
 * 
 */ 
define("LUMINOUS_END_IS_END", 0x20); 



/**
 * \ingroup LuminousRuleFlag
 * \internal
 * The rule's text member is an array, not a string. External calls should use
 * the LuminousSimpleRuleList type where this flag is set automatically.
 */ 
define("LUMINOUS_LIST", 0x40);



/**
 * \ingroup LuminousRuleFlag
 * 
 * In occasional cases this flag is useful. In most though, this is possibly
 * going to be completely unhelpful.
 * 
 * If set and a callback function is set, the rule's matches array is passed to 
 * the callback function, as well as the matching string.
 * The callback in this case has the signature:
 * 
 * str function (str $full_matching_string, array $matches).
 * 
 * See the match argument of preg_match for more information
 * http://www.php.net/manual/en/function.preg-match.php
 * 
 * This flag can only be used with: LUMINOUS_REGEX|LUMINOUS_COMPLETE
 * 
 * \warning Using this with stateful rules has important caveats: the match will
 * be what was captured by preg_match. The string is evaluated from left to
 * right, therefore if there are any nested rules then these will \b not be 
 * present in the matches array because they won't yet have been evaluated at 
 * the time preg_match was called. $full_matching_string however will be the 
 * complete evaluated string. Relying on $matches is therefore discouraged 
 * when using stateful mode, except for rules where there are no valid 
 * transitions within that rule.
 * 
 * \warning Similarly, using it with any rules has the problem that a rule 
 * may have been 'split' to avoid capturing nested rules from previous
 * languages. (i.e. a PHP block shouldn't be greyed out by an HTML comment 
 * which encloses it). In that case, the callback is fired for each segment
 * but in each case the matches array will be the same. This won't occur if 
 * you're not using nested languages, however.
 * 
 */ 
define('LUMINOUS_MATCHES', 0x80);

/**
 * Applies only to stateful rules which are 'incomplete' (i.e. do not have
 * LUMINOUS_COMPLETE set). With the stateful engine, the default behaviour 
 * upon capturing a start delimiter is to stay exactly where it is and 
 * simply load in the new state transitions before resuming. 
 * With this flag, the engine will also consume the matched  start-delimiter 
 * such that it will not be capturable by child states as well.
 */
define('LUMINOUS_CONSUME', 0x100);



/**
 * \class LuminousSimpleRule
 * \brief A simple rule type.
 * 
 * This rule is intended to be used for things like operators, keywords, i.e.
 * things that could be matched by a simple strpos or regex search. 
 */ 
class LuminousSimpleRule
{
  /** 
   * Rule name -- used in Luminous to name XML tags.
   * \internal 
   */
  public $name = null;
  
  /** 
   * Rule flags -- used by Luminous to discern how the rule should be treated
   * and executed \sa LuminousRuleFlag
   * \internal 
  */
  public $type = null;
  
  /** 
   * Rule text (regex or simple string)
   * \internal 
   */
  public $text = null;
  
  /**
   * Callback to be executed on matching this rule. 
   * \sa LuminousTypeCallbacks
   * \internal */
  public $callback = null;
  
  /** 
   * Regular expression group number which is used as the match. The other 
   * groups are ignored. Groups must therefore be linear, fully wrap the string,
   * and not nested.
   * \internal
   */ 
  public $group = 0;
  
  /**
   * Specifies whether Luminous should consume the ignored groups anyway. 
   * 
   * \internal 
   */ 
  public $consume_other_groups = false;
  
  /**
   * Luminous Verbosity level at which the rule is respected
   * \internal
   */ 
  public $verbosity = 0;
  
  /**
   * \param verbosity the verbosity level to cut off matching this rule. 
   *  See: \ref verbosity
   * \param name the name of the type this rule matches. This should be 'STRING'
   *    or 'COMMENT', or so.
   * \param type Some combination (logical or) of LuminousRuleFlag types, 
   *    which describes the rule.
   * \param text The text/pattern to match
   * \param callback [Not implemented for SimpleRules yet] 
   * 
   * \param group <b>See warning before use.</b> optional specification of the 
   * regex group which should be hit by the rule. Default 0 (the whole match).
   * 
   * This is a bit of a hack because PCRE doesn't let you set variable
   * width assertions. It's intended for when you want to match something very
   * contextual but you don't want to format the context. An example is in a
   * Makefile, you might want to format a pattern like:
   * 
   * <code>someobject.o: someobject.c someobject.h </code>,
   * 
   * but have a different rule for everything before vs after the ':' . 
   * To avoid hitting other instances of the ':' character, you might set a 
   * regex like:
   * 
   * <code>'/(^.*?:[ ]*)(.+?$)/m'</code>,
   * 
   * and set group=2.
   * 
   * \param consume_other_groups If 'group' is set and is nonzero, this flag
   * specifies whether the other groupss hould be consumed as part of the 
   * matching process. If false, they may still be matched by a later rule.
   * Default: false
   * 
   * 
   * 
   * \warning
   * If you specify group, it will 
   * work by concatenating all the groups together to form a full output, but 
   * the group you chose will receive formatting. In an RE with 4 groups, if you
   * set group=3, it would do this:
   * 
   * <code>$output = $1 . $2 . format($3) . $4;</code>
   * 
   * If you have nested groups the output will be wrong (containing repetitions)
   * . If some of your pattern is not contained within a group, it will be lost. 
   * Be careful using this.
   *
   * \see LuminousRuleFlag 
   */ 
  public function __construct($verbosity, $name, $type, $text, $callback=null, $group=0,
  $consume_other_groups = false)
  {
    $this->verbosity = $verbosity;
    $this->name = $name;
    $this->type = $type;
    $this->text = $text;
    $this->callback = $callback;
    $this->group = intval($group);
    $this->consume_other_groups = $consume_other_groups;
  }
}
  
  
/**
 * \class LuminousSimpleRuleList
 * \brief A simple rule type with a list of parameters.
 * 
 * This rule is intended to be used for things like operators, keywords, i.e.
 * things that could be matched by a simple strpos or regex search. This rule
 * adds a list of matches instead of just one.
 * 
 */ 
class LuminousSimpleRuleList extends LuminousSimpleRule
{
  /** \internal */
  public $values = array();
  /** \internal */
  public $replace_str = null;  
  
 /**
   * \param verbosity the verbosity level to cut off matching this rule. 
   *  See: \ref verbosity
   * \param name the name of the type this rule matches. This should be 'STRING'
   *    or 'COMMENT', or so.
   * \param type Some combination (logical or) of LuminousRuleFlag types, 
   *    which describes the rule.
   * \param values an array of strings, each of which is an element that will
   *    be searched for in the source string. 
   * 
   * \param text optional string which may 'wrap' the elements from values in 
   *    some way. If this is set, each element of values will be subsituted 
   *    into this string, and the result will be the search pattern.
   * 
   * \param replace_str The placeholder substring in text which will be
   *    replaced by each element in from values. 
   * 
   * \param callback [Not implemented for SimpleRules yet]
   * 
   * 
   * e.g.: <code>LuminousSimpleRuleList('KEYWORD', LUMINOUS_REGEX, 
   *    array('def','print', 'lambda', 'return'), 
   *    '/\\b(\%KEYWORD)\\b/', '\%KEYWORD')</code>
   * 
   * The regexes: <code> /\\b(def)\\b/, /\\b(print)\\b/, /\\b(lambda)\\b/ and 
   * /\\b(return)\\b/ </code> will be generated from this.
   *
   * \see LuminousRuleFlag 
   */ 
 
 
  public function __construct($verbosity, $name, $type, $values, $text=null, 
    $replace_str=null, $callback=null)
  {
    $this->verbosity = $verbosity;
    $this->name = $name;
    $this->type = $type | LUMINOUS_LIST;
    $this->text = $text;
    $this->values = $values;
    $this->replace_str = $replace_str;
    $this->callback = $callback;
  }
}

/**
 * \class LuminousDelimiterRule
 * \brief Represents a delimited type rule.
 * 
 * Delimited rules are quite important, being used as both markers for deciding
 * which part of the source to parse as well as forming the basis of the actual
 * parsing engine. This class encapsulates the idea of a delimited type into one
 * rule template.
 * 
 */ 

class LuminousDelimiterRule extends LuminousSimpleRule
{

  /** \internal */
  public $delim_1;
  /** \internal */
  public $delim_2;
  
  /**
   * \internal
   * This is read and written to by Luminous during the parsing process.
   * It shall store the 'matches' array of a rule (returned by preg_match), if 
   * the rule is a LUMINOUS_COMPLETE|LUMINOUS_REGEX.
   */
   public $match_array;
  
  /**
   * \param verbosity the verbosity level to cut off matching this rule. 
   *  See: \ref verbosity
   * \param name the name of the type this rule matches. This should be 'STRING'
   *    or 'COMMENT', or so.
   * \param type Some combination (logical or) of LuminousRuleFlag types, 
   *    which describes the rule.   
   * \param delim1 the opener delimiter pattern 
   * \param delim2 optional: if LUMINOUS_COMPLETE is set this is ignored. If 
   *    not, delim2 is the closing delimiter of the type.
   * \param callback an optional callback to execute upon finding a match. 
   * \see LuminousTypeCallbacks
   *    \see LUMINOUS_MATCHES 
   * 
   * 
   *  \see LuminousRuleFlag 
   */ 
  public function __construct($verbosity, $name, $type, $delim1, $delim2=null, $callback=null)
  {
    $this->verbosity = $verbosity;
    $this->name = $name;
    $this->type = $type;
    $this->delim_1 = $delim1;
    $this->delim_2 = $delim2;
    $this->callback = $callback;
  }    
}  


/**
 * \brief Rule to determine legal parsing range
 * A boundary rule is a rule which determines the legal parsing range of a 
 * source text. The rule represents a legal 'starting' delimiter and its
 * ending delimiter. For example, \<?php and ?\> for PHP scripts.
 * 
 * This class is a wrapper to LuminousDelimiterRule.
 */ 

class LuminousBoundaryRule extends LuminousDelimiterRule
{
  /**
   * \param type Some combination (logical or) of LuminousRuleFlag types, 
   *    which describes the rule.   
   * \param delim1 the opener delimiter pattern 
   * \param delim2 The closing delimiter pattern. 
   */ 
  public function __construct($type, $delim1, $delim2)
  {
    parent::__construct(null, null, $type, $delim1, $delim2, null);
  }
  
}

