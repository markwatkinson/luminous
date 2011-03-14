<?php
include 'helper.inc';
$LUMINOUS_OUTPUT_FORMAT = 'html_inline';
?><!DOCTYPE HTML>

<!DOCTYPE html>
<html>
  <head>
    <title>Inline code highlighting with AJAX example</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php 
    // we don't want the javascript stuff for inline code
    echo luminous_get_html_head(null, false, false, $http_path); 
    ?>
  </head>
  
  <body>
  <p>
  This example shows how to write some short single-line code snippets inside a paragraph, and then use JavaScript and AJAX to highlight it with Luminous. Viewing this page without JavaScript shows an elegant fallback.
  </p> 
  Lorem ipsum dolor sit amet, <?php echo luminous('c', '#include <stdio.h>') ;?> consectetur adipiscing elit. Pellentesque <?php echo luminous('c', 'int main()');?> orci eros, pellentesque sed elementum eu, mattis nec neque. Vestibulum hendrerit leo vel mi tristique mollis. Mauris magna odio, porta ut fringilla iaculis, <?php echo luminous('c', 'printf("hello, world!\n");');?>
  placerat eu urna. Vivamus non nisi nec <?php echo luminous('c', 'return 0;');?> ante euismod vehicula. Curabitur nec enim tortor. Proin viverra ligula nec quam pulvinar vehicula. Vivamus turpis diam
  </body>
</html>