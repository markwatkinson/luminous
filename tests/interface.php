<?php

require_once('../src/luminous.php');

Luminous::set('max-height', 300);
//Luminous::set('theme', 'zenophilia');
?>
<!DOCTYPE html>
<html>
  <head>
  <?php echo Luminous::head_html(false, false, '/luminous-exp/'); ?>

  </head>
<body>
  <?php
    if (count($_POST)) {
      // turn off caching for the moment or it's hard to see what changes
      // are having effect
      echo Luminous::highlight($_POST['lang'], $_POST['src'], false);
    }
    ?>
  <div style='text-align:center'>
    <form method='post' action='interface.php'>
    <select name='lang'>
    <?php foreach(Luminous::get_scanners() as $lang=>$codes) {
      $def = (isset($_POST['lang']) && $_POST['lang'] === $codes[0])?
        ' selected' : '';
      echo $_POST['lang'];
      echo "<option value='{$codes[0]}'$def>$lang</option>\n";
    } ?>
    </select>
    <br/>

    <textarea rows=15 cols=75 name='src'><?php
    if (isset($_POST['src'])) echo htmlentities($_POST['src']);
    ?></textarea>
    <br/>
    <input type=submit>
    </form>
  </div>
</body>
</html>