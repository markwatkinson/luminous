<?php

include 'helper.inc';


class tester extends LuminousStatefulScanner {

  public function __construct() {
    parent::__construct();
    $this->add_pattern('SQUARE', '/\\[/', '/\\]/');
    $this->add_pattern('ROUND', '/\\(/', '/\\)/');
    $this->add_pattern('CURLY', '/\\{/', '/\\}/');

    $this->transitions = array(
      'SQUARE' => array('SQUARE', 'ROUND', 'CURLY'),
      'ROUND' => array('SQUARE', 'ROUND', 'CURLY'),
      'CURLY' => array('SQUARE', 'ROUND', 'CURLY'),
    );
  }
}

$t = new tester();
$s = 'outside
[square]
(round)
{curly}

[sq(round)are]
[sq [u] [a] [r[e]] ]
out
';
$t->string($s);
$out = $t->main();
print_r($out);
echo $t->tagged();
