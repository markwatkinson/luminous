<?php



class HTMLScanner extends LuminousEmbeddedWebScript {
  
  private $child_state = null;
  
  function __construct($src=null) {
    $js = new JSScanner($src);
    $js->embedded_server = $this->embedded_server;
    $js->embedded_html = true;
    $js->init();
    
    $css = new CSSScanner($src);
    $css->embedded_server = $this->embedded_server;
    $css->embedded_html = true;
    $css->init();
    
    $this->add_child_scanner('js', $js);
    $this->add_child_scanner('css', $css);

    $this->dirty_exit_recoveries = array(      
      'DSTRING' => '/(?: [^\\\\">]+ | \\\\[^>])*("|$|(?=>))/',
      'SSTRING' => "/(?: [^\\\\'>]+ | \\\\[^>])*('|$|(?=>))/",
      'COMMENT1' => '/.*?(?:-->|$)/s',
      'COMMENT2' => '/.*?(?:>|$)/s',
      'CDATA' => '/.*?(?:\\]{2}>|$)/s'
    );
    
    $this->rule_tag_map = array(
      'DSTRING' => 'STRING',
      'SSTRING' => 'STRING',
      'COMMENT1' => 'COMMENT',
      'COMMENT2' => 'COMMENT',
      'CDATA' => 'COMMENT',
    );
      
    parent::__construct($src);
  }
  
  
  function scan_child($lang) {
    assert (isset($this->child_scanners[$lang])) or die("No such child scanner: $lang");    
    $scanner = $this->child_scanners[$lang];   
    $scanner->pos($this->pos());
    $substr = $scanner->main();
    $this->tag($substr, null, true);
    $this->pos($scanner->pos());
    if (!$scanner->clean_exit) {
      $this->child_state = array($lang, $this->pos());
    } else {
      $this->child_state = null;
    }
  }
  
  
  function init() {
    $this->add_pattern('', '/&/');
    if ($this->embedded_server) {
      $this->add_pattern('TERM', '/<\?/');
    }
    $this->add_pattern('', '/</');
    $this->state_ = 'global';
  }
  
  function main() {
    
    $tagname = '';
    
    $expecting = '';
    $this->out = '';
    while (!$this->eos()) {
      
      if (!$this->clean_exit) {
        $tok = $this->resume();
        $this->tag($this->match(), $tok);
        continue;
      }
      
      if ($this->child_state !== null && $this->child_state[1] < $this->pos()) {
        $this->scan_child($this->child_state[0]);
        continue;
      }
      
      $in_tag = $this->state_ === 'tag';
      if (!$in_tag) {
        $next = $this->next_match(false);
        if($next) {
          $skip = $next[1] - $this->pos();
          $this->tag($this->get($skip), null);
          if ($next[0] === 'TERM') {
            break;
          }
        }
      }
      $index = $this->pos();      
      $c = $this->peek();
      
      $tok = null;
      $get = false;
      if (!$in_tag && $c === '&'
        && $this->scan('/&[^;\s]+;/')
      ) $tok = 'ESC';
      elseif(!$in_tag && $c === '<') {
        if ($this->peek(2) === '<!') {
          if($this->scan('/(<)(!DOCTYPE)/i')) {
            // special case: doctype
            $matches = $this->match_groups();
            $this->tag($matches[1], null);
            $this->tag($matches[2], 'KEYWORD');
            $this->state_ = 'tag';
            continue;
          }
          // urgh
          elseif($this->scan('/<!\\[CDATA\\[.*?(?:\\]\\]>|$)/is')) 
            $tok = 'CDATA';
          elseif($this->scan('/<!--.*?-->/s')) $tok = 'COMMENT1';
          elseif($this->scan('/<!.*?>/s')) $tok = 'COMMENT2';
          else assert(0);
        } else {
          // check for <script>          
          $this->state_ = 'tag';
          $expecting = 'tagname';
          $get = true;
        }
      }
      elseif($c === '>') {
        $get = true; 
        $this->state_ = 'global';
        if ($tagname === 'script' || $tagname === 'style') {
          $this->tag($this->get(), null);          
          $this->scan_child( ($tagname === 'script')? 'js' : 'css');
          continue;          
        }
        $tagname = '';
      }
      elseif($in_tag && 
        $c === "'" && $this->scan("/' (?: [^'\\\\>]+ | \\\\.)* (?:'|$|(?=>))/xs")) {          
        $tok = 'SSTRING';
        $expecting = '';
      }
      
      elseif($in_tag && 
        $c === '"' && $this->scan('/" (?: [^"\\\\>]+ | \\\\.)* (?:"|$|(?=>))/xs')) {
        $tok = 'DSTRING';
        $expecting = '';
      }
      elseif($in_tag && $this->scan('/[^\s=<>]+/')) {
        if ($expecting === 'tagname') {
          $tok = 'HTMLTAG';
          $expecting = '';
          $tagname = strtolower($this->match());
        }
        elseif($expecting === 'value') {
          $tok = 'VALUE'; // val as in < a href=*/index.html*
          $expecting = '';
        }
        else {
          $tok = 'TYPE';     // attr, as in <a *HREF*= .... 
        }
      }
      elseif($in_tag && $c === '=') {
        $expecting = 'value';
        $get = true;
      }
      else $get = true;
      if (!$get && $this->server_break($tok)) break;

      $this->tag($get? $this->get(): $this->match(), $tok);
      assert ($index < $this->pos()) or die("Failed to consume for $tok");
    }
    return $this->out;
  }
  
}