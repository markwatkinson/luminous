<?php

include 'helper.inc';

require_once(luminous::root() . '/src/cache/cache.class.php');
require_once(luminous::root() . '/src/cache/fscache.class.php');


// generate something random
$data = '';
for($i=0; $i<1024; $i++) {
  $data .= chr(mt_rand(32, 126));
}
$id = md5($data);
$fs = new LuminousFileSystemCache($id);
$fs->write($data);
$fs1 = new LuminousFileSystemCache($id);
$data1 = $fs->read();

echo $data . "\n\n\n";
echo $data1 . "\n";
assert($data1 === $data);
