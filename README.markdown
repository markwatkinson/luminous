Luminous - a Syntax Highlighter for PHP
=======================================

Luminous is an accurate and style-able syntax highlighter for PHP which supports 
a bunch of common languages and output to HTML and LaTeX.

##Links:

+ [Site/info](http://luminous.asgaard.co.uk/)
+ [Live demo and examples](http://luminous.asgaard.co.uk/index.php/demo)
+ [Documentation](http://luminous.asgaard.co.uk/index.php/docs/show/index)
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
    ?>
```

Useful examples can be found in luminous/examples/. If you have problems,
check that luminous/examples/example.php works.

Polite Warning
================

Luminous is pretty slow. It's perfectly usable for highlighting several-KB
snippets on a blog or similar, and it also caches so highlighting is a
one-time overhead. Throughput is roughly 100-200 KB/s depending on the
language. In *most* use cases, this is easily fast enough. You may need to run
your own tests to decide whether or not it is suitable for you.




Licensing
=========

Luminous is distributed under the GPL3 but includes a bunch of stuff which is
separate.
Everything under src/ and languages/ are GPL3.
Everything under tests/regression/*/ is real source code taken from various
projects, which is just used as test data. It is all GPL-compatible, but is
distributed under its own license.

We also include in the distribution jQuery (not currently used by default),
some small icons from the [MIB Ossigeno icon set](http://kde-look.org/content/show.php/MIB+Ossigeno?content=126122) (also not currently
used by default), and the Vera-mono font family. These have their own license
and do not inherit Luminous's.