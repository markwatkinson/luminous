<?php

/* 
 * Very simple check to ensure that each scanner is okay, and does not
 * contain any syntax errors which prevent PHP compilation, or any errors 
 * which are trivially possible to reproduce. 
 *
 * Scanners are included lazily, so it's possible that a syntax error could
 * go undetected for some time
 */

require '../luminous.php';
error_reporting(E_ALL | E_STRICT);

foreach(luminous::scanners() as $codes) {
  luminous::highlight($codes[0], '');
}
