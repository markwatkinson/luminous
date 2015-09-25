<?php
// very easy, set the format to html-full
require_once('helper.php');
Luminous::set('format', 'html-full');
echo luminous::highlightFile('php', 'themeswitcher.php', $useCache);
