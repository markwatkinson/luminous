<?php

/// @cond CORE

require_once(dirname(__FILE__) . '/strsearch.class.php');
require_once(dirname(__FILE__) . '/utils.class.php');
require_once(dirname(__FILE__) . '/filters.class.php');
require_once(dirname(__FILE__) . '/tokenpresets.class.php');


/**
 * @brief Base string scanning class
 * 
 * The Scanner class is the base class which handles traversing a string while
 * searching for various different tokens.
 * It is loosely based on Ruby's StringScanner.
 *
 * The rough idea is we keep track of the position (a string pointer) and
 * use scan() to see what matches at the current position.
 * 
 * It also provides some automation methods, but it's fairly low-level as 
 * regards string scanning.
 * 
 * Scanner is abstract as far as Luminous is concerned. LuminousScanner extends 
 * Scanner significantly with some methods which are useful for recording 
 * highlighting related data.
 * 
 * @see LuminousScanner
 */
class Scanner {
  /// Input string
  private $src;
  
  /// length of input string (cached for performance)
  private $src_len;
  
  /// Current index
  private $index;
  
  /**
   * History of matches. This is an array (queue), which should have at most 
   * two elements. Each element consists of an array: 
   * 
   *  0 => Scan pointer when the match was found,
   *  1 => Match index (probably the same as scan pointer, but not necessarily),
   *  2 => Match data (match groups, as map, as returned by PCRE)
   * 
   * @note Numerical indices are used for performance.
   */
  private $match_history = array(null, null);
  
  /// LuminousStringSearch instance (caches preg_* results)
  private $ss;
  
  /// Preset patterns, used by next_match()
  private $patterns = array();

  
  function __construct($src=null) {
    $this->string($src);
  }
    
  /**
   * @brief Gets the remaining string
   * @return The rest of the string, which has not yet been consumed
   */
  function rest() {
    static $pos = -1;
    static $rest = null;
    if ($pos !== $this->index) {
      $pos = $this->index;
      $rest = substr($this->src, $pos);
    }
    return $rest;
  }
  
  /** 
   * @brief Getter and setter for the current position (string pointer).
   * @param $new_pos The new position (leave \c NULL to use as a getter), note 
   * that this will be clipped to a legal string index if you specify a 
   * negative number or an index greater than the string's length.
   * @return the current string pointer
   */
  function pos($new_pos=null) {
    if ($new_pos !== null) {
      $new_pos = max(min((int)$new_pos, $this->src_len), 0);
      $this->index = $new_pos;
    }
    return $this->index;
  }

  /**
   * @brief Moves the string pointer by a given offset
   * @param $offset the offset by which to move the pointer. This can be positve
   * or negative, but using a negative offset is currently generally unsafe.
   * You should use unscan() to revert the last operation.
   * @see pos
   * @see unscan
   */
  function pos_shift($offset) {
    $this->pos( $this->pos() + $offset );
  }
  /**
   * @brief Beginning of line?
   * @return @c TRUE if the scan pointer is at the beginning of a line (i.e. 
   * immediately following a newline character), or at the beginning of the 
   * string, else @c FALSE
   */
  function bol() {
    return $this->index === 0 || $this->src[$this->index-1] === "\n";
  }
  
  /**
   * @brief End of line?
   * @return @c TRUE if the scan pointer is at the end of a line (i.e. 
   * immediately preceding a newline character), or at the end of the 
   * string, else @c FALSE
   */
  function eol() {
    return ($this->eos() || $this->src[$this->index] === "\n");
  }
  
  /**
   * @brief End of string?
   * @return @c TRUE if the scan pointer at the end of the string, else 
   * @c FALSE.
   */
  function eos() {
    return $this->index >= $this->src_len;
  }
  
  /**
   * @brief Reset the scanner
   * Resets the scanner: sets the scan pointer to 0 and clears the match
   * history.
   */
  function reset() {
    $this->pos(0);
    $this->match_history = array(null, null);    
    $this->ss = new LuminousStringSearch($this->src);
  }
  
