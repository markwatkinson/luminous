<?php


class JSScanner extends LuminousEmbeddedWebScript {
  
  // TODO clean up redunancy here and in constructor
  public $server_tags = '<?';
  public $script_tags = '</script>';
    
  // logs a persistent token stream so that we can lookbehind to figure out
  // operators vs regexes.
  private $tokens_ = array(); 
  
  function __construct($src=null) {
    
    
    
    $this->rule_tag_map = array(
      'COMMENT_SL' => 'COMMENT',
      'DOCCOMMENT_SL' => 'DOCCOMMENT',
      'SSTRING' => 'STRING',
      'DSTRING' => 'STRING'
    );
    $this->dirty_exit_recovery = array(
      'COMMENT_SL' => '/.*/',
      'DOCCOMMENT_SL' => '/.*/',
      'COMMENT' => '%.*\*/%s',
      'DOCCOMMENT' => '%.*\*/%s',
      'SSTRING' => "/(?:[^\\\\']+|\\\\.)*'/",
      'DSTRING' => '/(?:[^\\\\"]+|\\\\.)*"/'
    );
    
    parent::__construct($src);
    $this->add_identifier_mapping('KEYWORD', array('break', 'case', 'catch', 
      'comment', 'continue', 'do', 'default', 'delete', 'else', 'export', 
      'for', 'function', 'if', 'import', 'in', 'instanceof', 'label', 'new', 
      'null', 'return', 'switch', 'throw', 'try', 'typeof', 'var', 'void', 
      'while', 'with',
      'true', 'false', 'this'
      ));
    $this->add_identifier_mapping('FUNCTION', array('$', 'alert', 'confirm', 
      'clearTimeout', 'clearInterval',
      'encodeURI', 'encodeURIComponent', 'eval', 'isFinite', 'isNaN', 
      'parseInt', 'parseFloat', 'prompt',
      'setTimeout', 'setInterval',      
      'decodeURI', 'decodeURIComponent', 'jQuery'));
      
    $this->add_identifier_mapping('TYPE', array('Array', 'Boolean', 'Date', 
      'Error', 'EvalError', 'Infinity', 'Image', 'Math', 'NaN', 'Number', 
      'Object', 'Option', 'RangeError', 'ReferenceError', 'RegExp', 'String',
      'SyntaxError', 'TypeError', 'URIError', 
      
      'document',
      'undefined', 'window'));

    $this->add_pattern('IDENT', '/(\\.?)(?>[a-zA-Z_$]+[_$\w]*)(?>\\.[a-zA-Z_$]+[_$\w]*)*/'); 
    // NOTE: slash is a special case, and </ may be a script close
    $this->add_pattern('OPERATOR', '@[=!+*%\-&^|~:?\.>]+|<(?![/?])@');
    // we care about openers for figuring out where regular expressions are
    $this->add_pattern('OPENER', '/[\[\{\(]+/');
    $this->add_pattern('NUMERIC', '/0x[a-f0-9]+/');
    $this->add_pattern('NUMERIC', '/(?>\.?\d+|\d+\.?)(?:e[+-]?\d+)?/i');
    $this->add_pattern('SSTRING', "/' (?: [^'\\\\]+ | \\\\.)* (?:'|$)/xs");
    $this->add_pattern('DSTRING', '/" (?: [^"\\\\]+ | \\\\.)* (?:"|$)/xs');
    $this->add_pattern('DOCCOMMENT', '% /\*[*!] .*? \*/ %sx');
    $this->add_pattern('DOCCOMMENT_SL', '%//[/!].*%');
    $this->add_pattern('COMMENT', '% /\*(?!\*!) .*? \*/ %sx');
    $this->add_pattern('COMMENT_SL', '%//(?!/!).*%');
    $this->add_pattern('SLASH', '%/%');
  }
  
  function init() {
    $stop_patterns = array();
    if ($this->embedded_server) $stop_patterns[] = "(?P<SERVER><\\?)";
    if ($this->embedded_html) $stop_patterns[] = "(?P<SCRIPT_TERM></script>)";
    if (!empty($stop_patterns)) {
      $this->stop_pattern = '%' . join('|', $stop_patterns) . '%i';
      $this->add_pattern('STOP', $this->stop_pattern);
    }
  }
  
  function main() {
    $this->start();
    
    while (!$this->eos()) {
      $index = $this->pos();
      $tok = null;
      $m = null;
      $escaped = false;
      
      if (!$this->clean_exit) {
        $tok = $this->resume();
      }
      elseif (($rule = $this->next_match()) !== null) {
        $tok = $rule[0];
        if ($rule[1] > $index) {
          $this->record(substr($this->string(), $index, $rule[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        $this->clean_exit = true;
        break;
      }
      
      if ($tok === 'IDENT') {
        // figure out what the identifier is and if it involves OO syntax
        // this is a fair bit faster than matching all identifiers 
        // individually.
        $m_ = $this->match();
        $s = explode('.', $m_);
        $limit = count($s)-1;

        foreach($s as $i=>$segment) {
          if ($segment === '') {
            $this->record('.', null);
            continue;
          }
          $t = $this->map_identifier($segment);            
          if ($i === $limit) {
            if ($t === 'IDENT' && $i) $t = 'OO';
          } else {
            if ($t === 'IDENT') $t = 'OBJ';
          }
          $this->record($segment, ($t==='IDENT')? null : $t);
          if ($i !== $limit) 
            $this->record('.', null);
        }
        $this->tokens_ [] = 'IDENT';
        continue;
      }
      elseif ($tok === 'SLASH') {
        $this->pos($this->pos()-1);
        assert ($this->peek() === '/');
        $i = count($this->tokens_);
        $tok = 'REGEX'; 
        while ($i) {
          $t = $this->tokens_[--$i];
          if ($t === 'COMMENT') continue;
          elseif ($t === 'OPENER' || $t === 'OPERATOR') {
            break;
          } else {
            $tok = 'OPERATOR';
            $m = $this->get();
          }
          break;
        }
        // do this outside the while loop so we're sure it executes 
        // (in the case of 0 length token array)
        if ($tok === 'REGEX') {
          $m = $this->scan('% / (?: [^/\\\\]+ | \\\\.)* (?:/[ioxgm]*|$)%sx');
          assert ($m !== null) or die();
        }
      }
      elseif ($tok === 'STOP') {
        if ($this->match() === '<?') {
          dirty_exit(null);
        }
        $this->unscan();
        break;
      }
      if ($m === null) $m = $this->match();
      
      if ($this->server_break($tok)) break;
      
      if (($tok === 'COMMENT_SL' || $tok === 'DOCCOMMENT_SL')
        && $this->script_break($tok)
      ) 
        break;
      
      assert($this->pos() > $index) or die("$tok didn't consume anything");
      $this->tokens_[] = $tok;
      
      $tag = $tok;
      if ($tok === 'OPENER')
        $tag = null;
      $this->record($m, $tag, $escaped);

    }
  }
}
