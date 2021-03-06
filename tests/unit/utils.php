<?php

use Luminous\Core\Utils;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
/*
 * Unit test for LuminousUtils module (class)
 */

include __DIR__ . '/helper.inc';

function testBalance()
{
    // balanced delimiters:
    // most character when used as a dynamic delimiter (perl, ruby) will map to
    // themselves as the end delimiter. But brackets pair up.

    $balanced = array(
        '{' => '}',
        '[' => ']',
        '(' => ')',
        '<' => '>'
    );

    for ($i = 32; $i <= 126; $i++) {
        $chr = chr($i);
        $expected = isset($balanced[$chr]) ? $balanced[$chr] : $chr;
        if (($out = Utils::balanceDelimiter($chr)) !== $expected) {
            echo "balanceDelimiter($chr) = $out (expected $expected)\n";
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

function testEscapeToken()
{
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
        $escaped = Utils::escapeToken($t);
        // name should be unchanged
        assert($t[0] === $escaped[0]);
        if ($t[2]) {
            $expected = $t[1]; // already escaped, should be unchanged
        } else {
            $expected = Utils::escapeString($t[1]);
        }
        assert($escaped[1] === $expected);
        assert($escaped[2]);
    }
}


testBalance();
testEscapeToken();
