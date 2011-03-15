<?php
// require_once(dirname(__FILE__) . '/luminous-r657/src/core/utils.php');

require_once(dirname(__FILE__) .  '/luminous-r657/src/core/strsearch.class.php');

// require_once(dirname(__FILE__) .  '/luminous-r657/src/luminous_grammar_callbacks.php');


require_once('utils.class.php');
require_once('filters.class.php');


/**
 * The Scanner class is a base class which handles traversing a string while
 * searching for various different tokens.
 * It is loosely based on Ruby's StringScanner.
 */
class Scanner {
  /// Input string
  private $src;
  
  /// length of input string (cached for performance)
  private $src_len;
  
  /// Current index
  private $index;
  
  /** History of matches. This is an array (queue), which should have at most 
   * two elements. Each element consists of an array: 
   *  0 => String index,
   *  1 => match index,
   *  2 => match data (groups as hash)
   * 
   * Numerical indices are used for performance.
   */
  private $match_history = array();
  
  /// LuminousStringSearch instance (caches preg_* results)
  private $ss;
  
  /// Preset patterns, used by next_match()
  private $patterns = array();

  
  function __construct($src=null) {
    $this->string($src);
  }
    
  /**
   * \return the rest of the string which has not yet been consumed
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
   * Optionally sets and returns the current string position (index)
   */
  function pos($new_pos=null) {
    if ($new_pos !== null) {
      $new_pos = max(min($new_pos, $this->src_len), 0);
      $this->index = $new_pos;
    }
    return $this->index;
  }
  
  /**
   * Returns true if Scanner has reached the end of the string, else false
   */
  function eos() {
    return $this->index >= $this->src_len;
  }
  
  /**
   * Resets Scanner: sets pos to 0 and clears the match history
   */
  function reset() {
    $this->pos(0);
    $this->match_history = array();    
    $this->ss = new LuminousStringSearch($this->src);
  }
  
  /**
   * Optionally sets and returns the current string being scanned
   * If a string is passed, it is set as the current string. Its line endings
   * will be converted to Unix form (\\n, LF)
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
   * Moves the scan pointer to the end of the string, terminating the 
   * current scan.
   */
  function terminate() {
    $this->reset();
    $this->pos($this->src_len);
  }
  
  /**
   * Returns the given number of characters from the string from the 
   * current scan pointer onwards, and does not consume them.
   * 
   * Note neither get nor peek logs its matches into the match history.

   */
  function peek($n=1) {
    if ($n === 0 || $this->eos()) return '';
    elseif ($n === 1) return $this->src[$this->index];    
    else return substr($this->src, $this->index, $n);
  }
  
  /**
   * Returns the given number of characters from the string from the 
   * current scan pointer onwards, and consumes them.
   * 
   * Note neither get nor peek logs its matches into the match history.
   */  
  function get($n=1) {
    $p = $this->peek($n);
    $this->index += strlen($p);
    return $p;
  }
  
