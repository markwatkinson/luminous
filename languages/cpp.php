<?php

require_once(dirname(__FILE__) . '/include/c_func_list.php');
// TODO: trigraph... does anyone use these?

class LuminousCppScanner extends LuminousScanner {

  function __construct($src=null) {
    parent::__construct($src);
    $this->add_filter('preprocessor', 'PREPROCESSOR',
      array($this, 'preprocessor_filter'));

    $this->add_identifier_mapping('FUNCTION',
      $GLOBALS['luminous_c_funcs']);
    $this->add_identifier_mapping('KEYWORD',
      $GLOBALS['luminous_c_keywords']);
    $this->add_identifier_mapping('TYPE',
      $GLOBALS['luminous_c_types']);
  }

  function init() {
    
    // http://www.lysator.liu.se/c/ANSI-C-grammar-l.html
    //     D                       [0-9]
    //     L                       [a-zA-Z_]
    //     H                       [a-fA-F0-9]
    //     E                       [Ee][+-]?{D}+
    //     FS                      (f|F|l|L)
    //     IS                      (u|U|l|L)*//     
    //     {L}({L}|{D})*           ident
    //     0[xX]{H}+{IS}?          hex
    //     0{D}+{IS}?              octal 
    //     {D}+{IS}?               int
    //     L?'(\\.|[^\\'])+'       char 
    //     {D}+{E}{FS}?            real/float
    //     {D}*"."{D}+({E})?{FS}?  real/float
    //     {D}+"."{D}*({E})?{FS}?  real/float
    //     L?\"(\\.|[^\\"])*\"     string, but we should exclude nl
    
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_ML);
    $this->add_pattern('COMMENT', LuminousTokenPresets::$C_COMMENT_SL);
    $this->add_pattern('STRING', "/L?\"(?: [^\\\\\"\n]+ | \\\\.)*(?:$|\")/xms");
    // if memory serves, a char looks like this:
    $this->add_pattern('CHARACTER', 
      "/L? ' (?: \\\\(?: x[A-F0-9]{1,2}| . ) | . ) (?: '|$)/ixm");
    
    $this->add_pattern('OPERATOR', '@[!%^&*\-/+=~:?.|<>]+@');
    
    $this->add_pattern('NUMERIC', '/0[xX][A-F0-9]+[uUlL]*/i');
    $this->add_pattern('NUMERIC',  '/
    (?: 
      (?: \d* \.\d+   |   \d+\.\d*) 
      ([eE][+-]?\d+)?
      ([fFlL]?)
    )
    /ix');
    $this->add_pattern('NUMERIC', '/
      \d+([uUlL]+ | ([eE][+-]?\d+)?[fFlL]? | ) #empty string on the end
    /x'); //inc octal

    
    
    
    
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);
    $this->add_pattern('PREPROCESSOR', '/^[ \t]*\#/m');
    $this->add_pattern('IDENT', '/[a-zA-Z_]+\w*/');
  }

  // FIXME: for some reason <...> isn't being picked up by this
  // TEST: don't know if comments are yet.
  // Strings work though.
  static function preprocessor_filter_cb($matches) {
    if (isset($matches['STR']))
      return LuminousUtils::tag_block('STRING', $matches[0]);
    else
      return LuminousUtils::tag_block('COMMENT', $matches[0]);
  }

  static function preprocessor_filter($token) {
    $token = LuminousUtils::escape_token($token);
    $token[1] = preg_replace_callback("@
    (?P<STR>  \" (?: [^\\\\\n\"]+ | \\\\. )* (?: \"|$) | (?<=&lt;) .*? (?=&gt;))
      | // .*
      | /\* (?s:.*?) \*/
    @x",
      array('LuminousCppScanner', 'preprocessor_filter_cb'),
      $token[1]);
    return $token;
  }


  function main() {
    while (!$this->eos()) {
      $tok = null;
      $index = $this->pos();
      if (($rule = $this->next_match()) !== null) {
        $tok = $rule[0];
        if ($rule[1] > $index) {
          $this->record(substr($this->string(), $index, $rule[1] - $index), null);
        }
      } else {
        $this->record(substr($this->string(), $index), null);
        break;
      }

      // we employ some trickery to deal with comments inside
      // processor statements
      // http://gcc.gnu.org/onlinedocs/gcc-2.95.3/cpp_1.html
      if ($tok === 'PREPROCESSOR') {
        $this->unscan();
        $this->skip_whitespace();
        // special case: #if 0
        // pretty sure nulls everything inside it and doesn't nest?
        if ($this->scan("/\#\s*if\s+0\\b.*?^\s*\#endif/ms"))
          $tok = 'COMMENT';
        else  {
          // fortunately comments don't nest so we can zap this with a, errr,
          // fairly simple regex :-\
          // well it beats a loop and a stack anyway.
          $m = $this->scan("@ \# (?: [^/\n\\\\]+ | /\* (?s:.*?) \*/ | //.* | / | \\\\(?s:.) )* @x");
          assert($m !== null);
          // we'll leave highlighting the nested tokens as a task for a filter
        }
      }
      assert($this->pos() > $index);
      $this->record($this->match(), $tok);
    }
  }

}
