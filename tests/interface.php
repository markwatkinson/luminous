<?php

require_once('../src/luminous.php');

Luminous::set('max-height', 300);
?>
<!DOCTYPE html>
<html>
  <head>
    <link rel='stylesheet' href='../style/luminous.css'>
    <link rel='stylesheet' href='../style/luminous_light.css'>
  </head>
<body>
  <?php
    if (count($_POST)) {
      echo Luminous::highlight($_POST['lang'], $_POST['src']);
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