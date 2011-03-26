<?php
// Example of having the output displayed like a full page.
require_once('helper.inc');
luminous::set('max-height', 0);
luminous::set('include-javascript', false);
?>
<!DOCTYPE HTML>
<html>
  <head>
    <title>Full page example</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php echo luminous::head_html(); ?>
    <style>
    body { margin:0;}
    </style>
  </head>
  
  <body>
  <?php 
  // we'll cheat for source code and use the themeswitcher example's source
  echo luminous::highlight_file('php', 'themeswitcher.php', $use_cache); ?>
  </body>
</html>