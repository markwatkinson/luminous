#!/usr/bin/php
<?php

/**
 * Raw formatter test.
 * Luminous raw output is pretty much XML, so we test it's properly formed
 * by making it proper XML (a very simple transformation), and then 
 * we try loading it into an XML parser which is error intolerant.
 */

require_once('helpers.inc');


$samples = glob('./samples/src/*');

luminous::set('format', null);

libxml_use_internal_errors(true);

$errors = array();


foreach($samples as $s)
{
  $language = substr($s, strrpos($s, '.')+1);
  $src = file_get_contents($s);
  
  $out = luminous::highlight($language, $src, false);
  $out = <<<EOF
<?xml version='1.0' encoding='UTF-8'?>
<LUMINOUS>
$out
</LUMINOUS>
EOF;

  $xml = simplexml_load_string($out);
  if ($xml === false)
  {
    $errors[$s] = array();
    foreach (libxml_get_errors() as $error) 
      $errors[$s][] = $error->message;
  } 
  libxml_clear_errors();
}


$EXIT_STATUS = (int)!empty($errors);
if ($EXIT_STATUS)
{
  $output = '';
  foreach($errors as $fn=>$errs)
  {
    $output .= "File: $fn:\n";
    foreach($errs as $e)
      $output .= "  $e\n";
  }
  file_put_contents("$LOG_DIR/formatter_raw", $output);
}

exit($EXIT_STATUS);

      