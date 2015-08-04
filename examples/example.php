<?php

/**
 * \file example.php
 * \brief A short example for calling Luminous
 */ 

require_once('helper.inc');


  
// Luminous shouldn't ever get caught in an infinite loop even on the most
// terrible and malformed input, but I think you'd be daft to run it with no
// safeguard. Also, if you allow your users to give it arbitrary inputs, large
// inputs are pretty much asking for a denial of service attack. Ideally you 
// would enforce your own byte-limit, but I think a time limit is also sensible.
set_time_limit(3);
  
$use_cache = !isset($_GET['nocache'])

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
                        "http://www.w3.org/TR/html4/loose.dtd">
<!-- Luminous is HTML4 strict/loose and HTML5 valid //-->
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title> Example </title>

  <?php 
  echo luminous::head_html();
  ?>
  
</head>

<body>

<h1> A simple usage example for Luminous </h1>
<p> Inspect the source code to see what's going on. Look at both the PHP
    code and the HTML markup. </p>

<p>
<?php if (!$use_cache)
  echo "Caching is <strong>disabled</strong>, click <a href='example.php'>here</a> to enable it";
  else
    echo "Caching is <strong>enabled</strong>. If you are seeing errors, you will need to make the directory: "
. realpath(__DIR__  . "/../") . "/cache/, and make it writable to your server if you intend to use the caching system. Click <a href='example.php?nocache'>here</a> to view this page with caching disabled";
?>
</p>

<?php echo luminous::highlight('cpp', <<<EOF
#include <stdio.h>
int main()
{
  printf("hello, world");
  return 0;
}
EOF
, $use_cache); ?>

<p> You can also set specific runtime options in the highlight call (here we set 'max-height' = 250), which will be
forgotten at the next highlight.
<?php
echo luminous::highlight('php', <<<EOF
<?php
/**
 * \\ingroup LuminousUtils
 * \\internal
 * \\brief Decodes a PCRE error code into a string
 * \\param errcode The error code to decode (integer)
 * \\return A string which is simply the name of the constant which matches the
 *      error code (e.g. 'PREG_BACKTRACK_LIMIT_ERROR')
 * 
 * \\todo this should all be namespaced
 */ 
function pcre_error_decode(\$errcode)
{
  switch (\$errcode)
  {
    case PREG_NO_ERROR:
      return 'PREG_NO_ERROR';
    case PREG_INTERNAL_ERROR:
      return 'PREG_INTERNAL_ERROR';
    case PREG_BACKTRACK_LIMIT_ERROR:
      return 'PREG_BACKTRACK_LIMIT_ERROR';
    case PREG_RECURSION_LIMIT_ERROR:
      return 'PREG_RECURSION_LIMIT_ERROR';
    case PREG_BAD_UTF8_ERROR:
      return 'PREG_BAD_UTF8_ERROR';
    case PREG_BAD_UTF8_OFFSET_ERROR:
      return 'PREG_BAD_UTF8_OFFSET_ERROR';
    default:
      return 'Unknown error code';
  }
}
EOF
, array('cache' => $use_cache, 'max-height' => '250'));
  ?>
<p> See:
<?php echo luminous::highlight('php', <<<EOF
<?php
/**
 * \\ingroup LuminousUtils
 * \\internal
 * \\brief Decodes a PCRE error code into a string
 * \\param errcode The error code to decode (integer)
 * \\return A string which is simply the name of the constant which matches the
 *      error code (e.g. 'PREG_BACKTRACK_LIMIT_ERROR')
 * 
 * \\todo this should all be namespaced
 */ 
function pcre_error_decode(\$errcode)
{
  switch (\$errcode)
  {
    case PREG_NO_ERROR:
      return 'PREG_NO_ERROR';
    case PREG_INTERNAL_ERROR:
      return 'PREG_INTERNAL_ERROR';
    case PREG_BACKTRACK_LIMIT_ERROR:
      return 'PREG_BACKTRACK_LIMIT_ERROR';
    case PREG_RECURSION_LIMIT_ERROR:
      return 'PREG_RECURSION_LIMIT_ERROR';
    case PREG_BAD_UTF8_ERROR:
      return 'PREG_BAD_UTF8_ERROR';
    case PREG_BAD_UTF8_OFFSET_ERROR:
      return 'PREG_BAD_UTF8_OFFSET_ERROR';
    default:
      return 'Unknown error code';
  }
}
EOF
, $use_cache); ?>
</body>
</html>
