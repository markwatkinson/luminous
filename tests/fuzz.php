#!/usr/bin/php
<?php

require_once(dirname(__FILE__) . '/../luminous.php');

$time_limit;


$done = false;

$EXIT_STATUS = 0;

$src = "";
$lang = "";

function random_source($size=102400) {
  $symbols = 'abcdefghijklmnopqrstuvwxyz1234567890!"$%^&*()-_=+#~[]{};:\'@,./<>?` ' . "\t\n\r";
  $s = str_split($symbols);
  $src = "";
  for ($i=0; $i<$size; $i++)
    $src .= $s[rand(0, count($s)-1)];
  return $src;
}

$scanners = Luminous::scanners();


  foreach($scanners as $language) {
    $src = random_source(102400);
    $scanner = $luminous_->scanners->GetScanner($language[0]);

    // take this source because it has line endings normalised.
    $src1 = $scanner->string($src);
    $out = $scanner->highlight($src1);
    $out1 = html_entity_decode(strip_tags($out));
    if ($out1 !== $src1) {
      echo $language[0] . "<br>\n";

      echo "IN:  $src1\n";
      echo "OUT: $out1\n";

      echo "\n\n";
      if (strlen($src1) !== strlen($out1)) {
        echo sprintf('diff strlen: scanner has %s data', ($src1>$out1)?'lost' : 'gained');
      }
      die();
    }
  }