<?php

/*
 * Options test 
 * 
 * We already have pretty good coverage of the options class via the 
 * API unit test, but this is a little more direct for stuff that doesn't 
 * currently address
 */

include dirname(__FILE__) . '/helper.inc';

    
$data = array(
    'auto_link' => true, 
    'cache_age' => 123456,
    'format' => 'latex',
    'html_strict' => true
);
$o = new LuminousOptions($data);

foreach($data as $opt => $val) {
    if ($o->$opt !== $val) {
       echo "Options: o->$opt !== $val\n";
       assert(0);
    }
}
