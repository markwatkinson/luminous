<?php

// Haskell scanner.
// We do not yet support TemplateHaskell because it looks INSANE.

require_once(dirname(__FILE__) . '/include/haskell.php');

class LuminousHaskellScanner extends LuminousSimpleScanner {

  // handles comment nesting of multiline comments.
  function comment_override() {
    assert($this->peek(2) === '{-');
    $stack = 0;
    $patterns = array('/\\{-/', '/-\\}/');
    $start = $this->pos();
    do {
      $next = $this->get_next($patterns);
      // no matches
      if ($next[0] === -1) {
        $this->terminate();
        break;
      }
      if ($next[1][0][0] === '{') $stack++;
      else $stack--;
      $this->pos($next[0] + 2);
    } while ($stack > 0);
    $this->record(
      substr($this->string(),
             $start,
             $this->pos()-$start),
      'COMMENT');
  }

  function init() {
    // import from ./include/
    global $luminous_haskell_functions;
    global $luminous_haskell_types;
    global $luminous_haskell_values;
    global $luminous_haskell_keywords;
    $this->add_identifier_mapping('KEYWORD', $luminous_haskell_keywords);
    $this->add_identifier_mapping('TYPE', $luminous_haskell_types);
    $this->add_identifier_mapping('FUNCTION', $luminous_haskell_functions);
    $this->add_identifier_mapping('VALUE', $luminous_haskell_values);

    // Refer to the sections in
    // http://www.haskell.org/onlinereport/lexemes.html
    // for the rules implemented here.
    
    // 2.4
    $this->add_pattern('TYPE', '/[A-Z][\'\w]*/');
    $this->add_pattern('IDENT', '/[_a-z][\'\w]*/');

    $op_chars = '\\+%^\\/\\*\\?#<>:;=@\\[\\]\\|\\\\~\\-!$@';

    // ` is used to make a function call into an infix operator
    // CRAZY OR WHAT.
    $this->add_pattern('OPERATOR', '/`[^`]*`/');
    // some kind of function, lambda, maybe.
    $this->add_pattern('FUNCTION', "/\\\\(?![$op_chars])\S+/");
    
    // Comments are hard!
    // http://www.mail-archive.com/haskell@haskell.org/msg09019.html
    // According to this, we can PROBABLY, get away with checking either side
    // for non-operator chars followed by at least 2 dashes, but I could well
    // be wrong. It'll do for now.
    $this->add_pattern('COMMENT', "/(?<![$op_chars])---*(?![$op_chars]).*/");
    // nested comments are easy!
    $this->add_pattern('NESTED_COMMENT', '/\\{-/');
    $this->overrides['NESTED_COMMENT'] = array($this, 'comment_override');
    $this->rule_tag_map['NESTED_COMMENT'] = 'COMMENT';
    $this->add_pattern('OPERATOR', "/[$op_chars]+/");

    // FIXME: the char type is way more discriminating than this
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR_SL);
    $this->add_pattern('CHARACTER', LuminousTokenPresets::$SINGLE_STR_SL);

    // 2.5
    $this->add_pattern('NUMERIC', '/
      0[oO]\d+  #octal
      |
      0[xX][a-fA-F\d]+  #hex
      |
      # decimal and float can be done at once, according to the grammar
      \d+ (?: (?:\.\d+)? (?: [eE][+-]? \d+))?
      /x');
    
  }

}
