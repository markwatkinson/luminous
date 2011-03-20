#!/usr/bin/php
<?php
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
    Luminous::set('format', 'html');
    Luminous::set('max-height', -1);
  }
  else
    $targets[] = $argv[$i];
}

if (empty($targets))
  $targets = $default_target;

foreach($targets as $t)
  recurse($t, 'generate');
