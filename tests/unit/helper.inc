<?php
$luminousRoot = dirname(dirname(__DIR__));
if (file_exists($luminousRoot . '/vendor/autoload.php')) {
    // standalone install
    require_once($luminousRoot . '/vendor/autoload.php');
} elseif (file_exists($luminousRoot . '/../../autoload.php')) {
    // dep install
    require_once($luminousRoot . '/../../autoload.php');
} else {
    die('Please install the Composer autoloader by running `composer install` from within ' . $luminousRoot . PHP_EOL);
}

function assert_fail($file, $line, $msg) {
    echo "Assertion failure in $file:$line\n$msg\n";
    debug_print_backtrace();
    exit(1);
}
assert_options(ASSERT_BAIL, 1);
assert_options(ASSERT_CALLBACK, 'assert_fail');
