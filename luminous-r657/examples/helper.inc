<?php
require_once('../luminous.php');
set_time_limit(2);

// This var is because on my dev machine I symlink some directories and 
// from that, PHP/Luminous cannot figure out where it is relative to the 
// document root. 
// $http_path = '/code/luminous';
$http_path = null;

$use_cache = false;