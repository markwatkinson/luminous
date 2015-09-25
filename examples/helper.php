<?php
$luminousRoot = dirname(__DIR__);
if (file_exists($luminousRoot . '/vendor/autoload.php')) {
    // standalone install
    require_once($luminousRoot . '/vendor/autoload.php');
} elseif (file_exists($luminousRoot . '/../../autoload.php')) {
    // dep install
    require_once($luminousRoot . '/../../autoload.php');
} else {
    die('Please install the Composer autoloader by running `composer install` from within ' . $luminousRoot . PHP_EOL);
}
set_time_limit(2);

// This var is because on my dev machine I symlink some directories and
// from that, PHP/Luminous cannot figure out where it is relative to the
// document root.
$httpPath = '../';

$useCache = false;

Luminous::set('relative-root', $httpPath);
Luminous::set('include-jquery', true);
