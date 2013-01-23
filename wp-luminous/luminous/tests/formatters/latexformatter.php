#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') die('This must be run from the command line');
// TODO: this no longer works, rewrite it for the new testing structure.


/**
 * tests the LaTeX formatter by formatting some code as LaTeX
 * then compiling it to pdf.
 * Requires pdflatex and the ability to call programs
 */
 

require_once dirname(__FILE__) . '/../../luminous.php';


$testfiles = glob(dirname(__FILE__) . '/samples/output/*');

$EXIT_STATUS = 0;

luminous::set('format', 'latex');
foreach($testfiles as $t)
{
  $ts = luminous::themes();
  $theme = $ts[array_rand(luminous::themes())];
  $formatter = luminous::formatter();
  $formatter->set_theme(file_get_contents('../../style/' . $theme));
  
  $src = file_get_contents($t);
  
  $t = preg_replace('%.*/%', '', $t);
  $fmt = $formatter->format($src);
  file_put_contents(dirname(__FILE__)  . "/filedump/$t.tex", $fmt);
  chdir('filedump');
  
  system("pdflatex $t.tex >> /dev/null", $i);
  if ($i) {
    echo "latex formatter test failed on file $t, pdflatex exit status: $i\n";
    $EXIT_STATUS = 1;
  }
  chdir(getcwd() . '/../');
}

exit($EXIT_STATUS);
