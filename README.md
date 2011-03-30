Luminous - a Syntax Highlighter for PHP
=======================================

Luminous is an accurate and style-able syntax highlighter for PHP which supports 
a bunch of common languages and output to HTML and LaTeX.

##Links:

+ [Demo](http://luminous.asgaard.co.uk/)
+ [Supported language list](http://luminous.asgaard.co.uk/luminous/supported.php)
+ [Documentation (on the github wiki)](https://github.com/markwatkinson/luminous/wiki)


Installation
============
Extract your tarball, zip, whatever, into some directory where it's going to be
used (i.e. probably your web-server).  We'll assume it's called `luminous/'

Quick Usage 
===========

First, if you're going to use caching, which you probably are, create a 
directory called luminous/cache and give it 777 permissions. Then include 
luminous/luminous.php and away you go!

    <?php 
    require_once 'luminous/luminous.php';
    echo luminous::head_html(); // outputs CSS includes, intending to go in <head>
    echo luminous::highlight('c', 'printf("hello world\n");');
    ?>

Useful examples can be found in luminous/examples/. If you have problems,
check that luminous/examples/example.php works.

A Polite Warning
================

A few things you should bear in mind:

+ Highlighted output is *massive*, expect around 10x the size, so you really
do want to be using gzip (it's very gzippable).
+ Luminous is now pretty slow. This isn't likely to change. The code is
mostly well structured and the profiler just says it's slow because it's doing
a lot, and I'm not going to start messing with the structure to favour 
performance because as anyone who has tried that knows, that's the first step
towards code that can only be described as 'a mess'.
+ Memory usage is also pretty obscene right now. This might go down in future
if I find any way of profiling it. 

Note that the last two are irrelevant for most use cases because Luminous
features transparent caching, and if your inputs are at most a few KB (e.g.
snippets on a blog), then they're too small to run up much performance 
penalty. However they are very important considerations if you are highlighting 
arbitrarily sized user input.

If any of these things bothers you, you've got a few options: 

1. use an old version of Luminous. The 
[0.5 branch](http://code.google.com/p/luminous) works much faster (but for 
complex languages, not as well)
2. Use GeSHi, although I tried this one once; look where it got us ;)
3. Rewrite your project in Python

I recommend option 3.


User's API reference
====================

We don't currently have a useful documentation manager, so I'm afraid this
will have to do for now.

The two main highlighting functions are:
  
    luminous::highlight($language, $source, $cache=true)
    luminous::highlight_file($language, $path, $cache=true)
  
Note that $language can be a language code (open supported.php in a browser 
to see a list of what you have available), or your own instance of
LuminousScanner.

    luminous::head_html()
  
This will output several link and script tags. It tries to determine the
correct path to the luminous/ directory relative to the document root, but 
may fail. In this case, you can override it to set it manually. The settings:
'theme', 'relative-root', 'include-javascript' and 'include-jquery' affect
this.

##Themes

    luminous::themes(); 
    luminous::theme_exists($theme_name);

themes() returns a list of themes present in the style/ directory. Use this 
if you're building a theme selector. theme_exists() returns true if a theme
exists in style/, false otherwise. 
  
returns true if a theme exists in the style/ directory, else false.

##Settings:##

    luminous::set($name, $value)
  
Sets an internal setting to the given value. An exception is raised if 
the setting is unrecognised.

    luminous::setting($name)
  
Returns the value currently set for the given setting. An exception is raised 
if the setting is unrecognised.
  
###List of observed settings###
  
As with php, setting an integer setting to 0 or -1 will disable it


#### misc

+ cache-age(int): age (seconds) at which to remove cached files (age is 
determined by mtime -- cache hits trigger a `touch', so this setting removes
cached files which have not been accessed for the given time.),
0 or -1 to disable. (default: 777600 : 90 days)

+ include-javascript (bool): controls whether luminous::head_html() outputs
the javascript 'extras'.

+ include-jquery (bool): controls whether luminous::head_html() outputs
jquery; this is ignored if include-javascript is false.

+ relative-root (str): luminous::head_html() has to know the location of the
luminous directory relative to the location of the document root. It 
tries to figure this out, but may fail if you are using symlinks. 
You may override it here.

+ theme: Sets the internal theme. The LaTeX formatter reads this, and
luminous::head_html observes this.


#### formatter

+ auto-link(bool): if the formatter supports hyperlinking, URIs will be linked

+ html-strict(bool): Luminous uses the 'target' attribute of hyperlinks (`a' tags). 
This is not valid for X/HTML4 strict, therefore it may be disabled. Note that
this is purely academic: browsers don't care, and let's be honest, it was a 
stupid idea by the W3C anyway. Luminous produces valid HTML5 and HTML4 
transitional output regardless.

+ line-numbers(bool): If the formatter supports line numbering, lines are 
numbered. (default: true)

+ max-height(int): if the formatter can control its height, it will constrain 
itself to this many pixels (you may specify this as a string with units)
(default: 500)

+ wrap-width(int): if the formatter supports line wrapping, lines 
will be wrapped at this number of characters (0 or -1 to disable) (default: 
-1)  

+ format (string): Controls the output format:  
  1. 'html': HTML. The HTML is basically a widget, and is heavily stylised by 
  external CSS which you have to include in your page.
  2. 'html-inline': This is a small variation on the HTML formatter which 
  stylises output for inline (in-text) display. The output is in an 
  inline-block element, with line numebrs and height constraints 
  disabled. You probably want HTML.  
  3. 'latex': LaTeX. 
  4. 'none', null: the 'identity' formatter, i.e. no formatting is applied. The
  result is basically an XML fragment, the way Luminous 'tags' the string
  internally. This is implemented for debugging but may in special 
  circumstances be of user interest?


  
###Other functions:
  
If you have a lot of custom scanners, you can make use of the internal 
scanner table by registering your scanners:
  
    register_scanner($language_code, $classname, $readable_language, $path, 
                   $dependencies=null);
  
codes may be an array or string. This means you don't have to include or 
instantiate your scanner classes yourself, instead you can use the given codes
and Luminous performs lazy file inclusion as and when necessary.

If you write serveral scanners which rely on each other, list their codes in 
the dependencies array. If you end up with circular include requirements\*, 
write a dummy include file which includes everything needed, insert that first 
with classname=null, and list that insertion's code as a dependency in your 
real insertion.
\* this can happen: you may have a 'compile time' dependency like a superclass's
definition, and a 'runtime' dependency like a sub-scanner which needs to be
instantiated (at runtime). These are conceptually different but handled in
the same way, hence minor problems can occur.

  
  
