<?php

use Luminous\Core\TokenPresets;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
include 'helper.inc';

/**
 * Unit tests for the preset token regexp patterns.
 */

function testRegex($regex, $string, $expectedMatch)
{
    $c = preg_match($regex, $string, $matches);
    assert(($c === 0 && !$expectedMatch) || ($c && $matches[0] === $expectedMatch));
}

////////////////////////////////////////////
// double quoted string

// empty string
testRegex(TokenPresets::$DOUBLE_STR, '""', '""');
// simple cases
testRegex(TokenPresets::$DOUBLE_STR, '"a string"', '"a string"');
testRegex(TokenPresets::$DOUBLE_STR, '"a string" not a string', '"a string"');
// ugly escape sequences
testRegex(TokenPresets::$DOUBLE_STR, '"a \\"string\\"" not a string', '"a \\"string\\""');
testRegex(TokenPresets::$DOUBLE_STR, '"a \\"string\\"\\\\" not a string', '"a \\"string\\"\\\\"');
testRegex(TokenPresets::$DOUBLE_STR, '"a string...', '"a string...');
// leading rubbish
testRegex(TokenPresets::$DOUBLE_STR, '\'hello\'"a string...', '"a string...');
// no match
testRegex(TokenPresets::$DOUBLE_STR, '12345', false);

testRegex(
    TokenPresets::$DOUBLE_STR,
    '"1
2
3"',
    '"1
2
3"'
);

/// single line
testRegex(
    TokenPresets::$DOUBLE_STR_SL,
    '"1
2
3"',
    '"1'
);

///////////////////////////////////////////
// single quoted string

// empty string
testRegex(TokenPresets::$SINGLE_STR, "''", "''");
// simple cases
testRegex(TokenPresets::$SINGLE_STR, "'a string'", "'a string'");
testRegex(TokenPresets::$SINGLE_STR, "'a string' not a string", "'a string'");
// ugly escape sequences
testRegex(TokenPresets::$SINGLE_STR, "'a \\'string\\'' not a string", "'a \\'string\\''");
testRegex(TokenPresets::$SINGLE_STR, "'a \\'string\\'\\\\' not a string", "'a \\'string\\'\\\\'");
testRegex(TokenPresets::$SINGLE_STR, "'a string...", "'a string...");
// leading rubbish
testRegex(TokenPresets::$SINGLE_STR, "\"hello\"'a string...", "'a string...");
// no match
testRegex(TokenPresets::$SINGLE_STR, "12345", false);

testRegex(
    TokenPresets::$SINGLE_STR,
    "'1
2
3'",
    "'1
2
3'"
);

/// single line
testRegex(
    TokenPresets::$SINGLE_STR_SL,
    "'1
2
3'",
    "'1"
);

///////////////////////////////////////////////
// Hex numbers

// no hex
testRegex(TokenPresets::$NUM_HEX, '0123456789', false);
testRegex(TokenPresets::$NUM_HEX, '0xHELLO', false);
testRegex(TokenPresets::$NUM_HEX, '0x', false);
testRegex(TokenPresets::$NUM_HEX, 'Ox1', false);

// yes hex
testRegex(TokenPresets::$NUM_HEX, '0X1', '0X1');
testRegex(TokenPresets::$NUM_HEX, '0xE110', '0xE110');
testRegex(TokenPresets::$NUM_HEX, '0x0123456789ABCDEFG', '0x0123456789ABCDEF');
testRegex(TokenPresets::$NUM_HEX, '0xcafe', '0xcafe');

///////////////////////////////////////////////////
// real numbers
// we don't include the sign because most programming languages regard that
// as a unary operator
testRegex(TokenPresets::$NUM_REAL, 'XYZ;rfsa', false);
testRegex(TokenPresets::$NUM_REAL, '0xhello', '0');
testRegex(TokenPresets::$NUM_REAL, '0', '0');
testRegex(TokenPresets::$NUM_REAL, '0123456789', '0123456789');
testRegex(TokenPresets::$NUM_REAL, '10', '10');
testRegex(TokenPresets::$NUM_REAL, '10L', '10');
testRegex(TokenPresets::$NUM_REAL, '010', '010');
testRegex(TokenPresets::$NUM_REAL, '-10', '10');
testRegex(TokenPresets::$NUM_REAL, '3.14159', '3.14159');
testRegex(TokenPresets::$NUM_REAL, '2.71', '2.71');
testRegex(TokenPresets::$NUM_REAL, '.71', '.71');
testRegex(TokenPresets::$NUM_REAL, '71e100', '71e100');
testRegex(TokenPresets::$NUM_REAL, '-23e+1', '23e+1');
testRegex(TokenPresets::$NUM_REAL, '-23e-1', '23e-1');
testRegex(TokenPresets::$NUM_REAL, '-23e-A', '23');
testRegex(TokenPresets::$NUM_REAL, '1.234E5678', '1.234E5678');
testRegex(TokenPresets::$NUM_REAL, '.234E5678', '.234E5678');

////////////////////////////////////////////////////////////////////////////
// Comments
testRegex(TokenPresets::$C_COMMENT_SL, 'xyz', false);
testRegex(TokenPresets::$C_COMMENT_SL, 'x//h', '//h');
testRegex(
    TokenPresets::$C_COMMENT_SL,
    'x// line
nextline',
    '// line'
);

testRegex(TokenPresets::$C_COMMENT_ML, 'xyz', false);
testRegex(TokenPresets::$C_COMMENT_ML, 'x//h', false);
testRegex(TokenPresets::$C_COMMENT_ML, '/* comment */', '/* comment */');
testRegex(TokenPresets::$C_COMMENT_ML, '/* comment ', '/* comment ');
testRegex(
    TokenPresets::$C_COMMENT_ML,
    '/* line
nextline
*/',
    '/* line
nextline
*/'
);
