<?php



class HTMLScanner extends LuminousEmbeddedWebScript {
  
  private $child_state = null;
  
  function __construct($src=null) {
    $this->add_child_scanner('js', new JSScanner($src));
    $this->add_child_scanner('css', new CSSScanner($src));
    
    $this->add_pattern('', '/&/');
    $this->add_pattern('TERM', '/<\?/');    
    $this->add_pattern('', '/</');
    $this->state_ = 'global';
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
      echo "$lang exited badly at " . $this->pos() . "\n";
      $this->child_state = array($lang, $this->pos());
    } else {
      $this->child_state = null;
    }
  }
  
  function main() {
    
    $tagname = '';
    
    $expecting = '';
    $this->out = '';
    while (!$this->eos()) {
      
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
            $this->clean_exit = false;
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
          if($this->scan('/(<!\s*)(DOCTYPE)(\s+)([^>]*)(\s*>)/i')) {
            // special case: doctype
            $matches = $this->match_groups();
            $this->tag($matches[1], null);
            $this->tag($matches[2], 'KEYWORD');
            $this->tag($matches[3], 'KEYWORD');            
            $this->tag($matches[4], 'VALUE');
            $this->tag($matches[5], null);
            continue;
          }
          elseif($this->scan('/<!--.*?-->/s')) {}
          elseif($this->scan('/<!.*?>/s')) {}
          else assert(0);
          $tok = 'COMMENT';
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
        ($c === '"' && $this->scan("/' (?: [^'\\\\>]+ | \\\\.)* (?:'|$|(?=>))/xs")
        || $c === '"' && $this->scan('/" (?: [^"\\\\>]+ | \\\\.)* (?:"|$|(?=>))/xs'))) {
        $tok = 'STRING';
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
      $this->tag($get? $this->get(): $this->match(), $tok);
      assert ($index < $this->pos()) or die("Failed to consume for $tok");
    }
    return $this->out;
  }
  
}