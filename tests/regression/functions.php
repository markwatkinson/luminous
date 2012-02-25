<?php
$lroot = dirname(__FILE__) . '/../../';
$path = dirname(__FILE__);

$lpath = realpath($lroot . '/src/luminous.php');
require ($lpath);

luminous::set('format', null);
$output_ext = '.luminous';


$missing_output = array();
$diff_output = array();



$default_target = scandir($path);
foreach($default_target as $k=>$v)
{
  if (!is_dir($v) || $v[0] == '.')
    unset($default_target[$k]);
}

function ext($path)
{
  return preg_replace('/.*(?=\.)/', '', $path);
}

function highlight_luminous($path)
{
  $lang = trim(substr(ext($path), 1));
  if ($lang === '')
    return;
  
  $src = file_get_contents($path);
  return luminous::highlight($lang, $src, false);
}


function generate($path)
{
  global $output_ext;  
  $out = highlight_luminous($path);
  if ($output_ext === '._html.luminous')
  {
    $out = luminous::head_html() . $out;
    $out = '<meta http-equiv="Content-Type" content="text/html; 
            charset=utf-8">' . $out;
  }
  file_put_contents($path . $output_ext, $out);
}

function compare($path)
{
  global $output_ext;
  global $diff_output;
  global $missing_output;
  $expected_path = $path.$output_ext;
  if (!is_file($expected_path))
  {
    $missing_output[] = $path;
    return;
  }
  
  $expected = file_get_contents($path . $output_ext);
  $actual = highlight_luminous($path);
  
  $expected = str_replace("\r\n", "\n", $expected);
  $expected = str_replace("\r", "\n", $expected);
  
  $actual = str_replace("\r\n", "\n", $actual);
  $actual = str_replace("\r", "\n", $actual);
  
  if ($expected == $actual)
    return;
  
  // we have to do it like this as opposed to clever piping or it seems to 
  // break on long strings
  $outpath = "$path._diff$output_ext";
  $temppath = "$path.actual$output_ext";
  file_put_contents($temppath, $actual);
  exec("diff -u $path$output_ext $temppath > $outpath", $x, $ret);
  if ($ret) exit(1);  
  unlink($temppath);
  $diff_output[$path] = $outpath;
  
}

function clean($path)
{
  if (preg_match('/\.luminous$/', $path))
    return;
  if (file_exists("$path._diff.luminous"))
    unlink("$path._diff.luminous");
  if (file_exists("$path._html.luminous"))
    unlink("$path._html.luminous");
}

function recurse($path, $action)
{
  global $output_ext;
  if (!in_array($action,
    array('compare', 'generate', 'clean')))
  {
    echo "Unknown action: $action\n";
    exit(1);
  }
  
  $files = array();
  if (is_dir($path))
    $files = scandir($path);
  elseif(is_file($path))
  {
    $files[]= basename($path);
    $path = dirname($path);
  }
  
  
  foreach($files as $f)
  {
    if (empty($f) || $f[0] == '.' || preg_match('/~$/', $f))
      continue;
    
    $f = preg_replace('%//+%', '/', $path . '/' . $f);
    
    if (is_dir($f))
      recurse($f, $action);    
    else
    {
      if ($action === 'clean')
      {
        clean($f);
        continue;
      }
      
      $ext = ext($f);
      if (!empty($ext) && $ext !== '.luminous')
      {
        echo "$f ...\n";
        if ($action == 'compare')
          compare($f);
        elseif ($action == 'generate')
          generate($f);
      }
    }
  }
}

