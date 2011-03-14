<?php

/*
 * Copyright 2010 Mark Watkinson
 * 
 * This file is part of Luminous.
 * 
 * Luminous is free software: you can redistribute it and/or
 * modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Luminous is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Luminous.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */



/** 
 * \file grammarutils.php
 * 
 * \defgroup GrammarUtils GrammarUtils
 * A set of predefined constants and functions which relate to commonly used
 * rules within Luminous grammars to prevent grammar writers from having to 
 * endure too much drudgery.
 * \since 0.4.0
 * 
 * 
 */
 
require_once('rule.class.php');

/**
 * \ingroup GrammarUtils
 * 
 * Generic regular expression to match C-like numeric literals
 * 
 * Should match hex, octal, decimal, and fractional types.
 * Recognised suffixes are case insensitive:
 * 
 * f, u, l, m and d (the latter two for Java and C#)
 * 
 * 
 * \note this is currently the default for LuminousGrammars::SetSimpleTypeRules()
 * to use, so you shouldn't have to use this.
 * 
 */ 
define('LUMINOUS_C_NUMERIC_REGEX', '
  /(?<![[:alnum:]_<$])
  (
    #hex 
    (?:0[xX][0-9A-Fa-f]+[uUlL]*)
    |
    # regular number
    (?:
      (?>[0-9]+)
      
      (?: 
        # fraction        
        [uUlLdDmM]+
        |
        (?:(?:\.(?>[0-9]+))?(?:(?:[eE][\+\-]?)?[0-9]+)?[FfdD]?)
      )?
    )
    |
    (?:
      # or only after the point, float x = .1;
      \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?[0-9]+)?[FflLdD]?
    )
  )
  /x');




/**
 * \ingroup GrammarUtils
 * 
 * \brief Returns a rule to match a comment
 * 
 * \param $delim1 the opening delimiter
 * \param $delim2 the closing delimiter
 * \param $callback the callback function to execute on matches
 * \return a LuminousDelimiterRule to match a generic comment
 */ 
function luminous_generic_comment($delim1, $delim2,
$callback='luminous_type_callback_comment')
{
  return new LuminousDelimiterRule(0, 'COMMENT', 0, $delim1, $delim2, 
                                   $callback);
}

/**
 * \ingroup GrammarUtils
 *
 * \brief returns a rule to match a single line comment
 * 
 * The delim argument is used in a regular expression.
 * Do not pre-escape it.
 */
function luminous_generic_comment_sl($delim,
  $callback='luminous_type_callback_comment')
{
  $delim = preg_quote($delim, '/');
  return new LuminousDelimiterRule(0, 'COMMENT', 
    LUMINOUS_REGEX|LUMINOUS_COMPLETE,
    "/(?:$delim).*/",
    null,
    $callback
  );
}

/**
 * \ingroup GrammarUtils
 *
 * \brief returns a rule to match a single line doc comment
 * 
 * The delim argument is used in a regular expression.
 * Do not pre-escape it.
 */
function luminous_generic_doc_comment_sl($delim,
  $callback='luminous_type_callback_doccomment')
{
  $la = preg_quote($delim[strlen($delim)-1], '/');
  $delim = preg_quote($delim, '/');
   
  return new LuminousDelimiterRule(0, 'DOCCOMMENT', 
    LUMINOUS_REGEX|LUMINOUS_COMPLETE,
    "/(?:$delim)(?!$la).*/",
    null,
    $callback
  );
}


/**
 * \ingroup GrammarUtils
 * \brief returns a rule to match a doc comment
 * \param $delim1 the opening delimiter
 * \param $delim2 the closing delimiter
 * \param $callback the callback function to execute on matches
 * \return a LuminousDelimiterRule to match a doc comment
 */

function luminous_generic_doc_comment($delim1, $delim2,
  $callback = 'luminous_type_callback_doccomment')
{
  $la = preg_quote( $delim1[ strlen($delim1)-1 ], '/' );
  $delim1 = preg_quote($delim1, '/');
  $delim2 = preg_quote($delim2, '/');
  return new LuminousDelimiterRule(0, 'DOCCOMMENT', 
    LUMINOUS_REGEX|LUMINOUS_COMPLETE,
    "/$delim1.*?$delim2/s",
    null,
    $callback
  );
}




/**
 * \ingroup GrammarUtils
 * \brief returns a rule to match a string
 * 
 * \param $delim1 the opening delimiter
 * \param $delim2 the closing delimiter
 * \param $callback the callback function to execute on matches
 * 
 * \return a LuminousDelimiterRule to match a string
 * 
 * \note for strings with SQL-style escaping (i.e. doubling), use 
 * GrammarUtils::luminous_generic_sql_string
 */