  /**
   * Returns the most recent matched string, or throws an exception if 
   * no matches have been recorded.
   */
  function match() {
    if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2][0];
  }
  
  /**
   * Returns the most recent match groups as an associative array of 
   * group => string. This is in the same format as returned by preg_match. 
   * 
   * Throws Exception if no matches have been recorded.
   */  
  function match_groups() {
     if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2];
  }  
  
  /**
   * Returns the given group from the most recent match (as string).
   * The group may be either an integer or a string in the case of named 
   * subpatterns
   * 
   * Throws Exception if no matches have been recorded.
   */   
  function match_group($g=0) {
     if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2][$g];
  }
  
  /**
   * Returns the position (offset) of the most recent match
   * 
   * Throws Exception if no matches have been recorded.
   */     
  function match_pos() {
    if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][1];
  }  
  
  private function __log_match($index, $match_pos, $match_data) {
    if (isset($this->match_history[1])) array_shift($this->match_history);
    $this->match_history[] = array(
      $index,
      $match_pos,
      $match_data
    );
  }
  
  /**
   * Unscans the most recent match. The match is removed from the history, and
   * the scan pointer is moved to where it was before the match.
   * 
   * Calls to get(), and peek() are not logged and are therefore not 
   * unscannable.
   */
  function unscan() {
    if (empty($this->match_history))
      throw new Exception('match history empty');
    $data = array_pop($this->match_history);
    $this->index = $data[0];
  }
  
  private function __consume($pos, $consume_match, $match_data) {
    $this->index = $pos;
    if ($consume_match) $this->index += strlen($match_data[0]);
  }
  
  private function __check($pattern, $instant=true, $consume=true, 
    $consume_match=true, $log=true) {
      $matches = null;
      $index = $this->index;
      $pos = null;
      if (($pos = $this->ss->PregSearch($pattern, $this->index, $x, $matches)) !== false) {
        if ($instant && $pos !== $index) {
          $matches = null;
        }        
      }
      else $matches = null;

      if ($log) {
        $this->__log_match($index, $pos, $matches);
      }      
      if ($matches !== null && $consume) {
        $this->__consume($pos, $consume_match, $matches);
      }      
      return ($matches === null)? $matches : $matches[0]; 
  }
  
  /**
   * Looks for the given pattern at the current index and consumes and logs it
   * if it is found.
   * Returns null if not found, else the full match.
   */
  function scan($pattern) {
    return $this->__check($pattern);
  }
  /**
   * Looks for the given pattern at the current index and logs it
   * if it is found, but does not consume it. This is a look-ahead.
   * Returns null if not found, else the full match.
   */  
  function check($pattern) {
    return $this->__check($pattern, true, false, false, true);
  }
  
  /**
   * Looks for the given pattern at the current index and consumes it if it 
   * is found, but does not log it (skips over it).
   * Returns the number of charaters consumed.
   */    
  function skip($pattern) {
    $p = $this->index;
    $this->__check($pattern, true, true, true, false);
    return $this->index - $p;
  }
  
#  /**
#   * Looks for the given pattern anywhere in the string after the current scan
#   * pointer, and consumes it and logs it.
#   * Returns null if the match fails, else the text of the match.
#   * 
#   * TODO this should probably log everything, not just the match.
#   */
#  function scan_to($pattern) {
#    return $this->__check($pattern, false, true, true, true);
#  }


  /**
   * Adds a predefined pattern which is visible to next_match.
   */  
  function add_pattern($name, $pattern) {
    $this->patterns[] = array($name, $pattern, -1, null);
  }
  
  /**
   * Iterates over the predefiend patterns array (add_pattern) and consumes/logs
   * the nearest match, skipping unrecognised segments of string.
   * returns an array: 
   *    0 => pattern name  (as given to add_pattern)
   *    1 => match index (although the scan pointer will have progressed to the 
   *            end of the match if the pattern is consumed)
   * 
   * if $consume_and_log is false, the pattern is not consumed or logged. 
   */
  function next_match($consume_and_log=true) {
    $target = $this->pos();
    $dontcare_ref = false;
    
    $nearest_index = -1;
    $nearest_key = -1;
    $nearest_name = null;
    $nearest_match_data = null;

    foreach($this->patterns as $k=>$p_data) {
      $p_data = $this->patterns[$k];
      $name = $p_data[0];
      $pattern = $p_data[1];
      $index = $p_data[2];
      $match_data = $p_data[3];
      
      if ($index !== false && $index < $target) {
        $index = $this->ss->PregSearch($pattern, $target, $dontcare_ref, $match_data);
        $this->patterns[$k][2] = $index;
        $this->patterns[$k][3] = $match_data;
      }
      
      if ($index === false) {
        unset($this->patterns[$k]);
        continue;
      }
      
      if ($nearest_index === -1 || $index < $nearest_index) {
        $nearest_index = $index;
        $nearest_key = $k;
        $nearest_name = $name;
        $nearest_match_data = $match_data;
        if ($index === $target) break;
      }
    }
    
    if ($nearest_index !== -1) {
      $nearest = $this->patterns[$nearest_key];
      if ($consume_and_log) {
        $this->__log_match($nearest_index, $nearest_index, $nearest_match_data);
        $this->__consume($nearest_index, true, $nearest_match_data);        
      }
      return array($nearest_name, $nearest_index);
    }
    else return null;
  }  
}



