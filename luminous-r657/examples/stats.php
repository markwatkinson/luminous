<?php
// in case you care about the highlighting info
require_once('helper.inc');
$LUMINOUS_WIDGET_HEIGHT = 0;
$output = luminous_file('php', __FILE__, $use_cache);
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Stats</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php echo luminous_get_html_head(null, true, true, $http_path); ?>
    <style type='text/css'>
    h1 { text-align:center;}
    table {border-collapse:collapse;}
    td {border: 1px solid #ddd; }
    </style>
  </head>  
  
  <body>
  <h1> Stats </h1>  
  <table style='margin-left:auto; margin-right:auto'>
  <?php
    $performance = $LUMINOUS_PERFORMANCE_LOG[0];
    $throughput = round($performance['input_size'] / $performance['time']);
    $cached = $LUMINOUS_WAS_CACHED? 'true' : 'false';
    echo <<<EOF
<tr><td>Version: </td><td>$LUMINOUS_VERSION</td></tr>
<tr><td>Language: </td><td>{$performance['language']}</td></tr>
<tr><td>Input size (bytes): </td><td>{$performance['input_size']}</td></tr>
<tr><td>Output size (bytes): </td><td>{$performance['output_size']}</td></tr>
<tr><td>Read from cache? </td><td>{$cached}</td></tr>
<tr><td>Highlight time (s): </td><td>{$performance['parse_time']}</td></tr>
<tr><td>Format time (s): </td><td>{$performance['format_time']}</td></tr>
<tr><td>Total time (s): </td><td>{$performance['time']}</td></tr>
<tr><td>Throughput (bytes/s): </td><td>{$throughput}</td></tr>
EOF;
  ?>  
  </table>
  
  <h1> Source </h1>
  <?php echo $output; ?>
  
  </body>
</html>
