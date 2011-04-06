<?php
include 'helper.inc';
require_once($luminous_root . '/src/utils/cssparser.class.php');

$parser = new LuminousCSSParser();

$css = <<<EOF
.class1 {
  background-color: #bbb;
  /* this will be dropped */
  background-color: rgba(0, 1, 2, 3);  
}

.class1 {
  color: /*blue*/ red /* red will be translated to hex */;
  /* this will be dropped */
  background-color: #bbb0000;
}

/* this will be dropped, we don't consider tags, only classes */
a {
  color: red;
}
EOF;


$parser->convert($css);
assert($parser->value('class1', 'bgcolor') === '#bbbbbb');
assert($parser->value('class1', 'color') === '#ff0000');
assert($parser->value('a', 'color') === null);