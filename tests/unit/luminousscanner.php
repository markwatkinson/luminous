<?php

include 'helper.inc';

/*
  * Unit test of LuminousScanner:
  * current coverage: record(), record_range() and stack related functions.
  * TODO: Filtering infrastructure, XML conversion
  */

class LuminousScanner_test extends LuminousScanner {

  function stack_test() {
    $datas = array(0, 1, true, false, null, 'test data', $this);
    assert($this->state() === null);
    foreach($datas as $d) {
      $this->push($d);
    }
    foreach(array_reverse($datas) as $d) {
      assert($this->state() === $d);
      assert($this->pop() === $d);
      assert($this->state() !== $d);
    }
    assert($this->state() === null);
    $exception = false;
    try { $this->pop(); }
    catch(Exception $e) { $exception = true; }
    assert($exception);
  }

  function record_test() {
    $toks = $this->token_array();
    assert(empty($toks));
    // correct use case:
    $tokens = array(
      array('TOK_TYPE', 'some string'),
      array('another_token', 'string'),
      array(null, 'null token')
    );
    $count = 0;
    foreach($tokens as $t) {
      list($type, $string) = $t;
      $this->record($string, $type);
      $toks = $this->token_array();
      $c = count($toks);
      assert ($c === ++$count);
      $top = $toks[count($toks)-1];
      list($rec_type, $rec_str, $xml) = $top;
      assert($rec_type === $type);
      assert($rec_str === $string);
      assert($xml === false);
    }

    // error case
    $exception = false;
    try { $this->record(null, 'token_name'); }
    catch (Exception $e) { $exception = true; }
    assert($exception);

    $ranges = array(
      array(0, 1, null),
      array(0, 3, 'type1'),
      array(1, 2, 'type2'),
      array(1, 3, null)
    );
    $s = '12345';
    $this->string($s);
    $this->start(); // flushes token stream
    $count = 0;
    foreach($ranges as $r) {
      $this->record_range($r[0], $r[1], $r[2]);
      $toks = $this->token_array();
      $c = count($toks);
      assert ($c === ++$count);
      $top = $toks[count($toks)-1];
      list($rec_type, $rec_str, $xml) = $top;
      assert($rec_type === $r[2]);
      assert($rec_str === substr($s, $r[0], $r[1]-$r[0]));
      assert($xml === false);
    }
  }

  function test() {
    $this->stack_test();
    $this->record_test();
  }
}

$tester = new LuminousScanner_test();
$tester->test();