  /**
   * @brief Getter and setter for the source string
   * @param $s The new source string (leave as @c NULL to use this method as a
   * getter)
   * @return The current source string
   * 
   * @note This method triggers a reset()
   * @note Any strings passed into this method are converted to Unix line 
   * endings, i.e. @c \\n
   */
  function string($s=null) {
    if ($s !== null) {
      $s = str_replace("\r\n", "\n", $s);
      $s = str_replace("\r", "\n", $s);
      $this->src = $s;
      $this->src_len = strlen($s);
      $this->reset();
    }
    return $this->src;
  }
  
  /**
   * @brief Ends scanning of a string
   * 
   * Moves the scan pointer to the end of the string, terminating the 
   * current scan.
   */
  function terminate() {
    $this->reset();
    $this->pos($this->src_len);
  }
  
  /**
   * @brief Lookahead into the string a given number of bytes
   * @param $n The number of bytes.
   * @return The given number of bytes from the string from the current scan 
   * pointer onwards. The returned string will be at most n bytes long, it may
   * be shorter or the empty string if the scanner is in the termination 
   * position.
   * 
   * @note This method is identitical to get(), but it does not consume the 
   * string.
   * @note neither get nor peek logs its matches into the match history.
   */
  function peek($n=1) {
    if ($n === 0 || $this->eos()) return '';
    elseif ($n === 1) return $this->src[$this->index];    
    else return substr($this->src, $this->index, $n);
  }
  
  /**
   * @brief Consume a given number of bytes
   * @param $n The number of bytes.
   * @return The given number of bytes from the string from the current scan 
   * pointer onwards. The returned string will be at most n bytes long, it may
   * be shorter or the empty string if the scanner is in the termination 
   * position.
   * 
   * @note This method is identitical to peek(), but it does consume the 
   * string.
   * @note neither get nor peek logs its matches into the match history.
   */ 
  function get($n=1) {
    $p = $this->peek($n);
    $this->index += strlen($p);
    return $p;
  }
  
  /**
   * @brief Get the result of the most recent match operation.
   * 
   * @return The return value is either a string or \c NULL depending on 
   * whether or not the most recent scanning function matched anything. 
   * 
   * @throw Exception if no matches have been recorded.
   * 
   */
  function match() {
    $index = false;
    if (isset($this->match_history[0])) {
      return $this->match_history[0][2][0];
    }
    throw new Exception('match history empty');
  }
  
  /**
   * @brief Get the match groups of the most recent match operation.
   * 
   * @return The return value is either an array/map or \c NULL depending on
   * whether or not the most recent scanning function was successful. The map
   * is the same as PCRE returns, i.e. group_name => match_string, where 
   * group_name may be a string or numerical index.
   * 
   * @throw Exception if no matches have been recorded.
   */  
  function match_groups() {
    if (isset($this->match_history[0])) 
      return $this->match_history[0][2];    
    throw new Exception('match history empty');  
  }  
  
  /**
   * 
   * @brief Get a group from the most recent match operation
   * @param $g the group's numerical index or name, in the case of named 
   * subpatterns.
   * @return A string represeting the group's contents.
   * 
   * @see match_groups()
   * 
   * @throw Exception if no matches have been recorded.
   * @throw Exception if matches have been recorded, but the group does not 
   * exist.
   */   
  function match_group($g=0) {
    if (isset($this->match_history[0])) {
      if (isset($this->match_history[0][2])) {
        if (isset($this->match_history[0][2][$g]))
          return $this->match_history[0][2][$g];
        throw new Exception("No such group '$g'");
      }
    }
    throw new Exception('match history empty');
  }
  
  /**
   * @brief Get the position (offset) of the most recent match
   * 
   * @return The position, as integer. This is a standard zero-indexed offset 
   * into the string. It is independent of the scan pointer.
   * 
   * @throw Exception if no matches have been recorded.
   */     
  function match_pos() {
    if (isset($this->match_history[0])) 
      return $this->match_history[0][1];
    
    throw new Exception('match history empty');
  }
  /**
   * @brief Helper function to log a match into the history
   * @internal
   */
  private function __log_match($index, $match_pos, $match_data) {
    if (isset($this->match_history[0])) {
      $this->match_history[1] = $this->match_history[0];
    }
    $this->match_history[0][0] = $index;
    $this->match_history[0][1] = $match_pos;
    $this->match_history[0][2] = $match_data;   
  }
  
