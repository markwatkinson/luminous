<?php

require_once('../src/utils/cssparser.class.php');


$parser = new LuminousCSSParser();
$css = file_get_contents('../style/luminous_light.css');
print_r($parser->convert($css));
