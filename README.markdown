Luminous - a Syntax Highlighter for PHP
=======================================

[![Build Status](https://secure.travis-ci.org/markwatkinson/luminous.png)](http://travis-ci.org/markwatkinson/luminous)

Luminous is an accurate and style-able syntax highlighter for PHP which 
supports a bunch of common languages and output to HTML and LaTeX.

If you simply want to use Luminous as a library, __please don't clone this
repository__. Or if you do, make sure you delete luminous/tests afterwards.
Do not expose luminous/tests on a public machine. It is recommended to get a
packaged version from the links below.

##Links:

+ [Luminous PHP syntax highlighter official site](http://luminous.asgaard.co.uk/) - news, latest stable versions, etc
+ [Online demo](http://luminous.asgaard.co.uk/index.php/demo)
+ [Documentation and help](http://luminous.asgaard.co.uk/index.php/docs/show/index),
  read this if you get stuck!
+ [Supported language list](http://luminous.asgaard.co.uk/assets/luminous/supported.php)
+ [Luminous on GitHub](https://github.com/markwatkinson/luminous) - please
  report problems to the issue tracker here

Installation
============

Extract your tarball, zip, whatever, into some directory where it's going to be
used (i.e. probably your web-server).  We'll assume it's called `luminous/'

## Alternatively, Composer:
Luminous is also available via Packagist as a Composer package:
```json
{
        "require": {
                "luminous/luminous": "0.7.*"
        }
}
```


Quick Usage 
===========

First, if you're going to use caching, which you probably are, create a 
directory called luminous/cache and give it writable permissions (chmod 777 on
most servers -- yours may accept a less permissive value). Then include
luminous/luminous.php and away you go!

```php
<?php
require_once 'luminous/luminous.php';
echo luminous::head_html(); // outputs CSS includes, intended to go in <head>
echo luminous::highlight('c', 'printf("hello world\n");');
```

Useful examples can be found in luminous/examples/. If you have problems,
check that luminous/examples/example.php works.

## Autoloading, PSR-0 and stuff.

Luminous's entire public interface is in the Luminous class, and this is
autoloadable, if you want.

```php
<?php
// via SplClassLoader
$classLoader = new SplClassLoader(null, 'luminous');
$classLoader->register();
echo luminous::highlight('c', 'printf("hello world")'); // works

// alternatively, via Composer's autoload:
<?php
require 'vendor/autoload.php';
echo luminous::highlight('c', 'printf("hello world")');  // works
```




Command Line Usage
==================

If you're crazy and want to use Luminous/PHP on the command line, guess what,
you can!

```bash
$ cd luminous/
$ php luminous.php --help
```
Polite Warning
================

Luminous is fairly slow. But it caches! So it's not slow. Or is it?

It depends on your use-case, is the simple answer. Most people should make sure
the cache works (create luminous/cache with appropriate permissions), and after
that, Luminous will almost certainly have negligable impact on their 
performance.

Optimizations are welcome, but not at the expense of maintainability.

## Caching 
The cache can be stored either directly on the file system or in a MySQL table
(support for other DBMSs will come later, patches welcome). In either case,
check out the [cache documentation](http://luminous.asgaard.co.uk/index.php/docs/show/cache).

Licensing
=========

Luminous is distributed under the LGPL but includes a bunch of stuff which is
separate.

  - Everything under src/ and languages/ is part of Luminous.
  - Everything under tests/regression/*/* is real source code taken from various
      projects, which is used only as test data. It is all GPL-compatible, but
      is distributed under its own license. This directory is only present in
      the git repository and is not part of any stable distribution archives.
  - We also include jQuery which is provided under its own license.
