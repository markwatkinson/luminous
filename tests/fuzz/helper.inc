<?php
if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
$luminousRoot = dirname(dirname(__DIR__));
if (file_exists($luminousRoot . '/vendor/autoload.php')) {
    // standalone install
    require_once($luminousRoot . '/vendor/autoload.php');
} elseif (file_exists($luminousRoot . '/../../autoload.php')) {
    // dep install
    require_once($luminousRoot . '/../../autoload.php');
} else {
    die('Please install the Composer autoloader by running `composer install` from within ' . $luminousRoot . PHP_EOL);
}
require_once(__DIR__ . '/../extern/php-diff/lib/Diff.php');
require_once(__DIR__ . '/../extern/php-diff/lib/Diff/Renderer/Text/Unified.php');

set_time_limit(60*15);
register_shutdown_function('timeout');
$symbols = 'abcdefghijklmnopqrstuvwxyz1234567890!"$%^&*()-_=+#~[]{};:\'@,./<>?` ' . "\t\n\r";

$src = null;
$scannerCode = null;
$cleanExit = false;

function timeout() {
    global $src, $scannerCode, $cleanExit;
    exit(0);
    echo <<<EOF
Fuzz tester hit the time limit. This probably indicates an infinite loop
Scanner: $scannerCode
Source: $src
End
EOF;
    exit(1);
}

function diff($a, $b)
{
    $d = new Diff(explode("\n", $a), explode("\n", $b));
    return $d->Render(new Diff_Renderer_Text_Unified());
}

/// Generates a totally random source
function randomSource($size = 102400)
{
    global $symbols;
    $s = str_split($symbols);
    $src = "";
    for ($i = 0; $i < $size; $i++) {
        $src .= $s[rand(0, count($s) - 1)];
    }
    return $src;
}

/**
 * Randomly mutates a source
 */
function randomiseSource($source)
{
    global $symbols;
    // a change in php5.4 makes everything bork in this
    // function only with some of the source files. This seems to suppress it.
    // Probably not ideal. FWIW the command line file reading option keeps
    // everything intact when highlighting, so it's not a big deal, I think...
    $source = iconv('utf-8', 'ascii//translit', $source);
    $s = str_split($symbols);
    for ($i = 0; $i < strlen($source); $i++) {
        if (rand() % 5 === 0) {
            $source[$i] =  $s[rand(0, count($s) - 1)];
        }
    }

    return $source;
}

/**
 * returns a randomish source. The source is taken from the $language directory
 * of the regression tests, and it is randomly mutated
 * If no sources are available, return false
 */
function randomishSource($languages)
{
    // path to regressions
    $path = realpath(__DIR__ . '/../regression/');
    $language = false;
    foreach($languages as $l) {
        if (is_dir($path . '/' . $l)) {
            $language = $l;
        }
    }
    if (!$language) {
        return false;
    }

    $candidates = glob($path . '/' . $language . '/*');
    foreach($candidates as $i => $c) {
        if (preg_match('/(\\.luminous|~)$/', $c)) {
            unset($candidates[$i]);
        }
    }
    if (empty($candidates)) {
        return false;
    }
    $candidates = array_values($candidates);
    $src = $candidates[rand(0, count($candidates) - 1)];
    $src = $path . '/actionscript/JPEGEncoder.as';
    return randomiseSource(file_get_contents($src));
}

function error($language, $exception, $input, $output)
{
    echo 'Fuzz failed for language: ' . $language . "\n";
    if ($exception) {
        echo 'Exception thrown: ' . $exception . "\n";
    } elseif (strlen($input) !== strlen($output)) {
        $diff = strlen($input) - strlen($output);
        echo sprintf("diff strlen: scanner has %s %d bytes of data\n", ($diff > 0) ? 'lost' : 'gained', abs($diff));
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

function test($randomish = false)
{
    global $luminous_, $src, $scannerCode, $cleanExit;
    $scanners = Luminous::scanners();
    $func = $randomish ? 'randomishSource' : 'randomSource';

    foreach ($scanners as $l => $language) {
        $src = '';
        if ($randomish) {
            $src = randomishSource($language);
        } else {
            $src = randomSource(1024*100);
        }

        if ($src === false) {
            echo 'No sources for ' . $l . "\n";
            continue;
        }
        $scannerCode = $language[0];
        $scanner = $luminous_->scanners->getScanner($scannerCode);

        // take this source because it has line endings normalised.
        $src1 = $scanner->string($src);
        $exception = false;
        try {
            $out = $scanner->highlight($src1);
        } catch (Exception $e) {
            $exception = $e->getMessage();
            $out = '';
        }
        $out1 = html_entity_decode(strip_tags($out));
        if ($exception || $out1 !== $src1) {
            error($language[0], $exception, $src1, $out1);
        }
    }
    $clean_exit = true;
}
