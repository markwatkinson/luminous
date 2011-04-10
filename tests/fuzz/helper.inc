<?php
require_once(dirname(__FILE__) . '/../../luminous.php');
require_once(dirname(__FILE__) . '/../extern/php-diff/lib/Diff.php');
require_once(dirname(__FILE__) . '/../extern/php-diff/lib/Diff/Renderer/Text/Unified.php');


set_time_limit(60*15);
register_shutdown_function('timeout');
$symbols = 'abcdefghijklmnopqrstuvwxyz1234567890!"$%^&*()-_=+#~[]{};:\'@,./<>?` ' . "\t\n\r";

$src = null;
$scanner_code = null;
$clean_exit = false;

function timeout() {
  global $src, $scanner_code, $clean_exit;
  exit(0);
  echo <<<EOF
Fuzz tester hit the time limit. This probably indicates an infinite loop
Scanner: $scanner_code
Source: $src
End
EOF;
  exit(1);
}

function diff($a, $b) {
  $d = new Diff(explode("\n", $a), explode("\n", $b));
  return $d->Render(new Diff_Renderer_Text_Unified());
}

/// Generates a totally random source
function random_source($size=102400) {
  global $symbols;
  $s = str_split($symbols);
  $src = "";
  for ($i=0; $i<$size; $i++)
    $src .= $s[rand(0, count($s)-1)];
  return $src;
}

/**
 * Randomly mutates a source
 */
function randomise_source($source) {
  global $symbols;
  $s = str_split($symbols);
  for ($i=0; $i<strlen($source); $i++) {
    if (rand() % 5 === 0) {
      $source[$i] =  $s[rand(0, count($s)-1)];
    }
  }
  
  return $source;
}


/**
 * returns a randomish source. The source is taken from the $language directory
 * of the regression tests, and it is randomly mutated
 * If no sources are available, return false
 */
function randomish_source($languages) {
  // path to regressions
  $path = realpath(dirname(__FILE__) . '/../regression/');
  $language = false;
  foreach($languages as $l) {
    if (is_dir($path . '/' . $l)) $language = $l;
  }
  if (!$language) return false;

  $candidates = glob($path . '/' . $language . '/*');
  foreach($candidates as $i=>$c) {
    if (preg_match('/(\\.luminous|~)$/', $c)) unset($candidates[$i]);
  }
  if (empty($candidates)) return false;
  $candidates = array_values($candidates);
  $src = $candidates[rand(0, count($candidates)-1)];
  return randomise_source(file_get_contents($src));
}


function error($language, $exception, $input, $output) {
  echo 'Fuzz failed for language: ' . $language . "\n";
  if ($exception) echo 'Exception thrown: ' . $exception . "\n";
  else if (strlen($input) !== strlen($output)) {
    $diff = strlen($input) - strlen($output);
    echo sprintf("diff strlen: scanner has %s %d bytes of data\n",
      ($diff > 0)? 'lost' : 'gained', abs($diff));
  }
  echo 'Data dump follows, delimited by three brackets' . "\n";
  echo "IN : ((($input)))\n";
  if (!$exception) {
    echo "OUT: ((($output)))\n";
    echo "\nDiff:\n";
    echo diff($input, $output);
  }
  echo "\nEnd fuzz dump\n";
  exit(1);
}

function test($randomish=false) {
  global $luminous_, $src, $scanner_code, $clean_exit;
  $scanners = luminous::scanners();
  $func = $randomish? 'randomish_source' : 'random_source';

  foreach($scanners as $l=>$language) {
    $src = '';
    if ($randomish) {
      $src = randomish_source($language);
    }
    else $src = random_source(1024*100);

    if ($src === false) {
      echo 'No sources for ' . $l . "\n";
      continue;
    }
    $scanner_code = $language[0];
    $scanner = $luminous_->scanners->GetScanner($scanner_code);

    // take this source because it has line endings normalised.
    $src1 = $scanner->string($src);
    $exception = false;
    try {
      $out = $scanner->highlight($src1);
    } catch (Exception $e) {
      $exception = $e->getMessage();
    }
    $out1 = html_entity_decode(strip_tags($out));
    if ($exception ||$out1 !== $src1) {
      error($language[0], $exception, $src1, $out1);
    }
  }
  $clean_exit = true;
}