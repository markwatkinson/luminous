<?php
require_once(dirname(__FILE__) . '/markuplite.class.php');
require_once(dirname(__FILE__) . '/../../src/luminous.php');

class Markup {

  public $linker = null;
  private $obj = null;
  
  function linker($url) {
    if (preg_match('%^.*://|^www\d*\.%i', $url)) return false;
    return array('uri' => site_url($url));
  }

  public function __construct() {
    $this->linker = array($this, 'linker');
  }
  
  function format($str) {
    $m = new MarkupLite();
    $m->linkifier_cb = $this->linker;
    $m->highlight_cb = create_function('$code,$lang', 
      'return luminous::highlight($lang, $code);');
    return $m->Format($str);
  }
}
$m = new Markup();
$in = '';
while(($line = fgets(STDIN)) !== false) $in .= $line;
echo $m->format($in);