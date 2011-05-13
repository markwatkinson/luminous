<?php

/*
 * If we're running in a web environment, this is simply an include
 * file which includes everythign necessary to use Luminous.
 *
 * If we're running in CLI-mode then this handles the CLI interface.
 */

require_once(dirname(__FILE__) . '/src/luminous.php');

if (PHP_SAPI === 'cli') {
  require(dirname(__FILE__) . '/src/cli.php');
  // cli mode
}