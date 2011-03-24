<?php

require_once(dirname(__FILE__) . '/cache.class.php');
require_once(dirname(__FILE__) . '/scanners.class.php');
require_once(dirname(__FILE__) . '/formatters/formatter.class.php');

require_once(dirname(__FILE__) . '/core/scanner.class.php');



// This is kind of a pseudo-UI class. It's a singleton which will be
// manipulated by a few procudural functions, for ease of use.
class _Luminous {
  public $version = 'master';
  public $settings = array(
    'cache-age' => -1,
    'wrap-width' => -1,
    'line-numbers' => true,
    'auto-link' => true,
    'max-height' => 500,
    'format' => 'html',
    'theme' => 'luminous_light',
    'html-strict' => false
  );

  public $scanners;

  

  public function __construct() {
    $this->scanners = new LuminousScanners();

    $this->register_default_scanners();
    

  }


  private function register_default_scanners() {
    // we should probably hide this in an include for neatness
    // when it starts growing.
    $language_dir = luminous::root() . '/languages/';
    
    
    // this is a dummy file which includes ECMAScript dependencies in a 
    // non-circular way.
    $this->scanners->AddScanner('ecma-includes', null, null, 
      "$language_dir/include/ecma.php");
      

    $this->scanners->AddScanner(array('as', 'actionscript'),
    'LuminousActionScriptScanner', 'ActionScript', "$language_dir/as.php", 
    'ecma');

    $this->scanners->AddScanner(array('bash', 'sh'),
    'LuminousBashScanner', 'Bash', "$language_dir/bash.php");
    
    $this->scanners->AddScanner(array('c', 'cpp', 'h', 'hpp', 'cxx', 'hxx'),
      'LuminousCppScanner', 'C/C++', "$language_dir/cpp.php");
      
    $this->scanners->AddScanner(array('cs', 'csharp', 'c#'),
      'LuminousCSharpScanner', 'C#', "$language_dir/csharp.php");
      
    $this->scanners->AddScanner('css',
      'LuminousCSSScanner', 'CSS', "$language_dir/css.php");
      
    $this->scanners->AddScanner(array('diff', 'patch'),
      'LuminousDiffScanner', 'Diff', "$language_dir/diff.php");
      
    $this->scanners->AddScanner(array('html', 'htm', 'xml'),
      'LuminousHTMLScanner', 'HTML/XML', "$language_dir/html.php",
      array('js', 'css'));
      
    $this->scanners->AddScanner(array('ecma', 'ecmascript'),
      'LuminousECMAScriptScanner', 'ECMAScript', 
      "$language_dir/ecmascript.php", 'ecma-includes');

    $this->scanners->AddScanner(array('groovy'),
      'LuminousGroovyScanner', 'Groovy',
      "$language_dir/groovy.php");

    $this->scanners->AddScanner('java',
      'LuminousJavaScanner', 'Java', "$language_dir/java.php");
      
    $this->scanners->AddScanner(array('js', 'javascript'),
      'LuminousJavaScriptScanner', 'JavaScript', "$language_dir/javascript.php",
      array('ecma'));
      
    $this->scanners->AddScanner('json',
      'LuminousJSONScanner', 'JSON', "$language_dir/json.php");

    $this->scanners->AddScanner(array('rails','rhtml', 'ror'),
      'LuminousRailsScanner', 'Ruby on Rails',
      "$language_dir/rails.php", array('ruby', 'html'));
      
    $this->scanners->AddScanner(array('ruby','rb'),
      'LuminousRubyScanner', 'Ruby', "$language_dir/ruby.php");

    $this->scanners->AddScanner(array('plain', 'text', 'txt'),
      'LuminousIdentityScanner', 'Plain', "$language_dir/identity.php");
      
    $this->scanners->AddScanner('php',
      'LuminousPHPScanner', 'PHP', "$language_dir/php.php",
      array('html'));
      
    $this->scanners->AddScanner(array('python', 'py'),
      'LuminousPythonScanner', 'Python', "$language_dir/python.php");
      
    $this->scanners->AddScanner(array('sql', 'mysql'),
      'LuminousSQLScanner', 'SQL', "$language_dir/sql.php");
  }

  function get_formatter() {
    $fmt_path = dirname(__FILE__) . '/formatters/';

    switch(strtolower($this->settings['format'])) {
      case 'html' :
        require_once($fmt_path . 'htmlformatter.class.php');
        return new LuminousFormatterHTML();
      case 'html-inline':
        require_once($fmt_path . 'htmlformatter.class.php');
        return new LuminousFormatterHTMLInline();
      case 'latex':
        require_once($fmt_path . 'latexformatter.class.php');
        return new LuminousFormatterLatex();
      case null:
      case 'none':
        require_once($fmt_path . 'identityformatter.class.php');
         return new LuminousIdentityFormatter();
      default:
        throw new Exception('Unknown formatter: ' . $LUMINOUS_OUTPUT_FORMAT);
        return null;
    }
  }

  private function set_formatter_options(&$formatter) {
    $formatter->wrap_length = $this->settings['wrap-width'];
    $formatter->line_numbers = $this->settings['line-numbers'];
    $formatter->link = $this->settings['auto-link'];
    $formatter->height = $this->settings['max-height'];
    $formatter->strict_standards =$this->settings['html-strict'];
  }

  private function cache_id($scanner, $source) {
    $settings = $this->settings;
    ksort($settings);
    $id = md5($source);
    $id = md5($id . serialize($scanner));
    $id = md5($id . serialize($settings));
    return $id;
  }

  

