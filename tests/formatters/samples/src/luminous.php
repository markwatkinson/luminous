<?php

/*
 * Copyright 2010 Mark Watkinson
 * 
 * This file is part of Luminous.
 * 
 * Luminous is free software: you can redistribute it and/or
 * modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Luminous is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Luminous.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */


 /**
   * \file luminous.php
   * \brief Easy (non-OO, single line call) API for Luminous.
   * \author Mark Watkinson
   * 
   * \defgroup LuminousEasyAPI LuminousEasyAPI
   * \example example.php Example calling and arrangement
   */

  
  require_once('core/luminous.class.php');
  require_once('formatters/luminous_formatter.class.php');  
  require_once('luminous_cache.class.php');
  require_once('luminous_grammars.class.php');
  require_once('luminous_grammar_callbacks.php');
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Maximum age of a cached file before it expires (in seconds), or -1
   * \since 0.20
   */   
  global $LUMINOUS_MAX_AGE;
  $LUMINOUS_MAX_AGE = -1;
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Maximum lenth of time between cache purges (deletion of everything) in seconds, or -1
   * \since 0.20
   */ 
  global $LUMINOUS_PURGE_TIME;
  $LUMINOUS_PURGE_TIME = -1;
  /**
   * \ingroup LuminousEasyAPI
   * \brief Line wrap at x characters, set to -1 to disable wrapping
   * \since 0.20
   */ 
  global $LUMINOUS_WRAP_WIDTH;
  $LUMINOUS_WRAP_WIDTH = -1;
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Enables or disables line numbering
   * \since 0.20
   */ 
  global $LUMINOUS_LINE_NUMBERS;
  $LUMINOUS_LINE_NUMBERS = true;
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief So-called 'verbosity' level control
   * \ref verbosity
   * \since 0.25
   */   
  global $LUMINOUS_HIGHLIGHTING_LEVEL;
  $LUMINOUS_HIGHLIGHTING_LEVEL = 4;
  
  /** 
   * \ingroup LuminousEasyAPI
   * \brief Enables or disables URI linking by the formatter.
   * \since 0.30
   */
  global $LUMINOUS_LINK_URIS;
  $LUMINOUS_LINK_URIS = true;
  
  /**
   * \ingroup LuminousEasyAPI
   * Information variable which logs whether the most recent Luminous call was
   * read from the cache or generated. 
   * \since 0.30
   * 
   */ 
  global $LUMINOUS_WAS_CACHED;
  $LUMINOUS_WAS_CACHED = false;
  
  /**
   * \ingroup LuminousEasyAPI
   * 
   * The maximum height, in pixels, of the resulting widget. Use 0 or -1 to 
   * unconstrain. The excess height will be scrollable.
   * \since 0.30
   * 
   */   
  global $LUMINOUS_WIDGET_HEIGHT;
  $LUMINOUS_WIDGET_HEIGHT = 500;
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \since 0.30
   * \brief Version number of Luminous
   */   
  global $LUMINOUS_VERSION;
  $LUMINOUS_VERSION = '0.5.5';
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Performance log
   * 
   * Performance log. Each array element has the keys
   * time (float, s)
   * parse_time (float, s)
   * format_time (float, s)
   * cache_time (float, s) 
   * cached (bool)
   * language (str)
   * input_size (int, bytes)
   * output_size (int, bytes)
   * 
   * 
   * The log is ordered chronologically starting from index 0 as the first 
   * highlight on this page load.
   * \since 0.30
   * 
   */     
  global $LUMINOUS_PERFORMANCE_LOG;
  $LUMINOUS_PERFORMANCE_LOG = array();
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Sets whether to escape Luminous input or whether it is pre-escaped.
   * 
   * Luminous operates on a string of text and uses some special characters 
   * internally. The characters &lt;, &gt; and &amp; are escaped to their HTML
   * entity codes (&amp;lt; &amp;gt; &amp;amp;) by default. In some cases 
   * you may be feeding &Luminous code which is already escaped like this, in
   * which case you will want to set this flag to false so that Luminous doesn't
   * double-escape these characters.
   * \since 0.30
   * 
   */
  global $LUMINOUS_ESCAPE_INPUT;
  $LUMINOUS_ESCAPE_INPUT = true;
  
  

  
  
  /**
   * \since  0.5.4
   * 
   * \ingroup LuminousEasyAPI
   * \brief The output format (as string). 
   * 
   * Current recognised formats are 'html' and 'latex'.
   */
  global $LUMINOUS_OUTPUT_FORMAT;
  $LUMINOUS_OUTPUT_FORMAT = "html";
  
  
  /**
   * \since 0.5.4
   * 
   * \brief The theme to use. 
   * 
   * The theme must exist as a (css) file in luminous/style/
   * 
   * \ingroup LuminousEasyAPI
   * 
   * \warning This has no direct effect in HTML output, you can still include
   * whatever stylesheet you like and ignore this completely.
   */
  global $LUMINOUS_THEME;
  $LUMINOUS_THEME = 'luminous_light';
  
  
  
  
  /**
   * \ingroup LuminousEasyAPI
   * 
   * \since  0.5.4
   * 
   * \brief returns what %Luminous believes is its root directory on the filesystem.
   * 
   * For various reasons internally, we need to know where %Luminous is located 
   * on the filesystem.
   * 
   * replaces $_LUMINOUS_ROOT, because that being writable represents something 
   * of a security consideration
   */
  
  function luminous_root()
  {
    return realpath(__DIR__ . '/../');
  }
  
  
  ///\cond DEV
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Collects settings together into an array
   * \internal
   * \since 0.30
   */
  
  function luminous_create_settings()
  {
    $settings = array(
      'escape'=> $GLOBALS['LUMINOUS_ESCAPE_INPUT'],
      'linenos' => $GLOBALS['LUMINOUS_LINE_NUMBERS'],
      'highlight_level' => $GLOBALS['LUMINOUS_HIGHLIGHTING_LEVEL'],
      'linkify' => $GLOBALS['LUMINOUS_LINK_URIS'],
      'height' => $GLOBALS['LUMINOUS_WIDGET_HEIGHT'],     
      'version' => $GLOBALS['LUMINOUS_VERSION'],
      'wrap' => $GLOBALS['LUMINOUS_WRAP_WIDTH'],
      'theme' => $GLOBALS['LUMINOUS_THEME'],
      'format' => $GLOBALS['LUMINOUS_OUTPUT_FORMAT']
      
    );    
    ksort($settings);
    return $settings;      
  }
  ///\endcond
  
  ///\cond DEV
  /**
   * \ingroup LuminousEasyAPI
   * \internal
   * \since  0.5.4
   * \brief returns the output formatter currently specified in the LUMINOUS_OUTPUT_FORMAT variable
   * \throw Exception if an unknown formatter is specified
   * \return A LuminousFormatter object.
   */
   
  function luminous_get_formatter()
  {
    global $LUMINOUS_OUTPUT_FORMAT;
    $fmt_path = 'formatters/';
    // this is a bit ugly but I don't see a compelling reason to change it to
    // a heavier solution like the one used with the gramamrs.
    switch(strtolower($LUMINOUS_OUTPUT_FORMAT))
    {
      case 'html':
        require_once($fmt_path . 'htmlformatter.class.php');
        return new LuminousFormatterHTML();
        
      case 'latex':
        require_once($fmt_path . 'latexformatter.class.php');
        return new LuminousFormatterLatex();
      
      default:
        throw new Exception('Unknown formatter: ' . $LUMINOUS_OUTPUT_FORMAT);
        return null;
    }
  }
  
  /// \endcond
  
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Simple call to luminous with language grammar
   * 
   * \param grammar The grammar to use to parse the string, as LuminousGrammar
   * \param src the source string to be formatted (string)
   * \param use_cache determines whether to use the caching system, 
   *    default is true. (true|false)
   * \return an HTML formatted piece of text representing the input string
   * \throw Exception if luminous encounters a fatal error (a more descriptive
   *    string will be set as its message)
   * \see Luminous
   * \see LuminousEasyAPI::$luminous_grammars
   */ 
  
  function luminous_grammar(LuminousGrammar $grammar, $src, $use_cache=true)
  {
    global $LUMINOUS_PERFORMANCE_LOG;
    $start = microtime(true);
    $perf_log = array();
    $perf_log['language'] = $grammar->info['language'];
    $perf_log['input_size'] = strlen($src);
    $perf_log['cache_time'] = 0.0;
    
    $o = false;
    $cache = null;
    
    
    if ($use_cache)
    {
      $c_t = microtime(true);
      // the cache's unique ID needs to be an alagamation of the input source,
      // grammar, and highlighting settings.
      $md5 = md5($src);      
      $id = md5($md5 . serialize($grammar->info));
      $id = md5($id . serialize(luminous_create_settings()));
      $cache = new LuminousCache($id, $md5);
      $cache->version = $GLOBALS['LUMINOUS_VERSION'];
      $cache->cache_max_age = $GLOBALS['LUMINOUS_MAX_AGE'];
      $cache->purge_time = $GLOBALS['LUMINOUS_PURGE_TIME'];
      $cache->Purge();
      $o = $cache->ReadCache();
      $GLOBALS['LUMINOUS_WAS_CACHED'] = true;
      $perf_log['cached'] = true;
      $perf_log['parse_time'] = 0.0;
      $perf_log['format_time'] = 0.0;
      $perf_log['cache_time'] = microtime(true) - $c_t;
    }
    
    if ($o === false)
    {
      $perf_log['cached'] = false;
      
      $GLOBALS['LUMINOUS_WAS_CACHED'] = false;
      $p_start = microtime(true);
      $l = new Luminous();    
      $l->verbosity = $GLOBALS['LUMINOUS_HIGHLIGHTING_LEVEL'];
      $l->pre_escaped = !$GLOBALS['LUMINOUS_ESCAPE_INPUT'];
      $l->separate_lines = $GLOBALS['LUMINOUS_LINE_NUMBERS'];
      $o = $l->Easy_Parse($src, $grammar);  
      $p_end = microtime(true);
      $f_start = microtime(true);
      
      $f = luminous_get_formatter();
//       $f = new LuminousFormatterHTML(); 
      $f->SetTheme(luminous_get_theme());
      $f->wrap_length = $GLOBALS['LUMINOUS_WRAP_WIDTH'];
      $f->line_numbers = $GLOBALS['LUMINOUS_LINE_NUMBERS'];
      $f->link = $GLOBALS['LUMINOUS_LINK_URIS'];
      $f->height = $GLOBALS['LUMINOUS_WIDGET_HEIGHT'];
      
      // this really shouldn't be here.
      if ($grammar instanceof LuminousGrammarWhitespace)
        $f->tab_width = -1;
      $o = $f->Format($o);
      $f_end = microtime(true);
      
      if ($use_cache)
        $cache->WriteCache($o);
      
      $perf_log['format_time'] = $f_end - $f_start;
      $perf_log['parse_time'] = $p_end - $p_start;
    }
    $end = microtime(true);
    $perf_log['time'] = $end-$start;
    $perf_log['output_size'] = strlen($o);
    $LUMINOUS_PERFORMANCE_LOG[] = $perf_log;
    
    return $o;
    
  }
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Simple call to luminous with language code
   * 
   * \param language The language code (which shall be looked up in 
   *    luminous_grammars. If no corresponding grammar is found, its default 
   *    will be used. In the event that no default is specified luminous, will 
   *    raise an Exception. 
   * \param src the source string to be formatted (string)
   * \param use_cache determines whether to use the caching system, 
   *    default is true. (true|false)
   * \return a piece of text representing the input string, formatted according
   *    to the format specified in LuminousEasyAPI::$LUMINOUS_OUTPUT_FORMAT
   * \throw Exception if luminous encounters a fatal error (a more descriptive
   *    string will be set as its message)
   * \see Luminous
   * \see LuminousEasyAPI::$luminous_grammars
   */ 
  function luminous($language, $src, $use_cache=true)
  {
    global $luminous_grammars;
    $grammar = $luminous_grammars->GetGrammar($language);
    return luminous_grammar($grammar, $src, $use_cache);
  }
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Simple call to Luminous with a file path to use as src.
   * 
   * \param language The language code (which shall be looked up in 
   *    luminous_grammars. If no corresponding grammar is found, its default 
   *    will be used. In the event that no default is specified luminous, will 
   *    raise an Exception.
   * \param path The path to the source file which shall be read by Luminous.
   * \param use_cache determines whether to use the caching system, 
   *    default is true. (true|false)
   * \return an HTML formatted piece of text representing the input string
   * \throw Exception if luminous encounters a fatal error (a more descriptive
   *    string will be set as its message)
   * \see Luminous
   * \see LuminousEasyAPI::$luminous_grammars 
   */ 
  function luminous_file($language, $path, $use_cache=true)
  {
    $src = file_get_contents($path);
    return luminous($language, $src, $use_cache); 
  }
  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Prints a table of known languages and their codes to output
   * 
   * This is a convenience function the user can call to determine which 
   * languages are supported, and how they can access them.
   * 
   * Similar to phpinfo()
   */ 
  function luminous_supported_languages()
  {
    global $luminous_grammars;
    $list = $luminous_grammars->ListGrammars();
    ksort($list);
    echo '<h1>Luminous Supported Languages</h1>';
    echo '<table style="margin-left:auto;margin-right:auto; ">
      <tr style="font-weight:bold"><td></td>
      <td>Language</td><td>Valid Codes</td></tr>';
    $count = 0;
    foreach($list as $k=>$l)
    {
      $count++;
      sort($l);
      echo "<tr><td> $count </td><td>$k</td><td>" . join($l, ", ") 
        . "</td></tr>";
    }
    echo "</table>";
  }

  /**
   * \ingroup LuminousEasyAPI
   * Returns a list of themes present in the $ROOT/style/ directory.
   * The names will simply be the filename, not including the parent directory
   * structure.
   *
   * \return the list, as array
   */ 
  function luminous_get_themes()
  {
    $themes_uri = luminous_root() . "/style/";
    $themes = array();
    foreach(glob($themes_uri . "/*.css") as $css)
    {
      $fn = trim(preg_replace("%.*/%", '', $css));
      switch($fn)
      {
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
  
  
  ///\cond DEV
  /**
   * \ingroup LuminousEasyAPI
   * returns the theme file as a string.
   * 
   * If you need to access the raw contents of a theme, this method is 
   * preferred as it includes security checks against reading arbitrary files
   * 
   * \todo this probably shouldn't be here, since it's internal.
   * \internal
   */
  function luminous_get_theme($theme=null)
  {
    if ($theme === null)
      $theme = $GLOBALS['LUMINOUS_THEME'];
    if (!preg_match('/\.css$/i', $theme))
      $theme .= '.css';
    
    if (!preg_match('/^[a-zA-Z0-9_\-]+(\.css)$/i', $theme))
    {
      throw new Exception("Invalid theme filename: " . htmlentities($theme));
      return null;
    }
    
    $path = luminous_root() . '/style/' . $theme;
    
    if (!file_exists($path))
    {
      throw new Exception("No such theme file: " . htmlentities($path));
      return;
    }
    return file_get_contents($path);
  }
  ///\endcond
  
  /**
   * \ingroup LuminousEasyAPI
   * 
   * Returns a string representing everything that needs to be printed in 
   * the \<head\> section of a website.
   * 
   * \param $theme (str) the theme to use. The theme file should exist
   *    as a file of the same name in luminous/style/. If you leave this as 
   *    \c NULL, the theme in LuminousEasyAPI::$LUMINOUS_THEME will be used
   * \param $javascript (boolean) whether or not to use JavaScript
   * \param $jquery (boolean) whether or not to include jQuery: jQuery is required for 
   *    javascript to work, but you may include it yourself (if you need a different
   *    version). If so, it must be included before this function's output is 
   *    echoed.
   * \param relative_root Optionally, you may specify the path to luminous/
   *    relative to the document root (remember to include the leading slash).
   *    If you don't specify this, Luminous attempts to work it out. See
   *    warning.
   *    
   * 
   * \warning due to shortcomings in PHP, it's not really possible to figure
   * out the include path if there are symbolic links involved In this case, you
   * \b must specify relative_root.
   * http://bugs.php.net/46260
   * 
   * \since 0.5.0
   */
  
  function luminous_get_html_head($theme=null, $javascript=true,
    $jquery=true, $relative_root=null)
  {

    if ($relative_root === null)
    {
      $relative_root = str_replace($_SERVER['DOCUMENT_ROOT'], '/', __DIR__);
      $relative_root = str_replace('\\', '/', $relative_root); // bah windows
      $relative_root = rtrim($relative_root, '/');
      // go up one level.
      $relative_root = preg_replace('%/[^/]*$%', '', $relative_root);
    }
    $relative_root = preg_replace('%(?:^(?!/))|(?://+)|(?:(?<!/)$)%', '/', $relative_root);
    
    if ($theme === null || $theme === '' || $theme === false)
      $theme = $GLOBALS['LUMINOUS_THEME'];
    
    $theme = urlencode(trim($theme));
    if (!preg_match('%\.css$%i', $theme))
      $theme .= '.css';

    $abs_root = luminous_root();
    $lcss = file_exists("$abs_root/style/luminous.min.css")? 
      "luminous.min.css" : "luminous.css";

    $out = "";
    $out .= "<link rel='stylesheet' type='text/css' href='$relative_root/style/$lcss'>\n";
    $out .= "<link rel='stylesheet' type='text/css' href='$relative_root/style/$theme'>\n";
    if ($javascript)
    {
      if($jquery)
        $out .= "<script type='text/javascript' src='$relative_root/client/jquery-1.4.2.min.js'></script>\n";
      $ljs = file_exists("$abs_root/client/luminous.min.js")? "luminous.min.js"
        : "luminous.js";
      $out .= "<script type='text/javascript' src='$relative_root/client/$ljs'></script>\n";
    }
    return $out;
  }
  
  
  
  
  

  
  
  /**
   * \ingroup LuminousEasyAPI
   * \brief Lookup table for language codes to grammars.
   * 
   * Global variable holding a LuminousGrammars language to grammar lookup 
   * table .This is used in any call to the easy API where a language code is 
   * specified. Add your own grammars to this using its AddGrammar method.
   * Anything stored in here is accessible by luminous()
   * \sa LuminousGrammars
   * \sa LuminousGrammars::AddGrammar
   */
  global $luminous_grammars;
  $luminous_grammars = new LuminousGrammars();
  
  
  $luminous_grammars->AddGrammar('as', 'LuminousGrammarActionscript',
    'ActionScript', luminous_root() . "/languages/actionscript.php");
    
  $luminous_grammars->AddGrammar('bnf', 'LuminousGrammarBNF', 
    'Backus Naur Form', luminous_root() . "/languages/bnf.php");
    
  $luminous_grammars->AddGrammar(array('c', 'cpp', 'h', 'hpp', 'hxx', 'cxx'), 
    'LuminousGrammarCpp', 'C/C++', luminous_root() . "/languages/cpp_stateful.php");
    
  $luminous_grammars->AddGrammar('changelog', 'LuminousGrammarChangelog', 
    'Changelog', luminous_root() . "/languages/changelog.php");
    
  $luminous_grammars->AddGrammar(array("cs", "csharp"), 
    'LuminousGrammarCSharp', 'C#', luminous_root() . "/languages/csharp.php");    
    
  $luminous_grammars->AddGrammar("css", 'LuminousGrammarCSS', 
  'CSS', luminous_root() . "/languages/css.php");
  
  $luminous_grammars->AddGrammar("diff", 'LuminousGrammarDiff', 
    'Diff', luminous_root() . "/languages/diff.php");
    
  $luminous_grammars->AddGrammar(array('erl', 'erlang'),
    'LuminousGrammarErlang', 'Erlang', 
    luminous_root() . "/languages/erlang.php"); 
    
  $luminous_grammars->AddGrammar('groovy', 'LuminousGrammarGroovy',
    'Groovy', luminous_root() . "/languages/groovy.php", 'java');
    
  $luminous_grammars->AddGrammar(array('haskell', 'hs'), 
    'LuminousGrammarHaskell', 'Haskell', 
    luminous_root() . "/languages/haskell.php");  
    
  $luminous_grammars->AddGrammar("html", 'LuminousGrammarJavaScriptEmbedded', 
    'HTML', luminous_root() . "/languages/javascript.php", array('xml', 'css'));
    
  $luminous_grammars->AddGrammar('go', 'LuminousGrammarGo', 
    'Go', luminous_root() . "/languages/go.php");
    
  $luminous_grammars->AddGrammar("java", 'LuminousGrammarJava',
  'Java', luminous_root() . "/languages/java.php");  
  
  $luminous_grammars->AddGrammar("js", 'LuminousGrammarJavaScript',
    'JavaScript', luminous_root() . "/languages/javascript.php");  
    
  $luminous_grammars->AddGrammar(array('latex', 'tex'), 'LuminousGrammarLatex',         
  'LaTeX', luminous_root() . "/languages/latex.php");   
  
  $luminous_grammars->AddGrammar(array('lolcode', 'lol'),
    'LuminousGrammarLolcode', 'LOLCODE',
    luminous_root() . "/languages/lolcode.php");
    
  $luminous_grammars->AddGrammar(array('make', 'makefile'), 
    'LuminousGrammarMakefile', 'Make', luminous_root() . "/languages/make.php",
    'bash');
    
  $luminous_grammars->AddGrammar(array('matlab', 'm'), 'LuminousGrammarMATLAB',
  'MATLAB', luminous_root() . "/languages/matlab.php");  
    
  $luminous_grammars->AddGrammar('mxml', 'LuminousGrammarActionscriptEmbedded',
    'XML + ActionScript', luminous_root() . "/languages/actionscript_xml.php",
    array('as', 'html')
  );
  
  $luminous_grammars->AddGrammar(array('pas', 'pascal'), 
    'LuminousGrammarPascal', 'Pascal/Delphi', 
    luminous_root() . "/languages/pascal.php");
    
  $luminous_grammars->AddGrammar("php", 'LuminousGrammarPHP', 'PHP',
    luminous_root() . "/languages/php.php", 'html');
    
  $luminous_grammars->AddGrammar(array('pl', 'perl'), 'LuminousGrammarPerl',
  'Perl', luminous_root() . "/languages/perl.php");
  
  $luminous_grammars->AddGrammar('plain',
    'LuminousGrammarPlain', 'Plain text', 
    luminous_root() . "/languages/plain.php");
  
  
  $luminous_grammars->AddGrammar(array("py", 'python'), 
    'LuminousGrammarPython', 'Python', luminous_root() . "/languages/python.php");
    
  $luminous_grammars->AddGrammar(array('rails', 'rhtml'), 
    'LuminousGrammarRubyHTML', 'Ruby On Rails',
    luminous_root() . "/languages/ruby_xml.php", array('ruby', 'html'));  
    
  $luminous_grammars->AddGrammar(array('ruby', 'rb'), 'LuminousGrammarRuby',
    'Ruby', luminous_root() . "/languages/ruby.php");
    

  $luminous_grammars->AddGrammar('scala', 
    'LuminousGrammarScala', 'Scala', luminous_root() . "/languages/scala.php");
    
  $luminous_grammars->AddGrammar(array('shell', 'sh', 'bash'), 
    'LuminousGrammarBash', 'Bash', luminous_root() . "/languages/bash.php");
    
  $luminous_grammars->AddGrammar("sql", 'LuminousGrammarSQL', 'SQL',
  luminous_root() . "/languages/sql.php");
  

  
  
  $luminous_grammars->AddGrammar('vim', 'LuminousGrammarVim',
  'Vim script', luminous_root() . "/languages/vim.php");
    
  $luminous_grammars->AddGrammar("vb", 'LuminousGrammarVisualBasic', 
  'Visual Basic', luminous_root() . "/languages/vb.php");
    
  $luminous_grammars->AddGrammar(array('ws', 'whitespace'), 
  'LuminousGrammarWhitespace', 'Whitespace', luminous_root() . "/languages/whitespace.php");
    
  $luminous_grammars->AddGrammar("xml", 'LuminousGrammarHTML', 'XML',
  luminous_root() . "/languages/xml.php");  
  
  
  $luminous_grammars->AddGrammar('generic', 'LuminousGrammarGeneric', 
    'Generic C-like language', luminous_root() . "/languages/generic.php");
  
  $luminous_grammars->SetDefaultGrammar('plain');
  
  
