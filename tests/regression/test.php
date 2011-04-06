#!/usr/bin/php
<?php
include 'functions.php';


$action = 'compare';
$targets = array();

for ($i=1; $i<$argc; $i++)
{
  $x = $argv[$i];
  if ($x == '-clean')
    $action = 'clean';
  else
    $targets[] = $argv[$i];
}

if (empty($targets))
  $targets = $default_target;

foreach($targets as $t)
  recurse($t, $action);

if ($action == 'clean')
  exit(0);

for($i=0; $i<79; $i++)
  echo '-';
echo "\n";
$exit = 0;
foreach($missing_output as $m)
{
  echo "WARNING: Missing expected output for $m\n";
  $exit = 2;
}

if (empty($diff_output))
  echo "No tests failed\n";

foreach($diff_output as $path=>$path1)
{
  echo "FAILURE: $path failed, diff output written to $path1\n";
  $exit = 1;
}


exit($exit);
