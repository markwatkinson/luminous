Luminous - a Syntax Highlighter for PHP
=======================================

Luminous is an accurate and style-able syntax highlighter for PHP which supports 
a bunch of common languages and output to HTML and LaTeX.

##Links:

+ [Site/info](http://luminous.asgaard.co.uk/)
+ [Supported language list](http://luminous.asgaard.co.uk/assets/luminous/supported.php)

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
