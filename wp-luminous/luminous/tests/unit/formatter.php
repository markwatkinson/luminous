<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
/*
 * Basic test of LuminousFormatter's methods (it currently only has one)
 */

include '../../src/formatters/formatter.class.php';

class FormatterTester extends LuminousFormatter {
  function format($s){}
  private function test_wrap($line, $length) {
    // we should find that after wrapping and stripping tags, no line
    // is longer than length
    $count = $this->wrap_line($line, $length);
    // the last char should be a newline. Let's trim that now
    assert($line[strlen($line)-1] === "\n");
    $line = substr($line, 0, strlen($line)-1);
    $lines = explode("\n", $line);
    assert(count($lines) === $count);
    if ($length <= 0) {
      // wrap length of 0 shouldn't perform any wrapping
      assert($count === 1);
    } else {
      $maxlength = -1;
      foreach($lines as $l) $maxlength = max(strlen(strip_tags($l)), $maxlength);
      assert($maxlength <= $length);
    }
  }
  public function test() {
    $this->test_wrap('unwrapped string', 0);
    $this->test_wrap('unwrapped string', -1);
    
    $this->test_wrap('', 10);
    // 9
    $this->test_wrap('012345678', 10);
    // 10
    $this->test_wrap('0123456789', 10);
    // 11
    $this->test_wrap('01234567890', 10);
    $this->test_wrap('01234567890123456789', 10);
    $this->test_wrap('01234567890123456789', 1);

    // now let's try some with XML tags.
    $this->test_wrap('01234<TAG>56789', 10);
    $this->test_wrap('01234<TAG>56789', 1);
    $this->test_wrap('01234</TAG>56789', 10);
    $this->test_wrap('01234</TAG>56789', 1);
    

  }
}

$test = new FormatterTester();
$test->test();