  /**
   * 
   * @brief Revert the most recent scanning operation.
   * 
   * Unscans the most recent match. The match is removed from the history, and
   * the scan pointer is moved to where it was before the match. 
   * 
   * Calls to get(), and peek() are not logged and are therefore not 
   * unscannable.
   *
   * @warning Do not call unscan more than once before calling a scanning 
   * function. This is not currently defined.
   */
  function unscan() {
    if (isset($this->match_history[0])) {
      $this->index = $this->match_history[0][0];
      if (isset($this->match_history[1])) {
        $this->match_history[0] = $this->match_history[1];
        $this->match_history[1] = null;
      } else 
        $this->match_history[0] = null;
    }
    else
      throw new Exception('match history empty');
  }
  
  /**
   * @brief Helper function to consume a match
   * @param $pos (int) The match position
   * @param $consume_match (bool) Whether or not to consume the actual matched
   * text
   * @param $match_data The matching groups, as returned by PCRE.
   * @internal
   */
  private function __consume($pos, $consume_match, $match_data) {
    $this->index = $pos;
    if ($consume_match) $this->index += strlen($match_data[0]);
  }
  
  /**
   * @brief The real scanning function
   * @internal
   * @param $pattern The pattern to scan for
   * @param $instant Whether or not the only legal match is at the 
   * current scan pointer or whether one beyond the scan pointer is also
   * legal.
   * @param $consume Whether or not to consume string as a result of matching
   * @param $consume_match Whether or not to consume the actual matched string.
   * This only has effect if $consume is @c TRUE. If $instant is @c TRUE, 
   * $consume is true and $consume_match is @c FALSE, the intermediate 
   * substring is consumed and the scan pointer moved to the beginning of the 
   * match, and the substring is recorded as a single-group match.
   * @param $log whether or not to log the matches into the match_register
   * @return The matched string or null. This is subsequently
   * equivalent to match() or match_groups()[0] or match_group(0).
   */
  private function __check($pattern, $instant=true, $consume=true, 
    $consume_match=true, $log=true) {
      $matches = null;
      $index = $this->index;
      $pos = null;
      if (($pos = $this->ss->match($pattern, $this->index, $matches)) !== false) {
        if ($instant && $pos !== $index) {
          $matches = null;
        }
        // don't consume match and not instant: the match we are interested in
        // is actually the substring between the start and the match.
        // this is used by scan_to
        if (!$consume_match && !$instant) {
          $matches = array(substr($this->src, $this->index, $pos-$this->index));
        }
      }
      else $matches = null;

      if ($log) {
        $this->__log_match($index, $pos, $matches);
      }      
      if ($matches !== null && $consume) {
        $this->__consume($pos, $consume_match, $matches);
      }      
      return ($matches === null)? null : $matches[0]; 
  }
  
  /**
   * @brief Scans at the current pointer
   * Looks for the given pattern at the current index and consumes and logs it
   * if it is found.
   * @param $pattern the pattern to search for
   * @return @c null if not found, else the full match.
   */
  function scan($pattern) {
    return $this->__check($pattern);
  }

  /**
  * @brief Scans until the start of a pattern
  * 
  * Looks for the given pattern anywhere beyond the current index and 
  * advances the scan pointer to the start of the pattern. The match is logged.
  * 
  * The match itself is not consumed.
  * 
  * @param $pattern the pattern to search for
  * @return The substring between here and the given pattern, or @c null if it 
  * is not found.
  */
  function scan_until($pattern) {
    return $this->__check($pattern, false, true, false, true);
  }

  
  /**
   * @brief Non-consuming lookahead
   * Looks for the given pattern at the current index and logs it
   * if it is found, but does not consume it. This is a look-ahead.
   * @param $pattern the pattern to search for
   * @return @c null if not found, else the matched string.
   */  
  function check($pattern) {
    return $this->__check($pattern, true, false, false, true);
  }
  
  /**
   * @brief Find the index of the next occurrence of a pattern
   * @param $pattern the pattern to search for
   * @return The next index of the pattern, or -1 if it is not found
   */
  function index($pattern) {
    return $this->ss->match($pattern, $this->index, $dontcare_ref);
  }


