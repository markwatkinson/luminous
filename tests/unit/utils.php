<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
/*
 * Unit test for LuminousUtils module (class)
 */


include __DIR__ . '/helper.inc';

function test_balance() {
  // balanced delimiters:
  // most character when used as a dynamic delimiter (perl, ruby) will map to
  // themselves as the end delimiter. But brackets pair up.
  
  $balanced = array(
    '{' => '}',
    '[' => ']',
    '(' => ')',
    '<' => '>');

  for($i=32; $i<=126; $i++) {
    $chr = chr($i);
    $expected = isset($balanced[$chr])? $balanced[$chr] : $chr;
    if (($out = LuminousUtils::balance_delimiter($chr)) !== $expected) {
      echo "balance_delimiter($chr) = $out (expected $expected)\n";
      assert(0);
    }
  }
}


function _test_escape_token($token) {
  $escaped = LuminousUtils::escape_token($token);
  // name should be unchanged
  assert($token[0] === $escaped[0]);
  $expected =  $token[2]?
                 $token[1] // already escaped, should be unchanged
                 // else ...
                 : LuminousUtils::escape_string($token[1]);
  assert($escaped[1] === $expected);
  assert($escaped[2]);
}

function test_escape_token() {
  $tokens = array( 
    // unescaped should escape to HTML entities
    array('NAME', '<>&', false),
    array('NAME', 'no html entities here', false),
    // should be double escaped as the token is not yet escaped
    array('NAME', '&lt;&gt;&amp;', false),

    // should not be escaped
    array('NAME', '<>&', true),
    array('NAME', 'no html entities here', true),
    array('NAME', '&lt;&gt;&amp;', true)
  );

  foreach($tokens as $t) {
    _test_escape_token($t);
  }
}


test_balance();
test_escape_token();


