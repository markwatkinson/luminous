<?php
$luminous_root = realpath(dirname(__FILE__)  . '/../..');
require_once ($luminous_root . '/src/luminous.php');


function assert_fail($file, $line, $msg) {
  echo "Assertion failure in $file:$line\n$msg\n";
  debug_print_backtrace();
  exit(1);
}
assert_options(ASSERT_BAIL, 1);
assert_options(ASSERT_CALLBACK, 'assert_fail');