  /**
   * @brief Look for the next occurrence of a set of patterns
   * Finds the next match of the given patterns and returns it. The
   * string is not consumed or logged.
   * Convenience function.
   * @param $patterns an array of regular expressions
   * @return an array of (0=>index, 1=>match_groups). The index may be -1 if
   * no pattern is found.
   */
  function get_next($patterns) {
    $next = -1;
    $matches = null;
    foreach($patterns as $p) {
      $m;
      $index = $this->ss->match($p, $this->index, $m);
      if ($index === false) continue;
      if ($next === -1 || $index < $next) {
        $next = $index;
        $matches = $m;
        assert($m !== null);
      }
    }
    return array($next, $matches);
  }

  /**
   * @brief Look for the next occurrence of a set of substrings
   * Like get_next() but uses strpos instead of preg_*
   * @see get_next()
   */
  function get_next_strpos($patterns) {
    $next = -1;
    $match = null;
    foreach($patterns as $p) {
      $index = strpos($this->src, $p, $this->index);
      if ($index === false) continue;      
      if ($next === -1 || $index < $next) {
        $next = $index;
        $match = $p;
      }
    }
    return array($next, $match);
  }


  /**
   * Adds a predefined pattern which is visible to next_match.
   * @param $name A name for the pattern. This does not have to be unique.
   * @param $pattern A regular expression pattern.
   */  
  function add_pattern($name, $pattern) {
    $this->patterns[] = array($name, $pattern . 'S', -1, null);
  }
  
  /**
   * Iterates over the predefiend patterns array (add_pattern) and consumes/logs
   * the nearest match, skipping unrecognised segments of string.
   * @return an array:
   *    0 => pattern name  (as given to add_pattern)
   *    1 => match index (although the scan pointer will have progressed to the 
   *            end of the match if the pattern is consumed)
   * 
   * @param $consume_and_log If this is @c FALSE, the pattern is not consumed 
   * or logged. 
   */
  function next_match($consume_and_log=true) {
    $target = $this->index;
    
    $nearest_index = -1;
    $nearest_key = -1;
    $nearest_name = null;
    $nearest_match_data = null;

    foreach($this->patterns as &$p_data) {
      $name = $p_data[0];
      $pattern = $p_data[1];
      $index = &$p_data[2];
      $match_data = &$p_data[3];

      if ($index !== false && $index < $target) {
        $index = $this->ss->match($pattern, $target, $match_data);
      }

      if ($index === false) {
        unset($p_data);
        continue;
      }

      if ($nearest_index === -1 || $index < $nearest_index) {
        $nearest_index = $index;
        $nearest_name = $name;
        $nearest_match_data = $match_data;
        if ($index === $target) break;
      }
    }
    
    if ($nearest_index !== -1) {
      if ($consume_and_log) {
        $this->__log_match($nearest_index, $nearest_index, $nearest_match_data);
        $this->__consume($nearest_index, true, $nearest_match_data);
      }
      return array($nearest_name, $nearest_index);
    }
    else return null;
  }
}






/**
 * @brief the base class for all scanners
 * 
 * A note on tokens: Tokens are stored as an array with the following indices:
 *      0:   Token name   (e.g. 'COMMENT'
 *      1:   Token string (e.g. '// foo')
 *      2:   escaped?      Because it's often more convenient to embed nested
 *              tokens by tagging token string, we need to escape it. This 
 *              index stores whether or nto it has been escaped.
 */

class LuminousScanner extends Scanner {

  /// scanner version. 
  public $version = 'master';

  /// A map of recognised identifiers, in the form
  /// identifier_string => TOKEN_NAME
  protected $ident_map = array();

  /// The token stream, as it is recorded
  protected $tokens = array();

  /// A stack of the scanner's state, should the scanner wish to use
  /// stack-based state
  protected $state_ = array();

  /// Individual filters, as a list of lists:
  /// (name, token_name, callback)
  protected $filters = array();
  
  /// Stream filters as a list of lists:
  /// (name, callback)
  protected $stream_filters = array();

  /// A map to handle re-mapping of rules, in the form:
  /// OLD_TOKEN_NAME => NEW_TOKEN_NAME
  protected $rule_tag_map = array();

  /// A map of remappings of user-defined types/functions. This is a map of
  /// identifier_string => TOKEN_NAME
  protected $user_defs;

