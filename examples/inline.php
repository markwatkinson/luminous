<?php
include 'helper.php';
Luminous::set('format', 'html-inline');
Luminous::set('includeJavascript', false);
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Inline code highlighting with AJAX example</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <?= Luminous::headHtml(); ?>
    </head>
    <body>
        Lorem ipsum dolor sit amet, <?= Luminous::highlight('c', '#include <stdio.h>'); ?> consectetur adipiscing elit.
        Pellentesque <?= Luminous::highlight('c', 'int main()'); ?> orci eros, pellentesque sed elementum eu, mattis
        nec neque. Vestibulum hendrerit leo vel mi tristique mollis. Mauris magna odio, porta ut fringilla iaculis,
        <?= Luminous::highlight('c', 'printf("hello, world!\n");'); ?> placerat eu urna. Vivamus non nisi nec
        <?= Luminous::highlight('c', 'return 0;'); ?> ante euismod vehicula. Curabitur nec enim tortor. Proin viverra
        ligula nec quam pulvinar vehicula. Vivamus turpis diam
    </body>
</html>
