<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
/// this test is broken

require_once(dirname(__FILE__) . '/../../src/luminous.php');

/**
 * iterates over all the source code in the regression testing dir,
 * then passes it through to each scanner
 *
 * The scanner that returns the highest probability SHOULD be the scanner
 * whose language code is the same as the parent directory of the source
 * code.
 *
 * That's the test.
 */

$filenames = glob(realpath(__DIR__ . '/../regression') . '/*/*');
sort($filenames);

foreach($filenames as $filename) {
  if (strpos($filename, '.luminous') !== false) continue;
  if (preg_match('/~$/', $filename)) continue;
  $real_language = basename(dirname($filename));
  $best = array('lang' => '',  'score'=>-1, 'codes' => array(null));
  $real = array('lang' => '', 'score'=>-1, 'codes'=>array(null));
  $text = file_get_contents($filename);
  foreach(luminous::scanners() as $name=>$codes) {
    $scanner = $luminous_->scanners->GetScanner($codes[0]);
    $score = $scanner->guess_language($text) . "\n";
    if ($score > $best['score']) {
      $best['score'] = $score;
      $best['lang'] = $name;
      $best['codes'] = $codes;
    }
    if (in_array($real_language, $codes)) {
      $real['score'] = $score;
      $real['lang'] = $name;
      $real['codes'] = $codes;
    }
  }
  if (!(in_array($real_language, $best['codes']))) {
    echo 'failed for ' . basename($filename) . "\n";
    if (!empty($best['lang'])) {
      echo  'Best choice was ' . $best['lang'] . ' with '
        . $best['score'];
    }
    else {
      echo 'No scanner matched';
    }
    echo "Real scanner should have been " . $real['lang'] . " which "
      . "achieved " . $real['score'];
    echo "\n";
  }
}