  /// Whether or not the scanner is dealing with a case sensitive language.
  protected $case_sensitive = true;
  
  
  function __construct($src=null) {
    parent::__construct($src);

    $this->add_filter('map-ident', 'IDENT', array($this,
      'map_identifier_filter'));
    
    $this->add_filter('comment-note', 'COMMENT', array('LuminousFilters', 'comment_note'));    
    $this->add_filter('comment-to-doc', 'COMMENT', array('LuminousFilters', 'generic_doc_comment'));
    $this->add_filter('string-escape', 'STRING', array('LuminousFilters', 'string'));
    $this->add_filter('pcre', 'REGEX', array('LuminousFilters', 'pcre'));
    $this->add_filter('user-defs', 'IDENT', array($this, 'user_def_filter'));

    $this->add_filter('constant', 'IDENT', array('LuminousFilters', 'upper_to_constant'));

    $this->add_filter('clean-ident', 'IDENT', array('LuminousFilters', 'clean_ident'));
    
    $this->add_stream_filter('rule-map', array($this, 'rule_mapper_filter'));
    $this->add_stream_filter('oo-syntax', array('LuminousFilters', 'oo_stream_filter'));
}

  /**
   * maps anything recorded in LuminousScanner::user_defs to the recorded type.
   * This is called as the filter 'user-defs'
   */
  protected function user_def_filter($token) {
    if (isset($this->user_defs[$token[1]])) {
      $token[0] = $this->user_defs[$token[1]];
    }
    return $token;
  }

  /**
   * Re-maps token rules according to the LuminousScanner::rule_tag_map
   * map.
   * This is called as the filter 'rule-map'
   */
  protected function rule_mapper_filter($tokens) {
    foreach($tokens as &$t) {
      if (array_key_exists($t[0], $this->rule_tag_map))
        $t[0] = $this->rule_tag_map[$t[0]];
    }
    return $tokens;
  }




  /**
   * Alias for:
   *   $s->string($src)
   *   $s->init();
   *   $s->main();
   *   return $s->tagged();
   */
  function highlight($src) {
    $this->string($src);
    $this->init();
    $this->main();
    return $this->tagged();
  }

  /**
   * The init method is always called prior to highlighting. At this stage, all
   * configuration variables are assumed to have been set, and it's now time
   * for the scanner tod o any last setup information. This may include
   * actually finalizing its rule patterns.
   */
  function init() {}
  
  /**
   * Adds an indivdual filter. The filter is bound to the given token_name
   *
   * The filter is a callback which should take a token and return a token.
   * 
   * args are;  ([name], token_name, filter)
   * poor man's method overloading.
   */
  public function add_filter($arg1, $arg2, $arg3=null) {
    $filter = null;
    $name = null;
    $token = null;
    if ($arg3 === null) {
      $filter = $arg2; 
      $token = $arg1;
    } else {
      $filter = $arg3;
      $token = $arg2;
      $name = $arg1;
    }
    if (!isset($this->filters[$token])) $this->filters[$token] = array();
    $this->filters[$token][] = array($name, $filter);
  }

  /**
   * Removes the individual filter(s) with the given name
   */
  public function remove_filter($name) {
    foreach($this->filters as $token=>$filters) {
      foreach($filters as $k=>$f) {
        if ($f[0] === $name) unset($this->filters[$token][$k]);
      }
    }
  }

  /**
   * Removes the stream filter(s) with the given name
   */
  public function remove_stream_filter($name) {
    foreach($this->stream_filters as $k=>$f) {
      if ($f[0] === $name) unset($this->stream_filters[$k]);
    }
  }

  /**
   * Adds a stream filter. A stream filter receives the entire token stream and
   * should return it.
   *
   * Args are: [name], callback.
   */
  public function add_stream_filter($arg1, $arg2=null) {
    $filter = null;
    $name = null;
    if ($arg2 === null) {
      $filter = $arg1; 
    } else {
      $filter = $arg2;
      $name = $arg1;
    }
    $this->stream_filters[] = array($name, $filter);
  }

  
  function state() {
    if (!isset($this->state_[0])) return null;
    return $this->state_[count($this->state_)-1];
  }
  
  function start() {
    $this->tokens = array();
  }
  
  function record($string, $type, $pre_escaped=false) {
    if ($string === null) throw new Exception('Tagging null string');
    $this->tokens[] = array($type, $string, $pre_escaped);
  }
  
