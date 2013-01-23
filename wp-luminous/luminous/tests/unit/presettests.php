<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
include 'helper.inc';

/**
 * Unit tests for the preset token regexp patterns. 
 */

function test_regex($regex, $string, $expected_match) {
  $c = preg_match($regex, $string, $matches);
  assert (($c === 0 && !$expected_match) || ($c && $matches[0] === $expected_match));
}

////////////////////////////////////////////
// double quoted string

// empty string
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '""', '""'); 
// simple cases
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '"a string"', '"a string"');
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '"a string" not a string', '"a string"');  
// ugly escape sequences
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '"a \\"string\\"" not a string', '"a \\"string\\""');  
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '"a \\"string\\"\\\\" not a string', '"a \\"string\\"\\\\"');  
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '"a string...', '"a string...');
// leading rubbish
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '\'hello\'"a string...', '"a string...');    
// no match
test_regex(LuminousTokenPresets::$DOUBLE_STR,
  '12345', false);

test_regex(LuminousTokenPresets::$DOUBLE_STR, 
  '"1
2
3"', '"1
2
3"');


/// single line
test_regex(LuminousTokenPresets::$DOUBLE_STR_SL, 
  '"1
2
3"', '"1');




///////////////////////////////////////////
// single quoted string

// empty string
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "''", "''"); 
// simple cases
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'a string'", "'a string'");  
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'a string' not a string", "'a string'");  
// ugly escape sequences
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'a \\'string\\'' not a string", "'a \\'string\\''");  
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'a \\'string\\'\\\\' not a string", "'a \\'string\\'\\\\'");  
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'a string...", "'a string...");
// leading rubbish
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "\"hello\"'a string...", "'a string...");  
// no match
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "12345", false);  
  
test_regex(LuminousTokenPresets::$SINGLE_STR,
  "'1
2
3'", "'1
2
3'");


/// single line
test_regex(LuminousTokenPresets::$SINGLE_STR_SL, 
  "'1
2
3'", "'1");



  
///////////////////////////////////////////////
// Hex numbers

// no hex
test_regex(LuminousTokenPresets::$NUM_HEX,  '0123456789', false);
test_regex(LuminousTokenPresets::$NUM_HEX,'0xHELLO', false);
test_regex(LuminousTokenPresets::$NUM_HEX,'0x', false);
test_regex(LuminousTokenPresets::$NUM_HEX,'Ox1', false);


// yes hex
test_regex(LuminousTokenPresets::$NUM_HEX,'0X1', '0X1');
test_regex(LuminousTokenPresets::$NUM_HEX, '0xE110', '0xE110');
test_regex(LuminousTokenPresets::$NUM_HEX, '0x0123456789ABCDEFG', '0x0123456789ABCDEF');
test_regex(LuminousTokenPresets::$NUM_HEX, '0xcafe', '0xcafe');





///////////////////////////////////////////////////
// real numbers
// we don't include the sign because most programming languages regard that
// as a unary operator
test_regex(LuminousTokenPresets::$NUM_REAL, 'XYZ;rfsa', false);
test_regex(LuminousTokenPresets::$NUM_REAL, '0xhello', '0');
test_regex(LuminousTokenPresets::$NUM_REAL, '0', '0');
test_regex(LuminousTokenPresets::$NUM_REAL, '0123456789', '0123456789');
test_regex(LuminousTokenPresets::$NUM_REAL, '10', '10');
test_regex(LuminousTokenPresets::$NUM_REAL, '10L', '10');
test_regex(LuminousTokenPresets::$NUM_REAL, '010', '010');
test_regex(LuminousTokenPresets::$NUM_REAL, '-10', '10');
test_regex(LuminousTokenPresets::$NUM_REAL, '3.14159', '3.14159');
test_regex(LuminousTokenPresets::$NUM_REAL, '2.71', '2.71');
test_regex(LuminousTokenPresets::$NUM_REAL, '.71', '.71');
test_regex(LuminousTokenPresets::$NUM_REAL, '71e100', '71e100');
test_regex(LuminousTokenPresets::$NUM_REAL, '-23e+1', '23e+1');
test_regex(LuminousTokenPresets::$NUM_REAL, '-23e-1', '23e-1');
test_regex(LuminousTokenPresets::$NUM_REAL, '-23e-A', '23');
test_regex(LuminousTokenPresets::$NUM_REAL, '1.234E5678', '1.234E5678');
test_regex(LuminousTokenPresets::$NUM_REAL, '.234E5678', '.234E5678');






////////////////////////////////////////////////////////////////////////////
// Comments
test_regex(LuminousTokenPresets::$C_COMMENT_SL, 'xyz', false);
test_regex(LuminousTokenPresets::$C_COMMENT_SL, 'x//h', '//h');
test_regex(LuminousTokenPresets::$C_COMMENT_SL, 'x// line
nextline', '// line');


test_regex(LuminousTokenPresets::$C_COMMENT_ML, 'xyz', false);
test_regex(LuminousTokenPresets::$C_COMMENT_ML, 'x//h', false);
test_regex(LuminousTokenPresets::$C_COMMENT_ML, '/* comment */', '/* comment */');
test_regex(LuminousTokenPresets::$C_COMMENT_ML, '/* comment ', '/* comment ');
test_regex(LuminousTokenPresets::$C_COMMENT_ML, '/* line
nextline
*/', '/* line
nextline
*/');

