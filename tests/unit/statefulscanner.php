<?php

include 'helper.inc';

/*
 * Basic unit test for the stateful scanner.
 * The *general* test for this is the LaTeX scanner -- there are several
 * regression tests which test the general usage of this scanner. We'll
 * do a very cursory test of a stateful language and then test the
 * more primitive pushing and popping and recording functions
 */


abstract class tester extends LuminousStatefulScanner {

  public function __construct() {
    $this->filters = array();
    $this->stream_filters = array();
    // we'll have a simple language which allows various brackets, but the
    // rounded brackets don't allow any transitions
    $this->add_pattern('SQUARE', '/\\[/', '/\\]/');
    $this->add_pattern('ROUND', '/\\( .*? \\)/sx');
    $this->add_pattern('CURLY', '/\\{/', '/\\}/');
    $this->add_pattern('term', '/-+/m');
    $this->overrides['term'] = array($this, 'override');
    $this->transitions = array(
      'SQUARE' => array('SQUARE', 'ROUND', 'CURLY'),
      'CURLY' => array('SQUARE', 'ROUND', 'CURLY'),
    );
    // now our string
    $s = '[square](round){curly}'
          . '[square(round)]{curly(round)}(round{stillround})'
          . '------- ignore this';
    $this->string($s);
    assert($this->string() === $s);
  }

  abstract function test();
}

class language_tester extends tester {
  
  // this function should simply break exection
  function override($matches) {
    return true;
  }
  private function check_tree() {
    $tagged = $this->tagged();
    $expected = '<SQUARE>[square]</SQUARE><ROUND>(round)</ROUND><CURLY>{curly}</CURLY>'
      . '<SQUARE>[square<ROUND>(round)</ROUND>]'
      . '</SQUARE><CURLY>{curly<ROUND>(round)</ROUND>}</CURLY>'
      . '<ROUND>(round{stillround})</ROUND>';
    assert ($tagged === $expected);
  }
  public function test() {
    $this->init();
    $this->main();
    $this->check_tree();
  }
}

class api_tester extends tester {


  private function get_top_token() {
    return $this->token_tree_stack[count($this->token_tree_stack)-1];
  }


  public function test() {
    $this->init();
    $this->setup();

    // we're going to test some simple push and pop operations
    assert($this->state_name() === 'initial');
    $this->push_state(array('new state'));
    assert($this->state_name() === 'new state');
    $this->pop_state();
    assert($this->state_name() === 'initial');

    
    $this->push_state(array('state'));
    $top = $this->get_top_token();
    assert($top['token_name'] === 'state');
    assert(count($top['children']) === 0);
    // now let's push a complete child token
    $this->record_token('text', 'child');
    // it should not have changed the top of the stack, because it's
    // a complete token. It's already finalised.
    $top = $this->get_top_token();
    assert($top['token_name'] === 'state');
    // it should be present in the top token's child array
    assert(count($top['children']) === 1);
    // the child token should also have 1 child, which should be its actual
    // text, i.e. a string with the contents 'text'
    assert($top['children'][0]['token_name'] === 'child');
    assert(count($top['children'][0]['children']) === 1);
    assert($top['children'][0]['children'][0] === 'text');

    // now let's pop, and we should be back to the initial state
    $this->pop_state();
    $top = $this->get_top_token();
    assert($top['token_name'] === 'initial');
    // now let's test that we can't pop the initial state
    $exception = false;
    try { $this->pop_state(); }
    catch (Exception $e) { $exception = true; }
    assert($exception);
    
    $top = $this->get_top_token();
    // now record some text into the initial state's child array
    $c = count($top['children']);
    $this->record('some text');
    $top = $this->get_top_token();
    $c1 = count($top['children']);
    assert($c + 1 === $c1);
    assert($top['children'][$c1-1] === 'some text');

  }
}


$t = new language_tester();
$t->test();
$t = new api_tester();
$t->test();
