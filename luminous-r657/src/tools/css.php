<?php

header('Content-type: text/css');

include 'cssconverter.php';

if (isset($_GET['style']))
{
  $t = luminous_get_theme($_GET['style']);
  $c = new CSSConverter();
  $c->verbose = false;
  
  $c->Convert($t);
  print_r($c->GetRules());

}
