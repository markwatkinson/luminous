<?php
if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
/*
 * Very simple check to ensure that each scanner is okay, and does not
 * contain any syntax errors which prevent PHP compilation, or any errors
 * which are trivially possible to reproduce.
 *
 * Scanners are included lazily, so it's possible that a syntax error could
 * go undetected for some time
 */

require 'helper.inc';
error_reporting(E_ALL | E_STRICT);

foreach (Luminous::scanners() as $codes) {
    Luminous::highlight($codes[0], ' ');
}
