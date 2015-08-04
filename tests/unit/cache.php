<?php

use Luminous\Caches\FileSystemCache;

if (php_sapi_name() !== 'cli') {
    die('This must be run from the command line');
}
include 'helper.inc';

// generate something random
$data = '';
for ($i=0; $i<1024; $i++) {
    $data .= chr(mt_rand(32, 126));
}
$id = md5($data);
$fs = new FileSystemCache($id);
$fs->write($data);
$fs1 = new FileSystemCache($id);
$data1 = $fs->read();

echo $data . "\n\n\n";
echo $data1 . "\n";
assert($data1 === $data);
