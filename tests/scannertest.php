<?php
require_once ('../src/core/scanner.class.php');
assert_options(ASSERT_BAIL, 1);


/**
 * Basic unit test for the Scanner class.
 */

// Simple tests of basic Scanner functions: 
// eos, string, rest, pos, peek, get, scan, match*.
// TODO: Others.
$string = '0123456789';
$s = new Scanner($string);
assert($s->string() === $string);
assert($s->rest() === $string);
assert($s->pos() === 0);
assert(!$s->eos());
assert($s->peek() === '0');
assert($s->peek(1) === '0');
assert($s->peek(2) === '01');
assert($s->peek(20) === $string);
assert($s->get() === '0');
assert($s->get(1) === '1');
assert($s->get(2) === '23');
assert($s->get(20) === '456789');
assert($s->eos());
$s->reset();
assert($s->pos() === 0);

// test scan and match logging
assert($s->scan('/\d/') === '0');
assert($s->pos() === 1);
assert($s->match() === '0');
assert($s->match_group(0) === '0');
assert($s->match_groups() === array(0=>'0'));
assert($s->scan('/(?P<one>\d)\d{2}.(\d(\d))/') === '123456');
assert($s->match_group(0) === '123456');
assert($s->match_group('one') === '1');
assert($s->match_group(2) === '56');
assert($s->match_group(3) === '6');
assert($s->match_pos() === 1);
assert($s->pos() === 7);
$s->unscan();
//repeat
assert($s->pos() === 1);
assert($s->match() === '0');
assert($s->match_group(0) === '0');
assert($s->match_groups() === array(0=>'0'));

// test check with the same data as we just used for scan
assert($s->check('/(?P<one>\d)\d{2}.(\d(\d))/') === '123456');
assert($s->match_group(0) === '123456');
assert($s->match_group('one') === '1');
assert($s->match_group(2) === '56');
assert($s->match_group(3) === '6');
assert($s->match_pos() === 1);
assert($s->pos() === 1); // but check pos hasn't moved.


$s->reset();


