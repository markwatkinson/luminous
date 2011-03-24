#!/usr/bin/php
<?php

/**
 * HTML formatter validation test
 * 
 * Requires http://pear.php.net/package/Services_W3C_HTMLValidator
 * # apt-get install php-pear && pear install Services_W3C_HTMLValidator
 * 
 * Unfortunately that package is pretty buggy and errors quite frequently
 * 
 * Returns:
 *      0: successful validation
 *      1: validation failed
 *      2: Something failed, validation inconclusive (probably network problem)
 *      3: Services_W3C_HTMLValidator is not installed.
 *
 *
 */

define('SUCCESS', 0);
define('FAIL', 1);
define('INCONCLUSIVE', 2);
define('NOT_INSTALLED', 3);


require_once('helpers.inc');
@(include('Services/W3C/HTMLValidator.php')) or exit(NOT_INSTALLED);

$samples = glob("$_DIR/samples/output/*.luminous");

$LUMINOUS_OUTPUT_FORMAT = 'html';



$doctypes = array(
  'html4strict' =>'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" 
                                         "http://www.w3.org/TR/html4/strict.dtd">',

  'html4loose' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
                                         "http://www.w3.org/TR/html4/loose.dtd">',

  'xhtml1.0strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                                               "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',

  'xhtml1.0loose' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
                                               "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
  'xhtml1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
                                        "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
  'html5' => '<!DOCTYPE HTML>'
);

function htmlify($html, $doctype)
{
  $root_tag = '<html>';
  $meta_close = '';
  if (stripos($doctype, 'XHTML 1.0 Strict') !== false)
  {
    $meta_close = '/';
    $root_tag = <<<EOF
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
EOF;
  }
  elseif(stripos($doctype, 'XHTML 1.0 Transitional') !== false)
  {
    $meta_close = '/';
    $root_tag = <<<EOF
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
EOF;
  }
  elseif (stripos($doctype, 'XHTML') !== false)
  {
    $meta_close = '/';
    $root_tag = '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">';
  }


  return <<<EOF
$doctype
  $root_tag
  <head>
    <title>Luminous Test</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" $meta_close>
  </head>
  <body>
  $html
  </body>
</html>
EOF;
}



function validate($html)
{
  $v = new Services_W3C_HTMLValidator();
  $r = $v->validateFragment($html);
  if (!$r)
    return false;
  
  return array(
    'errors'=>$r->errors,
    'warnings'=>$r->warnings
  );
}

function err_handler($errno, $errstr)
{
  global $s, $spec;
  if (!isset($out[$s][$spec]['problems']))
    $out[$s][$spec]['problems'] = array();
  
  $out[$s][$spec]['problems'][] = "$errno: $errstr";
  echo "Caught error: $errno: $errstr\n";  
}


function print_help()
{
  global $doctypes, $argv;
  echo "Usage: " . $argv[0] . " DOCTYPE1 DOCTYPE2\n";
  echo "Where doctypes may be:\n";
  foreach($doctypes as $s=>$d)
    echo "$s\n";
  exit(SUCCESS);
}


$doctypes_ = array();
if (count($argv) < 2)
  print_help();
$inline = false;
$verbose = false;
for ($i=1; $i<count($argv); $i++)
{
  $a = $argv[$i];  
  if ($a == '-i')
    $inline = true;
  elseif ($a == '-v')
    $verbose = true;
  elseif ($a == '--help' || !array_key_exists($a, $doctypes))
    print_help();
  else
    $doctypes_[] = $argv[$i];
}



// set_error_handler('err_handler');

$out = array();
$num_requests = count($samples) * count($doctypes_);
$req = 0;


luminous::set('format', ($inline)?'html_inline' : 'html');

foreach($samples as $s)
{
  $formatter = luminous::formatter();
  $formatter->line_numbers = true;
  $formatter->link = true;
  
  // At the moment, this just stops TARGET attributes on links, which happen
  // to be highly convenient but the w3c in their infinite wisdom decided 
  // were invalid in HTML4 strict.
  $formatter->strict_standards = true;
  
  $src = file_get_contents($s);
  $output = $formatter->Format($src);
  
  $out[$s] = array();
  
  foreach($doctypes_ as $spec)
  {
    $doctype = $doctypes[$spec];
    $html = @htmlify($output, $doctype);
    
    try {
      $return = validate($html);
    } catch (Exception $e) {
      echo "Failed to connect or some other problem\n";
      echo "{$e->message}\n";
      $out[$s][$spec]['problems'] = array();
      $out[$s][$spec]['problems'][] = $e->message;
      continue;
    }
    
    if (isset($out[$s][$spec]['problems']))
      continue;    
    if ($return == false)
    {
      $out[$s][$spec]['problems'] = array('Validator returned false');
      continue;
    }
    $out[$s][$spec] = $return;
    $req++;
    echo '...' . (round($req/$num_requests * 100)) . "%\n";
    if ($req < $num_requests)
      sleep(1);
  }
}
// restore_error_handler();

$out_log = '';
$warn = false;
foreach($out as $f=>$data)
{
  $out_log .= "$f\n";
  foreach($data as $doctype=>$validator_data)
  {
    $errs = isset($validator_data['errors']) ? $validator_data['errors']
            : array();
    $warns = isset($validator_data['warnings'])? $validator_data['warnings']
            : array();
    $problems = isset($validator_data['problems'])? $validator_data['problems']
            : array();
                
    if (empty($errs) && empty($warns) && empty($problems))
      continue;
    
    $out_log .= "  $doctype\n"; 
    
    if (!empty($errs))
    {
      $out_log .= "    Errors:\n";
      foreach($errs as $e)
      {
        $EXIT_STATUS = FAIL;
        $out_log .= "      {$e->message}\n";
      }
    }
    
    if (!empty($warns))
    {
      $out_log .= "    Warnings:\n";
      foreach($warns as $w)
      {
        // disable warnings here, they seem to be false positives.
//         $warn = true;
        $out_log .= "      {$w->message}\n";
      }
    }
    if (!empty($problems))
    {
      $out_log .= "    Possible problems:\n";
      foreach($problems as $p)
      {
        $warn = true;
        $out_log .= "      $p\n";
      }
    }
    
  }
}

file_put_contents("$LOG_DIR/formatter_html", $out_log);
if ($verbose)
  echo "$out_log\n";

if ($EXIT_STATUS == SUCCESS && $warn)
  $EXIT_STATUS = INCONCLUSIVE;
  
exit($EXIT_STATUS);
