<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
/**
 * 'Intelligent' fuzz test.
 * Works by manipulating existing sources on a random basis, so the result is
 * something that looks like source code but with a high frequency of errors.
 */
include 'helper.inc';
test(true);
