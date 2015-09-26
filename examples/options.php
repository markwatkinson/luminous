<?php
include __DIR__ . '/helper.php';
$code = <<<EOF
// here's some jquery - http://www.jquery.com
$('a').click(function() {
    return false;
});
EOF;
$language = 'js';
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Setting options</title>
        <?= Luminous::headHtml() ?>
    </head>
    <body>
        <p>
            There are two ways you can set options: globally via the set() method, and per-call in the highlight()
            method. Let's disable auto-linking and make all highlights begin at line 17 via the global call.
            <?php
            // NOTE: this is equivalent to calling luminous::set(array('auto-link' => false, 'start-line' => 17));
            Luminous::set('autoLink', false);
            Luminous::set('startLine', 17);
            echo Luminous::highlight($language, $code);
            ?>
        <p> Now let's override both of those for the duration of the next call
            <?= Luminous::highlight($language, $code, array('autoLink' => true, 'startLine' => 1)) ?>
        <p> When we next call highlight(), the options will be back to their global states:
            <?= Luminous::highlight($language, $code); ?>
        <p> We can get the current value for an option by calling setting(): auto-link is:
            <?= var_dump(Luminous::setting('autoLink')) ?>.
    </body>
</html>