  function tagged() {
    $out = '';

    // call stream filters.
    foreach($this->stream_filters as $f) {
      $this->tokens = call_user_func($f[1], $this->tokens);
    }
    foreach($this->tokens as $t) {
      $type = $t[0];
      
      // speed is roughly 10% faster if we process the filters inside this
      // loop instead of separately.
      if (isset($this->filters[$type])) {
        foreach($this->filters[$type] as $filter) {
          $t = call_user_func($filter[1], $t);
        }
      }
      list($type, $string, $esc) = $t;

      if (!$esc) $string = LuminousUtils::escape_string($string);
      if ($type !== null) 
        $out .= LuminousUtils::tag_block($type, $string);
      else $out .= $string;
    }
    return $out;
  }

  /// returns the token array
  function token_array() {
    return $this->tokens;
  }

  /**
   * Tries to maps any 'IDENT' token to a TOKEN_NAME in
   * LuminousScanner::$ident_map
   * This is implemented as the filter 'map-ident'
   */
  function map_identifier_filter($token) {
    $ident = $token[1];
    if (!$this->case_sensitive) $ident = strtolower($ident);
    foreach($this->ident_map as $n=>$hits) {
      if (isset($hits[$ident])) {
        $token[0] = $n;
        break;
      }
    }
    return $token;
  }

  /**
   * Adds an identifier mapping which is later analysed by
   * map_identifier_filter
   * @param $name The token name
   * @param $matches an array of identifiers which correspond to this token
   * name, i.e. add_identifier_mapping('KEYWORD', array('if', 'else', ...));
   *
   * This method observes LuminousScanner::$case_sensitive
   */
  function add_identifier_mapping($name, $matches) {
    $array = array();
    foreach($matches as $m) {
      if (!$this->case_sensitive) $m = strtolower($m);
      $array[$m] = true;
    }
    if (!isset($this->ident_map[$name]))
      $this->ident_map[$name] = array();
    $this->ident_map[$name] = array_merge($this->ident_map[$name], $array);
  }

  /**
   * Convenience function:
   * Skips whitespace, and records it as a null token.
   */
  function skip_whitespace() {
    if (ctype_space($this->peek())) {
      $this->record($this->scan('/\s+/'), null);
    }    
  }
}





/**
 * @brief Superclass for languages which may nest, i.e. web languages
 * 
 * Web languages get their own special class because they have to deal with
 * server-script code embedded inside them and the potential for languages
 * nested under them (PHP has HTML, HTML has CSS and JavaScript)
 * 
 * The relationship is strictly hierarchical, not recursive descent
 * Meeting a '<?' in CSS bubbles up to HTML and then up to PHP (or whatever)
 * 
 * The scanners should be persistent, so only one JavaScript scanner exists
 * even if there are 20 javascript tags. This is so they can keep persistent 
 * state, which might be necessary if they are interrupted by server-side. In
 * the case that they are interrupted in the middle of a rule which has to be 
 * resumed when the scanner is next called, it is said to be a 'dirty exit'.
 * 
 * The init method of the class should be used to set relevant rules based
 * on whether or not the embedded flags are set; and therefore the embedded
 * flags should be set before init is called.
 */
abstract class LuminousEmbeddedWebScript extends LuminousScanner {
  
  /// Embedded in HTML? i.e. does it need to observe tag terminators
  public $embedded_html = false;
  
  /// Embedded in a server side language? i.e. does it need break at
  /// server language tags
  public $embedded_server = false;
  
  /// Opening tag for server-side code, which the scanner has to break at
  public $server_tags = '<?';
  /// Closing tag for script code, which a script scanner has to break at
  public $script_tags;
  
  
  /** specifies whether or not we reached an interrupt by a server-side 
    * script block */    
  public $interrupt = false;
  
  /** 
   * Signifies whether the program exited due to inconvenient interruption by 
   * a parent language (i.e. a server-side langauge), or whether it reached 
   * a legitimate break. A server-side language isn't necessarily a dirty exit,
   * but if it comes in the middle of a token it is, because we need to resume
   * from it later. e.g.:
   *
   * var x = "this is <?php echo 'a' ?> string";
   */
  public $clean_exit = true;
  
  
  
  /// Map of child scanners, name => scanner (instance)
  protected $child_scanners = array();

