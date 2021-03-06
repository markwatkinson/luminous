<?php

use Luminous\Core\Scanners\StringScanner;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
include 'helper.inc';

/**
 * Unit test for the Scanner class.
 *
 */

// first we're going to test string(), rest(), pos() manipulation and the
// eol, eos and bol with multiline data.
$string = '0
1
2
3
4

6
7';
$s = new StringScanner($string);
assert($s->string() === $string);
assert($s->rest() === $string);
assert($s->pos() === 0);
assert(!$s->eos());
assert(!$s->eol());
assert($s->bol());
$s->terminate();
assert(!$s->bol());
assert($s->eol());
assert($s->eos());

$s->reset();
$s->pos(1);
assert($s->eol());
assert(!$s->eos());
assert(!$s->bol());
$s->pos(2); // ON the newline character
assert(!$s->eol());
assert(!$s->eos());
assert($s->bol());
assert($s->peek() === '1');
$s->pos(10); // the blank line
assert($s->bol());
assert($s->eol());
assert(!$s->eos());

// Check pos ranges
$s->pos(3);
assert($s->pos() === 3);
$s->posShift(2);
assert($s->pos() === 5);
$s->posShift(-1);
assert($s->pos() === 4);
$s->posShift(99);
assert($s->pos() === strlen($string));
$s->posShift(-900);
assert($s->pos() === 0);

$s->reset();

$string = '0123456789';
$s->string($string);

assert($s->peek() === '0');
assert($s->peek(1) === '0');
assert($s->peek(2) === '01');
assert($s->peek(20) === $string);
assert($s->get() === '0');
assert($s->get(1) === '1');
assert($s->get(2) === '23');
assert($s->rest() === '456789');
assert($s->get(20) === '456789');
assert($s->eos());
assert($s->rest() === '');
$s->reset();
assert($s->pos() === 0);

// test scan and match_* functions, and unscan
assert($s->scan('/\d/') === '0');
assert($s->pos() === 1);
assert($s->match() === '0');
assert($s->matchGroup(0) === '0');
assert($s->matchGroups() === array(0=>'0'));
assert($s->scan('/(?P<one>\d)\d{2}.(\d(\d))/') === '123456');
assert($s->matchGroup(0) === '123456');
assert($s->matchGroup('one') === '1');
assert($s->matchGroup(2) === '56');
assert($s->matchGroup(3) === '6');
assert($s->matchPos() === 1);
assert($s->pos() === 7);
$s->unscan();
//repeat
assert($s->pos() === 1);
assert($s->match() === '0');
assert($s->matchGroup(0) === '0');
assert($s->matchGroups() === array(0=>'0'));

// test check and match_* functions the same data as we just used for scan
assert($s->check('/(?P<one>\d)\d{2}.(\d(\d))/') === '123456');
assert($s->matchGroup(0) === '123456');
assert($s->matchGroup('one') === '1');
assert($s->matchGroup(2) === '56');
assert($s->matchGroup(3) === '6');
assert($s->matchPos() === 1);
assert($s->pos() === 1); // but check pos hasn't moved.
$s->check('/\d+/');
assert($s->match() === '123456789');
$s->unscan();
//repeat
assert($s->pos() === 1);
assert($s->matchGroup(0) === '123456');
assert($s->matchGroup('one') === '1');
assert($s->matchGroup(2) === '56');
assert($s->matchGroup(3) === '6');
assert($s->matchPos() === 1);

$s->reset();


// now check scan_until
assert($s->scanUntil('/5/') === '01234');
assert($s->match() === '01234');
assert($s->matchGroup(0) === '01234');
assert($s->matchGroups() === array(0=>'01234'));
assert($s->pos() === 5);


// now check index
$s->reset();
assert($s->index('/0/') === 0);
assert($s->index('/2/') === 2);
assert($s->index('/21/') === -1);
$s->pos(5);
assert($s->index('/0/') === -1);
assert($s->index('/6/') === 6);


// Now the automation functions
$s->string('012 45 ');
$s->addPattern('zero', '/0/');
$s->addPattern('one-dummy', '/1/');
$s->addPattern('one', '/1/');
$s->addPattern('two', '/2/');
$s->addPattern('digit', '/\d/');
$s->addPattern('four', '/4/');

$s->removePattern('one-dummy');
// 4 should never match, digit will take precedence.
// one-dummy should never match, it was removed.

$out = $s->nextMatch();
assert($out === array(0=>'zero', 1=>0));
assert($s->match() === '0');
assert($s->matchPos() === 0);
assert($s->pos() === 1);

// don't log this one
$out = $s->nextMatch(false);
assert($out === array(0=>'one', 1=>1));
// match info and pos unchanged
assert($s->match() === '0');
assert($s->matchPos() === 0);
assert($s->pos() === 1);

// do log it now
$out = $s->nextMatch();
assert($out === array(0=>'one', 1=>1));
assert($s->match() === '1');
assert($s->matchPos() === 1);
assert($s->pos() === 2);

$out = $s->nextMatch();
assert($out === array(0=>'two', 1=>2));
assert($s->match() === '2');
assert($s->matchPos() === 2);
assert($s->pos() === 3);

// we won't get a match for index 3, we're onto digit now and we'll have
// skipped an index
$out = $s->nextMatch();
assert($out === array(0=>'digit', 1=>4));
assert($s->match() === '4');
assert($s->matchPos() === 4);
assert($s->pos() === 5);

// this is the final match
$out = $s->nextMatch();
assert($out === array(0=>'digit', 1=>5));
assert($s->match() === '5');
assert($s->matchPos() === 5);
assert($s->pos() === 6);

$out = $s->nextMatch();
assert($out === null);



$s->reset();

$s->string('01 23;45');
$out = $s->getNext(array('/\d+/', '/\s+/'));
assert($out === array(0=> 0, 1=> array(0=>'01')));
$s->posShift(2);
$out = $s->getNext(array('/\d+/', '/\s+/'));
assert($out === array(0=> 2, 1=> array(0=>' ')));
$s->posShift(1);

$out = $s->getNext(array('/\d+/', '/\s+/'));
assert($out === array(0=> 3, 1=> array(0=>'23')));
$s->posShift(2);
// note we've skipped over the ;
$out = $s->getNext(array('/\d+/', '/\s+/'));
assert($out ===array(0=> 6, 1=> array(0=>'45')));
$s->posShift(3);

$out = $s->getNext(array('/\d+/', '/\s+/'));
assert($out === array(0=>-1, 1=>null));

$s->reset();

$out = $s->getNextStrpos(array('1', ';'));
assert($out ===array(0=> 1, 1=> '1'));
$s->posShift(2);
$out = $s->getNextStrpos(array('1', ';'));
assert($out ===array(0=> 5, 1=> ';'));
$s->pos(6);
$out = $s->getNextStrpos(array('1', ';'));
assert($out === array(-1, null));


$s->reset();
$s->string('0123');
$rules = array('one'=>'/1/', 'zero'=>'/0/', 'digit' => '/\d+/');
$out = $s->getNextNamed($rules);
assert($out === array('zero', 0, array('0')));
$s->posShift(1);

$out = $s->getNextNamed($rules);
assert($out === array('one', 1, array('1')));
$s->posShift(1);
$out = $s->getNextNamed($rules);
assert($out === array('digit', 2, array('23')));

// rest() had a problem being independent on two scanners
$s2 = new StringScanner('123');
$s3 = new StringScanner('456');
assert($s2->rest() === '123');
assert($s3->rest() === '456');
assert($s2->rest() === $s2->string());
assert($s3->rest() === $s3->string());
