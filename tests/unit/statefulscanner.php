<?php

use Luminous\Core\Scanners\StatefulScanner;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
include 'helper.inc';

/*
 * Basic unit test for the stateful scanner.
 * The *general* test for this is the LaTeX scanner -- there are several
 * regression tests which test the general usage of this scanner. We'll
 * do a very cursory test of a stateful language and then test the
 * more primitive pushing and popping and recording functions
 */

abstract class StatefulScannerTest extends StatefulScanner
{
    public function __construct()
    {
        $this->filters = array();
        $this->streamFilters = array();
        // we'll have a simple language which allows various brackets, but the
        // rounded brackets don't allow any transitions
        $this->addPattern('SQUARE', '/\\[/', '/\\]/');
        $this->addPattern('ROUND', '/\\( .*? \\)/sx');
        $this->addPattern('CURLY', '/\\{/', '/\\}/');
        $this->addPattern('term', '/-+/m');
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

    abstract public function test();
}

class LanguageTest extends StatefulScannerTest
{
    // this function should simply break exection
    public function override($matches)
    {
        return true;
    }

    private function checkTree()
    {
        $tagged = $this->tagged();
        $expected = '<SQUARE>[square]</SQUARE><ROUND>(round)</ROUND><CURLY>{curly}</CURLY>'
            . '<SQUARE>[square<ROUND>(round)</ROUND>]'
            . '</SQUARE><CURLY>{curly<ROUND>(round)</ROUND>}</CURLY>'
            . '<ROUND>(round{stillround})</ROUND>';
        assert($tagged === $expected);
    }

    public function test()
    {
        $this->init();
        $this->main();
        $this->checkTree();
    }
}

class ApiTest extends StatefulScannerTest
{
    private function getTopToken()
    {
        return $this->tokenTreeStack[count($this->tokenTreeStack) - 1];
    }

    public function test()
    {
        $this->init();
        $this->setup();

        // we're going to test some simple push and pop operations
        assert($this->stateName() === 'initial');
        $this->pushState(array('new state'));
        assert($this->stateName() === 'new state');
        $this->popState();
        assert($this->stateName() === 'initial');

        $this->pushState(array('state'));
        $top = $this->getTopToken();
        assert($top['token_name'] === 'state');
        assert(count($top['children']) === 0);
        // now let's push a complete child token
        $this->recordToken('text', 'child');
        // it should not have changed the top of the stack, because it's
        // a complete token. It's already finalised.
        $top = $this->getTopToken();
        assert($top['token_name'] === 'state');
        // it should be present in the top token's child array
        assert(count($top['children']) === 1);
        // the child token should also have 1 child, which should be its actual
        // text, i.e. a string with the contents 'text'
        assert($top['children'][0]['token_name'] === 'child');
        assert(count($top['children'][0]['children']) === 1);
        assert($top['children'][0]['children'][0] === 'text');

        // now let's pop, and we should be back to the initial state
        $this->popState();
        $top = $this->getTopToken();
        assert($top['token_name'] === 'initial');
        // now let's test that we can't pop the initial state
        $exception = false;
        try {
            $this->popState();
        } catch (Exception $e) {
            $exception = true;
        }
        assert($exception);

        $top = $this->getTopToken();
        // now record some text into the initial state's child array
        $c = count($top['children']);
        $this->record('some text');
        $top = $this->getTopToken();
        $c1 = count($top['children']);
        assert($c + 1 === $c1);
        assert($top['children'][$c1 - 1] === 'some text');
    }
}

$t = new LanguageTest();
$t->test();
$t = new ApiTest();
$t->test();
