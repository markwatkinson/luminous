<?php

class LuminousHTMLScanner extends LuminousEmbeddedWebScript {
  
  private $child_state = null;

  public $scripts = true;
  
  // XML literals are part of several languages. Settings this makes the scanner
  // halt as soon as it pops the its root tag from the stack, so no trailing 
  // code is consumed.
  public $xml_literal = false;
  private $tag_stack = array();
  function __construct($src=null) {
   

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
    $this->tokens[] = array(null, $scanner->tagged(), true);
    $this->pos($scanner->pos());
    if ($scanner->interrupt) {
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
    if ($this->scripts) {
      $js = new LuminousJSScanner($this->string());
      $js->embedded_server = $this->embedded_server;
      $js->embedded_html = true;
      $js->init();

      $css = new LuminousCSSScanner($this->string());
      $css->embedded_server = $this->embedded_server;
      $css->embedded_html = true;
      $css->init();

      $this->add_child_scanner('js', $js);
      $this->add_child_scanner('css', $css);
    }
  }
  
  private $tagname = '';
  private $expecting = '';
  
  function main() {
    $this->start();
    $this->interrupt = false;
    while (!$this->eos()) {
      $index = $this->pos();
      
      if ($this->embedded_server &&  $this->peek(2) === '<?') {
        $this->interrupt = true;
        break;
      }      
      
      if (!$this->clean_exit) {
        $tok = $this->resume();
        $this->record($this->match(), $tok);
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
          $this->record($this->get($skip), null);
          if ($next[0] === 'TERM') {
//             $this->interrupt = true;
            break;
          }
        }
      } else {
        
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
            $this->record($matches[1], null);
            $this->record($matches[2], 'KEYWORD');
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
          $this->expecting = 'tagname';
          $get = true;
        }
      }
      elseif($c === '>') {
        $get = true; 
        $this->state_ = 'global';
        if ($this->scripts && ($this->tagname === 'script' || $this->tagname === 'style')) {
          $this->record($this->get(), null);          
          $this->scan_child( ($this->tagname === 'script')? 'js' : 'css');
          continue;
        }
        $this->tagname = '';
      }
      elseif($in_tag && $this->scan('@/\s*>@')) {
        $this->state_ = 'global';
        array_pop($this->tag_stack);
      }
      elseif($in_tag && 
        $c === "'" && $this->scan("/' (?: [^'\\\\>]+ | \\\\.)* (?:'|$|(?=>))/xs")) {
        $tok = 'SSTRING';
        $this->expecting = '';
      }
      
      elseif($in_tag && 
        $c === '"' && $this->scan('/" (?: [^"\\\\>]+ | \\\\.)* (?:"|$|(?=>))/xs')) {
        $tok = 'DSTRING';
        $this->expecting = '';
      }
      elseif($in_tag && $this->scan('/[^\s=<>]+/')) {
        if ($this->expecting === 'tagname') {
          $tok = 'HTMLTAG';
          $this->expecting = '';
          $this->tagname = strtolower($this->match());
          if ($this->tagname[0] === '/') array_pop($this->tag_stack);
          else $this->tag_stack[] = $this->tagname;
        }
        elseif($this->expecting === 'value') {
          $tok = 'VALUE'; // val as in < a href=*/index.html*
          $this->expecting = '';
        }
        else {
          $tok = 'TYPE';     // attr, as in <a *HREF*= .... 
        }
      }
      elseif($in_tag && $c === '=') {
        $this->expecting = 'value';
        $get = true;
      }
      else $get = true;
      if (!$get && $this->server_break($tok)) break;

      $this->record($get? $this->get(): $this->match(), $tok);
      assert ($index < $this->pos()) or die("Failed to consume for $tok");

      if ($this->xml_literal && $this->state_ !== 'tag' && empty($this->tag_stack)) {
        return;
      }
    }
  }
  
}