  function highlight($scanner, $source, $use_cache=true) {
    
    if (!($scanner instanceof LuminousScanner)) {
      $code = $scanner;
      $scanner = $this->scanners->GetScanner($code);
      if ($scanner === null) throw new Exception("No known scanner for '$code'");
    }
    $cache_obj = null;
    $out = null;
    if ($use_cache) {
      $cache_id = $this->cache_id($scanner, $source);
      $cache_obj = new LuminousCache($cache_id);
      $cache_obj->purge_older_than = $this->settings['cache-age'];
      $cache_obj->purge();
      $out = $cache_obj->read();
    }
    if ($out === null) {
      $out_raw = $scanner->highlight($source);
      $formatter = $this->get_formatter();
      $this->set_formatter_options($formatter);
      $out = $formatter->format($out_raw);
    }

    if ($use_cache) {
      $cache_obj->write($out);
    }
    return $out;
  }
}


// Here's our singleton.

$luminous_ = new _Luminous();


// here's our 'real' UI class, which uses the above singleton. This is all
// static because these are actually procudural functions, we're using the
// class as a namespace.
abstract class luminous {


  static function highlight($scanner, $source, $cache=true) {
    global $luminous_;
    return $luminous_->highlight($scanner, $source, $cache);
  }

  static function highlight_file($scanner, $file, $cache=true) {
    return self::highlight($scanner, file_get_contents($file), $cache);
  }

  static function register_scanner($language_code, $classname, $readable_language, $path) {
      global $luminous_;
      $luminous_->scanners->AddScanner($language_code, $classname,
        $readable_language, $path);
  }
  
  /**
   * returns what Luminous thinks its location is on the filesystem
   */
  static function root() {
    return realpath(dirname(__FILE__) . '/../');
  }

  /**
   * returns the list of theme files present in style/.
   * Each theme will be a filename, and will end in .css
   */
  static function themes() {
    $themes_uri = self::root() . "/style/";
    $themes = array();
    foreach(glob($themes_uri . "/*.css") as $css) {
      $fn = trim(preg_replace("%.*/%", '', $css));
      switch($fn) {
        // these are special, exclude these
        case 'luminous.css':
        case 'luminous_print.css':
        case 'luminous.min.css':
          continue;
        default:
        $themes[] = $fn;
      }
    }
    return $themes;
  }

  /**
   * returns true if a theme exists in style/, else false
   */
  static function theme_exists($theme) {
    return in_array($theme, self::themes());
  }
  
  /**
   * Returns the content of a theme; this is the actual CSS text. 
   * Use this function for reading themes as it involves security
   * checks against reading arbitrary files
   */
  static function theme($theme) {
    if (self::theme_exists($theme)) 
      return file_get_contents(self::luminous_root() . "/style/" . $theme);
    else
      throw new Exception('No such theme file: ' . $theme);
  }


  

  static function setting($option) {
    global $luminous_;
    if (!array_key_exists($option, $luminous_->settings))
      throw new Exception("Luminous: No such option: $option");
    return $luminous_->settings[$option];
  }

  static function set($option, $value) {
    global $luminous_;
    if (!array_key_exists($option, $luminous_->settings))
      throw new Exception("Luminous: No such option: $option");
    else $luminous_->settings[$option] = $value;
  }

  static function scanners() {
    global $luminous_;
    return $luminous_->scanners->ListScanners();
  }

  /**
   * Returns an instance of a LuminousFormatter according to the current
   * format setting
   */
  static function formatter() {
    global $luminous_;
    return $luminous_->get_formatter();
  }


/**
  *
  * Returns a string representing everything that needs to be printed in
  * the \<head\> section of a website.
  *
  * \param $javascript (boolean) whether or not to use JavaScript
  * \param $jquery (boolean) whether or not to include jQuery: jQuery is
  *    required for javascript to work, but you may include it yourself (if you
  *    need a different version). If so, it must be included before this
  *    function's output is echoed.
  * \param relative_root Optionally, you may specify the path to luminous/
  *    relative to the document root (remember to include the leading slash).
  *    If you don't specify this, Luminous attempts to work it out. See
  *    warning.
  *
  *
  * \warning due to shortcomings in PHP, it's not really possible to figure
  * out the include path if there are symbolic links involved. In this case,
  * you \b must specify relative_root.
  * http://bugs.php.net/46260  (note the workaround there dated December 2010
  *     is inadequate: it only applies to the executing file, not included
  *     files)
  *
  * \since 0.5.0
  *
  * \todo does this path manipulation work on Windows? if not, is there a php
  *   solution?
  */
  static function head_html($js=true, $jquery=false, $relative_root=null) {
    global $luminous_;
    $theme = $luminous_->settings['theme'];
    if (!preg_match('/\.css$/i', $theme)) $theme .= '.css';
    if (!self::theme_exists($theme)) $theme = 'luminous_light.css';
    
    if ($relative_root === null) {
      $relative_root = str_replace($_SERVER['DOCUMENT_ROOT'], '/',
        dirname(__FILE__));
      $relative_root = str_replace('\\', '/', $relative_root); // bah windows
      $relative_root = rtrim($relative_root, '/');
      // go up one level.
      $relative_root = preg_replace('%/[^/]*$%', '', $relative_root);
    }
    $relative_root = preg_replace('%(?:^(?!/))|(?://+)|(?:(?<!/)$)%', '/',
      $relative_root);
    $out = '';
    $out .= "<link rel='stylesheet' type='text/css'
      href='$relative_root/style/luminous.css'>\n";
    $out .= "<link rel='stylesheet' type='text/css'
      href='$relative_root/style/$theme'>\n";
    if ($js){
      if($jquery)
        $out .= "<script type='text/javascript'
          src='$relative_root/client/jquery-1.4.2.min.js'></script>\n";
      $out .= "<script type='text/javascript'
        src='$relative_root/client/luminous.js'></script>\n";
    }
    return $out;
  }
}