abstract class LuminousTokenPresets {
  static $DOUBLE_STR = '/" (?: [^"\\\\]+ | \\\\.)* (?:"|$)/xs';
  static $SINGLE_STR = "/' (?: [^'\\\\]+ | \\\\.)* (?:'|$)/xs";
  static $NUM_HEX = '/0x[a-fA-F0-9]+/';
  static $NUM_REAL = '/
    (?: \d+ (?: \.\d+ )? | \.?\d+)     # int, fraction or significand 
    (?:e[+-]?\d+)?                     # exponent
    /ix';
  static $C_COMMENT_SL = '% // .* %x';
  static $C_COMMENT_ML = '% / \* .*? (?: \*/ | $) %sx';  
}









/**
 * A note on tokens: Tokens are stored as an array with the following indices:
 *      0:   Token name   (e.g. 'COMMENT'
 *      1:   Token string (e.g. '// foo')
 *      2:   escaped?      Because it's often more convenient to embed nested
 *              tokens by tagging token string, we need to escape it. This 
 *              index stores whether or nto it has been escaped.
 */

class LuminousScanner extends Scanner {
  protected $ident_map = array();
  protected $tokens = array();
  protected $out = '';
  
  protected $state_ = array();
  protected $stop_at = array();
  
  protected $filters = array();
  protected $stream_filters = array();
  
  protected $rule_tag_map = array();
  
  function __construct($src=null) {
    parent::__construct($src);
    
    $this->add_filter('ident', 'IDENT', create_function('$tok', '$tok[0] = null; return $tok;'));
    $this->add_filter('comment-note', 'COMMENT', array('LuminousFilters', 'comment_note'));    
    $this->add_filter('comment-to-doc', 'COMMENT', array('LuminousFilters', 'generic_doc_comment'));
    $this->add_filter('string-escape', 'STRING', array('LuminousFilters', 'string'));
    $this->add_filter('pcre', 'REGEX', array('LuminousFilters', 'pcre'));
  }
  
  
  
  
  protected $case_sensitive = true;
  
  
  function init() {}
  
