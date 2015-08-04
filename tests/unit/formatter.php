<?php

use Luminous\Formatters\Formatter;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
/*
 * Basic test of LuminousFormatter's methods (it currently only has one)
 */

include __DIR__ . '/helper.inc';

class FormatterTest extends Formatter
{
    public function format($s)
    {
    }
    private function testWrap($line, $length)
    {
        // we should find that after wrapping and stripping tags, no line
        // is longer than length
        $count = $this->wrapLine($line, $length);
        // the last char should be a newline. Let's trim that now
        assert($line[strlen($line) - 1] === "\n");
        $line = substr($line, 0, strlen($line) - 1);
        $lines = explode("\n", $line);
        assert(count($lines) === $count);
        if ($length <= 0) {
            // wrap length of 0 shouldn't perform any wrapping
            assert($count === 1);
        } else {
            $maxlength = -1;
            foreach ($lines as $l) {
                $maxlength = max(strlen(strip_tags($l)), $maxlength);
            }
            assert($maxlength <= $length);
        }
    }
    public function test()
    {
        $this->testWrap('unwrapped string', 0);
        $this->testWrap('unwrapped string', -1);

        $this->testWrap('', 10);
        // 9
        $this->testWrap('012345678', 10);
        // 10
        $this->testWrap('0123456789', 10);
        // 11
        $this->testWrap('01234567890', 10);
        $this->testWrap('01234567890123456789', 10);
        $this->testWrap('01234567890123456789', 1);

        // now let's try some with XML tags.
        $this->testWrap('01234<TAG>56789', 10);
        $this->testWrap('01234<TAG>56789', 1);
        $this->testWrap('01234</TAG>56789', 10);
        $this->testWrap('01234</TAG>56789', 1);
    }
}

$test = new FormatterTest();
$test->test();
