<?php
error_reporting(E_ALL | E_STRICT);
assert_options(ASSERT_BAIL, 1);
require_once('../src/luminous.php');


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

luminous::set('max-height', 300);
luminous::set('theme', 'geonyx');
luminous::set('relative-root', '../');
luminous::set('include-jquery', true);
luminous::set('include-javascript', true);

if (isset($_POST['theme'])) luminous::set('theme', $_POST['theme']);
if (isset($_POST['format'])) luminous::set('format', $_POST['format']); 
$line_numbers = true;
if (!empty($_POST) && !isset($_POST['line-numbers'])) 
  $line_numbers = false;
$line_numbers_start = false;
if (!empty($_POST) && isset($_POST['line-numbers-start'])) {
  $line_numbers_start = (int)$_POST['line-numbers-start'];
  if ($line_numbers_start > 0)
    luminous::set('start-line', $line_numbers_start);
}

luminous::set('line-numbers', $line_numbers);
?>
<!DOCTYPE html>
<html>
  <head>
  <?php echo luminous::head_html(); ?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

  </head>
<body>
  <?php
if (count($_POST)) {
  $lang = $_POST['lang'];
  if ($lang === 'guess') {
    $s = microtime(true);
    $guesses = luminous::guess_language_full($_POST['src']);
    $e = microtime(true);
    $lang = luminous::guess_language($_POST['src']);
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
    if ($e = luminous::cache_errors()) {
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
