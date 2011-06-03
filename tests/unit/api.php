<?php

/*
 * API test - tests the various configuration options
 */

include dirname(__FILE__) . '/helper.inc';

function assert_set($setting, $value) {
  luminous::set($setting, $value);
  $real = luminous::setting($setting);
  if ($real !== $value) {
    echo "Set $setting to $value, but it is $real\n";
    assert(0);
  }
}

function assert_set_exception($setting, $value) {
  $val = luminous::setting($setting);
  $exception = false;
  try {
    luminous::set($setting, $value);
  } catch(Exception $e) {
    $exception = true;
  }
  if (!$exception) {
    echo "set($setting, " . var_export($value, true) . ") failed to throw exception\n";
    assert(0);
  }  
  assert($val === luminous::setting($setting));
}
    

function test_set() {
  // first we'll check legal settings
  $legal_vals = array(
    'auto_link' => array(true, false),
    'cache_age' => array(-1, 0, 1, 200, 100000000),
    'failure_tag' => array(null, '', 'pre', 'table', 'div'),
    'format' => array('html', 'html-full', 'html-inline', 'latex', null, 'none'),
    'html_strict' => array(true, false),
    'include_javascript' => array(true, false),
    'include_jquery' => array(true, false),
    'line_numbers' => array(true, false),
    'max_height' => array(-1, 0, 1, 2, 3, 100, '100', '200px', '250%'),
    'relative_root' => array(null, '', 'xyz', '/path/to/somewhere/'),
    'theme' => luminous::themes(),
    'wrap_width' => array(-1, 0, 2, 3, 100, 10000000, 999999999)
  );

  foreach($legal_vals as $k=>$vs) {
    foreach($vs as $v)
      assert_set($k, $v);
  }

  // now the illegal ones should throw exceptions

   $illegal_vals = array(
    'auto_link' => array(1, 0, 'yes', 'no', null),
    'cache_age' => array(true, false, 1.1, 'all year', null),
    'failure_tag' => array(true, false, array()),
    'format' => array('someformatter', '', true, false, 1, 2, 3),
    'html_strict' => array(1, 0, 'yes', 'no', null, array()),
    'include_javascript' => array(1, 0, 'yes', 'no', null, array()),
    'include_jquery' => array(1, 0, 'yes', 'no', null, array()),
    'line_numbers' => array(1, 0, 'yes', 'no', null, array()),
    'max_height' => array(null, true, false, array()),
    'relative_root' => array(1, 0, true, false, array()),
    'theme' => array('mytheme', null, true, false, 1, array()),
    'wrap_width' => array('wide', 1.5, true, false, null, array())
  );

  foreach($illegal_vals as $k=>$vs) {
    foreach($vs as $v)
      assert_set_exception($k, $v);
  }

  // finally, we're going to use the old fashioned array indices and check that
  // they still correspond to the new keys. The old fashioned way used dashes
  // to separate words in the array. For impl. reasons we had to switch these
  // to underscores, but they should be aliases of each other as far as the
  // API is concerned.
  
  foreach($legal_vals as $k=>$vs) {
    foreach($vs as $v) {
      $k_old = str_replace('_', '-', $k);
      luminous::set($k, $v);
      assert(luminous::setting($k_old) === $v);
      luminous::set($k_old, $v);
      assert(luminous::setting($k) === $v);
    }
  }
}


function assert_formatter_option($setting, $value) {
  // convert API setting name to the property name in the formatter
  $setting_property_map = array(
    'wrap_width' => 'wrap_length',
    'max_height' => 'height',
    'html_strict' => 'strict_standards',
    'auto_link' => 'link',
    'line_numbers' => 'line_numbers',
  );
  luminous::set($setting, $value);
  $formatter = luminous::formatter();
  $mapped = $setting_property_map[$setting];
  $val = $formatter->$mapped;
  if ($val !== $value) {
    echo "formatter->$mapped == {$val}, should be $value\n";
    assert(0);
  }
}

function test_formatter_options() {
  // check that each of the formatter options is applied correctly to the
  // formatter.
  $formatters = array('html', 'html-full', 'html-inline', 'latex', 'none', null);
  foreach($formatters as $f) {
    luminous::set('format', $f);
    assert_formatter_option('wrap_width', 1337);
    assert_formatter_option('wrap_width', -1);
    assert_formatter_option('max_height', 100);
    assert_formatter_option('max_height', '100');
    assert_formatter_option('max_height', '100px');
    assert_formatter_option('max_height', 0);
    assert_formatter_option('max_height', -1);
    assert_formatter_option('line_numbers', false);
    assert_formatter_option('line_numbers', true);
    assert_formatter_option('auto_link', false);
    assert_formatter_option('auto_link', true);
    assert_formatter_option('html_strict', true);
    assert_formatter_option('html_strict', false);
  }
}


$sql_executed = false;
function sql($query) {
  global $sql_executed;
  $sql_executed = true;
  return false;
}
// tests that setting the SQL function results in the SQL backend being used
function test_cache() {
  global $sql_executed;
  $sql_executed = false;
  luminous::set('sql_function', 'sql');
  // this will throw a cache not creatable warning which we don't really care
  // about
  @luminous::highlight('plain', '123', true);
  assert($sql_executed);
}


test_set();
test_formatter_options();
test_cache();