function luminous_generic_string($delim1, $delim2,
  $callback = 'luminous_type_callback_generic_string')
{
  return new LuminousDelimiterRule(0, 'STRING', 0, $delim1, $delim2, $callback);
}

/**
 * \ingroup GrammarUtils
 * \brief returns a rule to match a string escaped by doubling the delimiter.
 * \param $delimiter the open and close delimiter
 * \param $callback the callback function to execute on matches
 * 
 * \return a LuminousDelimiterRule to match a string.
 * 
 * \note SQL only escapes its single-quoted strings like this, the SQL reference
 * is just to distinguish this from GrammarUtils::luminous_generic_string
 * 
 * \note the arguments to this function are used in regular expressions
 */
function luminous_generic_sql_string($delimiter = "'",
  $callback = 'luminous_type_callback_sql_single_quotes',
  $backslash_escapes = false)

{
  $escp = ($backslash_escapes)? '(?<!\\\)(\\\\\\\\)*' : '';
  $backslash_alt= ($backslash_escapes)? "| \\$delimiter" : '';
  $regex = "/

  $delimiter
  (?: [^$delimiter]* | $delimiter$delimiter $backslash_alt)*

  $escp
  $delimiter

  /sx";
  
  return new LuminousDelimiterRule(0, 'STRING', 
                                   LUMINOUS_COMPLETE|LUMINOUS_REGEX, $regex, 
                                   null,  $callback);
}

/**
 * \ingroup GrammarUtils
 * \brief returns a rule to match a regular expression literal 
 * \param $modifiers The legal modifiers for a regex (e.g ismx)
 * \param $callback The callback to execute on matches
 * \param $preceding_chars The regex s deemed to be a regular expression literal
 *      and not a division symbol because of its context. This argument allows
 *      you to change which characters may precede a regular expresison literal
 *      (ignoring whitespace).
 * \note the arguments to this function are used in regular expressions.
 * \return a LuminousDelimiterRule to match a doc comment
 */

function luminous_generic_regex($modifiers = '', 
  $callback = 'luminous_type_callback_generic_regex',
  $preceding_chars = "[\(\[,=:;\?!\|\&~]|^")
{
  $modifiers_ = strlen($modifiers)? "[$modifiers]*" : '';
  $regex = 
  "/
  (?<=$preceding_chars)
  [\s]* 
  \/(?![\/\*])
  (?:.*?[^\\\])*?
  (?:\\\\\\\\)*\/
  $modifiers_/sx";
  
  return new LuminousDelimiterRule(0, 'REGEX', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
    $regex, null, $callback);
}

/**
 * \ingroup GrammarUtils
 * \brief Returns a rule to match a generic shebang (e.g. #!/usr/bin/env python)
 * \param $identifier the opening delimiter
 * \param $end the ending delimiter
 * \param $callback The callback to execute on matches
 * 
 * \return a LuminousDelimiterRule to match a shebang
 */
function luminous_generic_shebang($identifier='#!', $end="\n", $callback=null)
{
  return new LuminousDelimiterRule(0, 'SHEBANG', 0, $identifier, $end,
                                   $callback);
}

/**
 * \ingroup GrammarUtils
 * \brief Returns a rule to match a generic constant
 * A constant is defined as being any word which starts with an upper case
 *      character, which may be followed by either upper case or numeric 
 *      characters and must be at least three characters long.
 * \return a LuminousSimpleRule to match a constant
 */
function luminous_generic_constant()
{
  return new LuminousSimpleRule(3, 'CONSTANT', LUMINOUS_REGEX,
    '/(?<![[:alnum:]_&<])[A-Z_][A-Z0-9_](?>[A-Z0-9_]*)(?![a-z])/');
}


/*
  redundant.
  
function luminous_generic_regex_literal($modifiers, 
  $preceding_chars = "[
                        \(\[,=:;\?!\|\&~
                      ]")
{
  return "/
  (?<=($preceding_chars))
  [\s]* 
  \/(?![\/\*])
  (?:.*?[^\\\])*?
  (?:\\\\\\\\)*\/
  [$modifiers]*
  /x";
}
*/

/*
 * redundant
function luminous_generic_str_literal_sql($delimiter='\'')
{
  return 
  "/
  (?:$delimiter$delimiter)
  |
  (?:(?:$delimiter.*?(?<!$delimiter)$delimiter)(?!$delimiter))
  /sx";
}
*/

