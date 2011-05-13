Luminous - a Syntax Highlighter for PHP
=======================================

Luminous is an accurate and style-able syntax highlighter for PHP which supports 
a bunch of common languages and output to HTML and LaTeX.

##Links:

+ [Site/info](http://luminous.asgaard.co.uk/)
+ [Live demo and examples](http://luminous.asgaard.co.uk/index.php/demo)
+ [Documentation and help, READ THIS!](http://luminous.asgaard.co.uk/index.php/docs/show/index)
+ [Supported language list](http://luminous.asgaard.co.uk/assets/luminous/supported.php)
+ [Luminous on GitHub](https://github.com/markwatkinson/luminous)

Installation
============
Extract your tarball, zip, whatever, into some directory where it's going to be
used (i.e. probably your web-server).  We'll assume it's called `luminous/'

Quick Usage 
===========

First, if you're going to use caching, which you probably are, create a 
directory called luminous/cache and give it 777 permissions. Then include 
luminous/luminous.php and away you go!

```php
<?php
require_once 'luminous/luminous.php';
echo luminous::head_html(); // outputs CSS includes, intending to go in <head>
echo luminous::highlight('c', 'printf("hello world\n");');
```

Useful examples can be found in luminous/examples/. If you have problems,
check that luminous/examples/example.php works.

Polite Warning
================

Luminous is pretty slow. It's perfectly usable for highlighting several-KB
snippets on a blog or similar, and it also caches so highlighting is a
one-time overhead. Throughput is roughly 50-200 KB/s depending on the
language. In *most* use cases, this is easily fast enough, and Luminous does
cache its highlights so there is no real penalty most of the time. You may need
to run your own tests to decide whether or not it is suitable for you.

Licensing
=========

Luminous is distributed under the GPL3 but includes a bunch of stuff which is
separate.

  - Everything under src/ and languages/ is part of Luminous.
  - Everything under tests/regression/*/ is real source code taken from various
      projects, which is used only as test data. It is all GPL-compatible, but
      is distributed under its own license. This directory is only present in
      the git repository and is not part of any stable distribution archives.
  - We also include jQuery (client/jquery-1.4.2.min.js) and the vera-mono
    font family (client/font/). These are provided under their own licenses.