  /**
   * exit state logs our exit state in the case of a dirty exit: this is the
   * rule that was interrupted.
   */
  protected $exit_state;
  
  
  /** If we reach a dirty exit, when we resume we need to figure out how to 
   * continue consuming the rule that was interrupted. So essentially, this 
   * will be a regex which matches the rule without start delimiters.
   *  
   * This is a map of rule => pattern
   */
  protected $dirty_exit_recovery = array();

  /// Adds a child scanner, convenience function
  public function add_child_scanner($name, $scanner) {
    $this->child_scanners[$name] = $scanner;
  }
  
  // override string to hit the child scanners as well
  public function string($str=null) {
    if ($str !== null) {
      foreach($this->child_scanners as $s) {
        $s->string($str);
      }
    }
    return parent::string($str);
  }
  
  /**
   * Sets the exit data to signify it was a dirty exit
   */
  function dirty_exit($state) {
    // if we don't know how to recover from it, there's no point tagging 
    // this as a dirty exit.
    // XXX is this okay?
    if (!isset($this->dirty_exit_recovery[$state])) {
      $this->clean_exit = true;
      return;
    }
    $this->exit_state = $state;
    $this->interrupt = true;
    $this->clean_exit = false;
  }
  
  /**
   * Attempts to resume from a dirty exit 
   * Consumes the remaining segment of string for the rule that was exited on
   * and returns the rule name. The match will be in $this->match(). 
   * Returns null if no recovery is known, but this is an implementation error
   * so an assertion is also failed.
   */
  function resume() {
    assert (!$this->clean_exit);
    $this->clean_exit = true;
    $this->interrupt = false;
    if (!isset($this->dirty_exit_recovery[$this->exit_state])) {
      echo "No such state exit data: {$this->exit_state}";
      assert(0);
      return null;
    }
    $pattern = $this->dirty_exit_recovery[$this->exit_state];
    assert($this->scan($pattern) !== null);
    return $this->exit_state;
  }
  
  
  /**
   * Breaks current scanning due to server-side language interruption, 
   * which it is expected will be recovered from
   */  
  function server_break($state, $match=null, $offset=null) {
    if (!$this->embedded_server) {
      return false;
    }
    if ($match === null) $match = $this->match();
    if (($pos = stripos($match, $this->server_tags)) !== false) {
      $this->record(substr($match, 0, $pos), $state);
      if ($offset === null) $offset = $this->match_pos();
      $this->pos($offset + $pos);
      $this->dirty_exit($state);
      return true;
    }
    else return false;
  }
  
  /**
   * Breaks current scanning due to a terminator tag, i.e. a real exit.
   */
  function script_break($state, $match=null, $offset=null) {
    if (!$this->embedded_html) return false;
    if ($match === null) $match = $this->match();
    if (($pos = stripos($this->match(), $this->script_tags)) !== false) {
      $this->record(substr($match, 0, $pos), $state);
      if ($offset === null) $offset = $this->match_pos();
      $this->pos($offset + $pos);
      $this->clean_exit = true;
      
      return true;
    }
    else return false;
  }
  
}


/**
 * @brief A largely automated scanner
 *
 * LuminousSimpleScanner implements a main() method and observes the
 * patterns added with Scanner::add_pattern()
 *
 * An overrides array allows the caller to override the handling of any token.
 * If an override is set for a token, the override is called when that token is
 * reached and the caller should consume it. If the callback fails to advance
 * the string pointer, an Exception is thrown.
 */
class LuminousSimpleScanner extends LuminousScanner {

  /// A map of TOKEN_NAME => callback
  protected $overrides = array();
  
  function main() {
    while (!$this->eos()) {
      $index = $this->pos();
      if (($match = $this->next_match()) !== null) {
        $tok = $match[0];
        if ($match[1] > $index) {
          $this->record(substr($this->string(), $index, $match[1] - $index), null);
        }
        $match = $this->match();
        if (isset($this->overrides[$tok])) {
          $groups = $this->match_groups();
          $this->unscan();
          $p = $this->pos();
          call_user_func($this->overrides[$tok], $groups);
          if ($this->pos() <= $p)
            throw new Exception('Failed to consume any string in override for ' . $tok);
        } else
          $this->record($match, $tok);
      } else {
        $this->record(substr($this->string(), $index), null);
        break;
      }
    }
  }
}

/// @endcond