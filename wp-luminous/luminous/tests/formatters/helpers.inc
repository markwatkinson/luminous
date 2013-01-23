<?php
 
require_once dirname(__FILE__) . '/../../src/luminous.php';

$_DIR = realpath(dirname(__FILE__));
$LOG_DIR = "$_DIR/../log/";
$REGRESSIONS_DIR = "$_DIR/../regressions/";

$EXIT_STATUS = 0;
