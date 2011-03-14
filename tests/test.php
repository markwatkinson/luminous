<?php

require_once('../scanner.class.php');
require_once('../languages/js.php');
require_once('../languages/css.php');
require_once('../languages/html.php');
require_once('../languages/php.php');


require_once '../luminous-r657/src/formatters/luminous_formatter.class.php';
require_once '../luminous-r657/src/formatters/htmlformatter.class.php';

$tests = array(
  'js' => 'testdata/jquery-1.4.4.js',
  'jsdense' => 'testdata/jquery-1.4.4.min.js',
  'jshtml' => 'testdata/jshtml.html',
  'phpjshtml' => 'testdata/jshtml.php',  
  'css' => 'testdata/luminous.css',
  'zen' => 'testdata/zenophilia.css',
  'html' => 'testdata/test.html',
  'php' => __FILE__,
  'php1' => 'testdata/luminous.class.php',
  'phpcss' => 'testdata/phpcss.php',
  
  'xml' => 'testdata/xml.xml',
);

$scanners = array(
  'js' => 'JSScanner',
  'css' => 'CSSScanner',
  'html' => 'HTMLScanner',
  'xml' => 'HTMLScanner',
  'php' => 'PHPScanner'
);

$formatter = new LuminousFormatterHTML();
$formatter->wrap_length = -1;
if (!isset($_GET['lang'])) die('set ?lang=');
$lang = $_GET['lang'];
$test = isset($_GET['test'])? $_GET['test'] : $_GET['lang'];
$src = file_get_contents($tests[$test]);
$scanner = new $scanners[$lang]($src);

$t = microtime(true);
$scanner->main($src);
$tagstr = $scanner->tagged();
$t1 = microtime(true);
$fmted = $formatter->format($tagstr);
?>
<!DOCTYPE html>
<html>
<head>
<link rel=stylesheet href=/luminous-exp/luminous-r657/style/luminous.css>
<link rel=stylesheet href=/luminous-exp/luminous-r657/style/luminous_light.css>
</head>
<body>
<?
$total = $t1 - $t;
echo $total . '<br>' . (strlen($src)/$total/1024) . 'KiB/s';
echo $fmted;
?>
</body>
</html>