  /*
   * args are;  ([name], token, filter)
   * 
   * poor man's method overloading.
   * 
   * TODO: allow unbinding of the filter if a name is passed.
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
  
  
  // this isn't used
  function add_range_check($pattern, $callback) {
    $this->stop_at[] = array($pattern, $callback);
  }
  
  function state() {
    if (!isset($this->state_[0])) return null;
    return $this->state_[count($this->state_)-1];
  }
  
  static function escape_string($string) {
    return htmlspecialchars($string, ENT_NOQUOTES);
  }
  
  function fire($event, $data) {
    return $data;
  }
  
//   function tag($string, $type, $escaped=false) {
//     assert(0);
//     if ($string === null) return;
//     if (isset($this->rule_tag_map[$type])) $type = $this->rule_tag_map[$type];
//     if (!$escaped)
//       $string = self::escape_string($string);
//     
//     if ($type !== null) {
//       $open = '<' . $type . '>';
//       $close = '</' . $type . '>';
//       $this->out .= $open . str_replace("\n", $close . "\n" . $open, $string) . $close;
//     }
//     else $this->out .= $string;
//   }
  
  function start() {
    $this->tokens = array();
  }
  
  function record($string, $type) {    
    if (isset($this->rule_tag_map[$type])) $type = $this->rule_tag_map[$type];
    $this->tokens[] = array($type, $string, false);
  }
  
  
  // TODO safety check: this should only be called once. Probably.
  // TODO this would be faster (probably) if we could once-generate a compose 
  // function instead of the inner loop
  function process_filters() {
    
    foreach($this->stream_filters as $f) {
      $this->tokens = call_user_func($f[1], $this->tokens);
    }
    if (empty($this->filters))
      return;
    foreach($this->tokens as $k=>$t) {
      $tok = $t[0];
      if (isset($this->filters[$tok])) {
        foreach($this->filters[$tok] as $f) {
          $this->tokens[$k] = call_user_func($f[1], $t);
        }
      }
    }
  }
  
  
  
  function tagged() {
    $out = '';
    
    $this->process_filters();
    
    foreach($this->tokens as $t) {
      $t = LuminousUtils::escape_token($t);
      list($type, $string, ) = $t;
      if ($type !== null) {
        $open = '<' . $type . '>';
        $close = '</' . $type . '>';
        // should this be a stream filter which splits tokens?
        // probably not.
        $out .= $open . str_replace("\n", $close . "\n" . $open, $string) . $close;
      }
      else $out .= $string;
    }
    return $out;
  }
  
  function token_array() {
    return $this->tokens;
  }
  
  function map_identifier($ident) {
    if (!$this->case_sensitive) $ident = strtolower($ident);
    foreach($this->ident_map as $n=>$hits) {
      if (isset($hits[$ident])) return $n;
    }
    return 'IDENT';
  }
  
  function add_identifier_mapping($name, $matches) {
    $array = array();
    foreach($matches as $m) {
      if (!$this->case_sensitive) $m = strtolower($m);
      $array[$m] = true;
    }
    $this->ident_map[$name] = $array;
  }  
  
  function skip_whitespace() {
    if (ctype_space($this->peek())) {
      $this->record($this->scan('/\s+/'), null);
    }    
  }
  
  
// TODO
//   static function explode_array($sepchars, $str) {
//     $out = array();
//   }
  
  // TODO multiple sep chars
//   function handle_idents($oo_sep_char=false) {
//     $m_ = $this->match();
//     $s = explode($oo_sep_char, $m_);
//     $limit = count($s)-1;
//     foreach($s as $i=>$segment) {
//       if ($segment === '') {
//         $this->tag('.', null);
//         continue;
//       }
//           $t = $this->map_identifier($segment);            
//           if ($i === $limit) {
//             if ($t === 'IDENT' && $i) $t = 'OO';
//           } else {
//             if ($t === 'IDENT') $t = 'OBJ';
//           }
//           $this->tag($segment, ($t==='IDENT')? null : $t);
//           if ($i !== $limit) 
//             $this->tag('.', null);
//         }
//         $this->tokens [] = 'IDENT';
//         continue;    
//   }
}






/**
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
 */
abstract class LuminousEmbeddedWebScript extends LuminousScanner {
  
  /// Embedded in HTML? i.e. does it need to observe tag terminators
  public $embedded_html = false;
  
  /// Embedded in a server side language? I.e. does it need break at
  /// server language tags
  public $embedded_server = false;
  
  // don't think these are actually observed at the moment
  public $server_tags = '<?';
  public $script_tags;
  
  /** 
   * Signifies whether the program exited due to inconvenient interruption by 
   * a parent language (i.e. a server-side langauge), or whether it reached 
   * a legitimate break.
   */
  public $clean_exit = true;
  
  
  protected $child_scanners = array();
  
  protected $exit_state;
  
  
  /** If we reach a dirty exit, when we resume we need to figure out how to 
   * continue consuming the rule that was interrupted. So essentially, this 
   * will be a regex which matches the rule without start delimiters.
   *  
   * This is a map of rule => pattern
   */
  protected $dirty_exit_recovery = array();
  
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
    if (!isset($this->dirty_exit_recovery[$this->exit_state])) {
      $this->clean_exit = true;
      return;
    }
    $this->exit_state = $state;
    $this->clean_exit = false;
  }
  
  
  /**
   * Initialises the scanner ready to scan. This should involve setting up 
   * rules observing the $embedded* class members
   */
//   abstract function init();
  
  
  
  /**
   * Attempts to resume from a dirty exit 
   * Consumes the remaining segment of string for the rule that was exited on
   * and returns the rule name. The match will be in $this->match(). 
   * Returns null if no recovery is known.
   */
  function resume() {
    assert (!$this->clean_exit) or die();
    $this->clean_exit = true;
    if (!isset($this->dirty_exit_recovery[$this->exit_state])) {
      assert(0) or die("No such state exit data: {$this}");
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