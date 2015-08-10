<?php
error_reporting(E_ALL | E_STRICT);
assert_options(ASSERT_BAIL, 1);

$luminousRoot = dirname(__DIR__);
if (file_exists($luminousRoot . '/vendor/autoload.php')) {
    // standalone install
    require_once($luminousRoot . '/vendor/autoload.php');
} elseif (file_exists($luminousRoot . '/../../autoload.php')) {
    // dep install
    require_once($luminousRoot . '/../../autoload.php');
} else {
    die('Please install the Composer autoloader by running `composer install` from within ' . $luminousRoot . PHP_EOL);
}


if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

Luminous::set('max-height', 300);
Luminous::set('theme', 'geonyx');
Luminous::set('relative-root', '../');
Luminous::set('include-jquery', true);
Luminous::set('include-javascript', true);

if (isset($_POST['theme'])) Luminous::set('theme', $_POST['theme']);
if (isset($_POST['format'])) Luminous::set('format', $_POST['format']);
$line_numbers = true;
if (!empty($_POST) && !isset($_POST['line-numbers']))
  $line_numbers = false;
$line_numbers_start = false;
if (!empty($_POST) && isset($_POST['line-numbers-start'])) {
  $line_numbers_start = (int)$_POST['line-numbers-start'];
  if ($line_numbers_start > 0)
    Luminous::set('start-line', $line_numbers_start);
}

Luminous::set('line-numbers', $line_numbers);
?>
<!DOCTYPE html>
<html>
  <head>
  <?php echo Luminous::headHtml(); ?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

  </head>
<body>
  <?php
if (count($_POST)) {
  $lang = $_POST['lang'];
  if ($lang === 'guess') {
    $s = microtime(true);
    $guesses = luminous::guessLanguageFull($_POST['src']);
    $e = microtime(true);
    $lang = luminous::guessLanguage($_POST['src']);
    $printable_guesses = array();
    foreach($guesses as $g) {
      $printable_guesses[$g['language']] = $g['p'];
    }
    echo sprintf('Language guessed in %f seconds:', $e-$s);
    echo '<pre>';
    print_r($printable_guesses);
    echo '</pre>';
  }
  // else
  {
    $t = microtime(true);
    $out = luminous::highlight($lang, $_POST['src'], false);
    $t1 = microtime(true);
    if ($e = luminous::cacheErrors()) {
      echo '<pre>';
      echo implode("<br/>", $e);
      echo '</pre>';
    }
    echo ($t1-$t) . 'seconds <br>';
    echo strlen($_POST['src']) . '<br>';
    echo $out;
  }
}
?>
  <div style='text-align:center'>
    <form method='post' action='interface.php'>
    <select name='lang'>
    <option value='guess'>Guess Language</option>
    <?php foreach(luminous::scanners() as $lang=>$codes) {
      $def = (isset($_POST['lang']) && $_POST['lang'] === $codes[0])?
        ' selected' : '';
      echo "<option value='{$codes[0]}'$def>$lang</option>\n";
    } ?>
    <option value='no_such_scanner'>error case</option>
    </select>
    <br/>
    <select name='theme'>
    <?php foreach(luminous::themes() as $t) {
      $def = (isset($_POST['theme']) && $_POST['theme'] === $t)? ' selected':
          '';
      echo sprintf("<option value='%s'%s>%s</option>\n", $t, $def,
        preg_replace('/\.css$/i', '', $t));
    }
?>  </select>
    <br/>
    <select name='format'>
    <?php foreach(array('html', 'html-full', 'latex') as $f) {
      $def = (isset($_POST['format']) && $_POST['format'] === $f)? ' selected':
          '';
      echo sprintf("<option value='%s'%s>%s</option>\n", $f, $def, $f);

    }
?>  </select>
    <br/>
    <label>Line numbers
      <input type='checkbox' name='line-numbers'<?= $line_numbers? ' checked' : ''?>>
    </label>
    <br/>
    <label>First line number
      <input type='number' name='line-numbers-start' value='<?=
        (int)$line_numbers_start > 0? $line_numbers_start : 1?>'
        min=1 step=1>
    </label>
    <br/>
    <textarea rows=15 cols=75 name='src'><?php
    if (isset($_POST['src'])) echo htmlspecialchars($_POST['src']);
    ?></textarea>
    <br/>
    <input type=submit>
    </form>
  </div>
</body>
</html>
