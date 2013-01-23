#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
include 'functions.php';

if ($argc == 1)
  echo "Usage : {$argv[0]} [options] FILE ...\n";

$targets = array();

for ($i=1; $i<$argc; $i++)
{
  $x = $argv[$i];
  if ($x == '-html')
  {
    $output_ext = '._html.luminous';
    luminous::set('format', 'html');
    luminous::set('max-height', -1);
  }
  else
    $targets[] = $argv[$i];
}

if (empty($targets))
  $targets = $default_target;

foreach($targets as $t)
  recurse($t, 'generate');
