<?php


/*
 * Diff is a strange one because we could just highlight the lines and be done 
 * with it, but we are actually going to try to highlight the source code AND 
 * the diff format
 * 
 * As such, we handle formatting and tagging inside the scanner.
 */
class DiffScanner extends LuminousScanner {
  
  /* TODO: plug this into the language code selector in the old EasyAPI
   * when we port it across 
   * This function is just a placeholder and will be implemented properly
   * later.
   */
  static function get_child_scanner($filename) {
//     echo $filename;
    $spos = strrpos($filename, '.');
    if ($spos === false) {return null;}
    $ext = substr($filename, $spos+1);
    switch(strtolower($ext)) {
      case 'js': return 'JSScanner';
    }
    return null;
  }
  
  // TODO figure out context/unified format here and create appropriate
  // regex patterns.
  function string($src=null) {
    return parent::string($src);
  }
  
  function main() {
    
    $child = null;
    $last_index = -1;
    while (!$this->eos()) {
      $index = $this->pos();
      assert($index > $last_index);
      $last_index = $index;
      
      assert($this->bol());
      
      $tok = null;
      if ($this->scan('/diff\s.*$/m'))  $tok = 'KEYWORD';
      elseif($this->scan("@[+\-]{3}(\s+(?<path>.*)\t)?.*@")) {
        $m = $this->match_groups();
        // unified uses +++
        if ($m[0][0] === '+')
          $tok = 'DIFF_HEADER_NEW';
        else $tok = 'DIFF_HEADER_OLD';
        
        if (isset($m['path'])) {
          $filename = preg_replace('@.*\\\\/@', '', $m['path']);
          $child = self::get_child_scanner($filename);  
        }
      }
      elseif($this->scan('/@@.*/')) $tok = 'DIFF_RANGE';
      elseif($this->scan('/\\\\.*/')) $tok = null;     
      elseif($this->check('/^$/m')) $tok = null;
      else {
        // this is actual source code.
        // we're going to format this here.
        // we're going to extract the block, and try to re-assemble it as 
        // verbatim code, then highlight it, then figure out which lines are
        // which. Eek.
        
        $block = $this->scan("/(^([\-+ ]).*(\n)?)+/m");
        if (!strlen($block)) {
//           echo $this->rest();
//           echo $block;
          assert(0);
        }
        
        $lines = explode("\n", $block);
        $verbatim = array();
        $verbatim_ = '';
        $types = array();
        foreach($lines as $l) {
          if (!strlen($l) || $l[0] === ' ') $types[]= 'DIFF_UNCHANGED';
          elseif ($l[0] === '+') $types[] = 'DIFF_NEW';
          elseif ($l[0] === '-') $types[] = 'DIFF_OLD';
          else assert(0);
          $verbatim_[] = substr($l, 1);
        }
        $verbatim = implode("\n", $verbatim_);
        $escaped = false;
        $tagged;
        if ($child !== null) {
          $c = new $child;          
          $c->init();
          $c->string($verbatim);
          $c->main();
          $tagged = $c->tagged();          
          $escaped = true;
        } else { 
          $tagged = $verbatim;
        }
        $prefix = array('DIFF_UNCHANGED' => ' ', 'DIFF_NEW' => '+',
          'DIFF_OLD' => '-');
        $exp = explode("\n", $tagged);
        
        foreach($exp as $i=>$v) {
          $t = $types[$i];
          $this->record(
            $prefix[$t] . $v,
            $t, 
            $escaped);
          if ($i < count($exp)-1) $this->record("\n", null);
        }
        if ($this->eol()) $this->record($this->get(), null);
        
        continue;
      }
      assert($this->pos() > $index);
      assert($this->match() !== null);
      $this->record($this->match(), $tok);
      
      assert($this->eol());
      // consume newline
      if (!$this->eos()) $this->record($this->get(), null);
      
    }
  }
 
}