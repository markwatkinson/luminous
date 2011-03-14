<?php
require_once(dirname(__FILE__) . '/luminous-r657/src/core/utils.php');

require_once(dirname(__FILE__) .  '/luminous-r657/src/core/strsearch.class.php');

require_once(dirname(__FILE__) .  '/luminous-r657/src/luminous_grammar_callbacks.php');



class Scanner {
  private $src;
  private $index;
  private $src_len;
  private $match_history = array();
  private $ss;
  
  private $patterns = array();

  function __construct($src=null) {
    $this->string($src);
  }
  
  function rest() {
    static $pos = -1;
    static $rest = null;
    if ($pos !== $this->index) {
      $pos = $this->index;
      $rest = substr($this->src, $pos);
    }
    return $rest;
  }
  
  function pos($new_pos=null) {
    if ($new_pos !== null) {
      $new_pos = max(min($new_pos, $this->src_len), 0);
      $this->index = $new_pos;
    }
    return $this->index;
  }
  
  function eos() {
    return $this->index >= $this->src_len;
  }
  
  function reset() {
    $this->pos(0);
    $this->match_history = array();    
    $this->ss = new LuminousStringSearch($this->src);
  }
  
  function string($s=null) {
    if ($s !== null) {
      $this->src = $s;
      $this->src_len = strlen($s);
      $this->reset();
    }
    return $this->src;
  }
  
  function terminate() {
    $this->reset();
    $this->pos($this->src_len);
  }
  
  function peek($n=1) {
    if ($n === 0 || $this->eos()) return '';
    elseif ($n === 1) return $this->src[$this->index];    
    else return substr($this->src, $this->index, $n);
  }
  
  function get($n=1) {
    $p = $this->peek($n);
    $this->index += strlen($p);
    return $p;
  }
  
  function match() {
    if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2][0];
  }
  
  function match_groups() {
     if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2];
  }  
  
  function match_group($g=0) {
     if (empty($this->match_history))
      throw new Exception('match history empty');
    return $this->match_history[ count($this->match_history) -1][2][$g];
  }
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
   * Unscans the most recent match. Calls to get(), and peek() are not logged
   * and are therefore not unscannable.
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
  
  function scan($pattern) {
    return $this->__check($pattern);
  }
  function check($pattern) {
    return $this->__check($pattern, true, false, false, true);
  }
  
  function skip($pattern) {
    $this->__check($pattern, true, true, true, false);
  }
  
  function scan_to($pattern) {
    return $this->__check($pattern, false, true, true, true);
  }
  
  function add_pattern($name, $pattern) {
    $this->patterns[] = array($name, $pattern, -1, null);
  }
  
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



class LuminousTokenPresets {
  static $DOUBLE_STR = '/" (?: [^"\\\\]+ | \\\\.)* (?:"|$)/xs';
  static $SINGLE_STR = "/' (?: [^'\\\\]+ | \\\\.)* (?:'|$)/xs";
  static $NUM_HEX = '/0x[a-fA-F0-9]+/';
  static $NUM_REAL = '/(?>\.?\d+|\d+\.?)(?:e[+-]?\d+)?/i';
  static $C_COMMENT_SL = '% // .* %sx';
  static $C_COMMENT_ML = '% /\* .*? (\*/|$) %sx';
}


class LuminousScanner extends Scanner {
  protected $ident_map = array();
  protected $tokens = array();
  protected $out = '';
  
  protected $state_ = array();
  protected $stop_at = array();
  
  protected $rule_tag_map = array();
  
  function __construct($src=null) {
    parent::__construct($src);
  }
  
  
  
  
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
  
  function tag($string, $type, $escaped=false) {
    if ($string === null) return;
    if (!$escaped)
      $string = self::escape_string($string);
    if ($type !== null) {
      if (isset($this->rule_tag_map[$type])) $type = $this->rule_tag_map[$type];
      $open = '<' . $type . '>';
      $close = '</' . $type . '>';
      $this->out .= $open . str_replace("\n", $close . "\n" . $open, $string) . $close;
    }
    else $this->out .= $string;
  }
  
  function map_identifier($ident) {
    foreach($this->ident_map as $n=>$hits) {
      if (isset($hits[$ident])) return $n;
    }
    return 'IDENT';
  }
  
  function add_identifier_mapping($name, $matches) {
    $array = array();
    foreach($matches as $m) {
      $array[$m] = true;
    }
    $this->ident_map[$name] = $array;
  }  
  
  function skip_whitespace() {
    if (ctype_space($this->peek())) {
      $this->tag($this->scan('/\s+/'), null);
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






/*
 * Web languages get their own special class because they have to deal with
 * server-script code embedded inside them.
 * 
 * The relationship is strictly hierarchical, not recursive descent
 * Meeting a '<?' in CSS bubbles up to HTML and then up to PHP (or whatever)
 */
abstract class LuminousEmbeddedWebScript extends LuminousScanner {
  
  public $embedded_html = true;
  public $embedded_server = true;
  public $server_tags = '<?';
  public $script_tags;
  
  public $clean_exit = true;
  
  public $child_scanners = array();
  
  protected $exit_data;  
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
  
  function dirty_exit($state) {
    $this->exit_state = $state;
    $this->clean_exit = false;
  }
  
  // resumes from a dirty exit, i.e. an interruption by server side script 
  // tags
  function resume() {
    assert (!$this->clean_exit) or die();
    $this->clean_exit = true;
    if (!isset($this->dirty_exit_recovery[$this->exit_state]))
      return;
    $pattern = $this->dirty_exit_recovery[$this->exit_state];
    assert($this->scan($pattern) !== null);
    return $this->exit_state;
  }
  
  function server_break($state, $match=null, $offset=null) {
    if (!$this->embedded_server) {
      return false;
    }
    if ($match === null) $match = $this->match();
    if (($pos = stripos($match, $this->server_tags)) !== false) {
      $this->tag(substr($match, 0, $pos), $state);
      if ($offset === null) $offset = $this->match_pos();
      $this->pos($offset + $pos);
      $this->dirty_exit($state);
      return true;
    }
    else return false;
  }
  
  function script_break($state, $match=null, $offset=null) {
    if (!$this->embedded_html) return false;
    if ($match === null) $match = $this->match();
    if (($pos = stripos($this->match(), $this->script_tags)) !== false) {
      $this->tag(substr($match, 0, $pos), $state);
      if ($offset === null) $offset = $this->match_pos();
      $this->pos($offset + $pos);
      $this->clean_exit = true;
      
      return true;
    }
    else return false;
  }
  
}