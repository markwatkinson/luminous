This is basically an experimental redesign and rewrite of Luminous, which is 
a syntax highlighter for PHP.

Rationale/Preamble
------------------

The primary problem this aims to solve is the fact that Luminous is *massive*.

This happened for a few reasons: The main one is that php is incredibly slow 
for this kind of thing (I implemented a prototype in Python and PHP, the former
ran slightly more than twice as fast, but it being in php is kind of the whole
point) and so I started relying heavily on the PHP preg_* and str* built-ins, 
adding more and more kludge to try to prevent overlaps between calls (involving
judicious use of placeholders which we had to hope were being ignored). From
what I can tell, overlaps are a pretty common concern with syntax highlighters. 
The result was pretty accurate and pretty speedy, but huge and very hard to 
maintain.

The second issue is that Luminous was built around the fairly obvious 
idea of a single, central scanner/lexer which would take as input: a language 
ruleset and a source string of that language. This isn't really scalable: it 
involved a lot of extensions to handle quirks specific to one or two languages
and it's hard to handle inconsistencies. Extensions always had to be data-based 
(i.e. giving the scanner a flag which it had to know how to respond to) rather 
than giving it actual instructions, i.e. if you see x, do *this*.

But performance seems less important now. Transparent caching (of the 
end-result) has been built into Luminous right from the start. And there are 
other considerations: Luminous used up a lot of memory, probably due to the 
number of things it was caching during the process of highlighting and the 
number of placeholder maps it had to store. This may shave time off the CPU 
requirements but in reality it would still fold under a large number of 
concurrent requests because it would just run out of memory.

And there were shortcomings:

  * Languages using a state transition table could get really slow due to a
    general safety check that had to be performed by frequently traversing the 
    whole stack, to check that the placeholder optimisations were being 
    performed safely!
  * The central lexer hit a brick wall on some things, like effective 
    distinction of regular expression literals from division operators in some 
    languages (this requires looking behind in the token stream, not just a
    lookbehind assertion in a regexp, not something a set of static rules are
    capable of), and correct matching of Perl's matched delimiters was next to 
    impossible. And don't get me started on Ruby.
  * Handling nested languages appeared to work well, but nesting is inherently
    inconsistent: '<?' *always* means 'php [or whatever] here', but 
    '<script\\b' could be inside a comment or a string or inside a CSS block or
    anything.
  * JavaScript and some other languages (Scala?) can do XML literals. Luminous 
    can't, even though it can do XML. huh? EXACTLY. The big deal with 
    incorrectly skipping a type is that if it has an embedded quote, then it
    just looks hideous as it cascades throughout the rest of the document.


About/Aims/etc
--------------

So this is the experimental branch of Luminous, whose general aim is to 
subsitute the actual highlighting element of Luminous for a different approach.
The general idea is to use one custom written lexer per language rather than 
one lexer (hopefully, however, easy languages like C, Java will consist of very 
very little executable code). This offers three main advantages:

  * It's easier to handle languages' special cases
  * More control over nested langauges
  * Complexity is limited to individual lexers

A quick note here: this is also the approach used by CodeRay (for Ruby), 
which is *insanely* fast. However CodeRay's speed probably comes from the 
fact that it uses a Ruby built-in class to do the legwork on the scanning
(see StringScanner). PHP lacks such a class, so scanning like this is likely
to be slower because we're going to be doing more of the hard work ourselves.
And straight PHP is slooooooow.


## General expectations

  * Overall complexity (say lines of code) will likely greatly exceed the 
    current situation, but...
  * `real' complexity will be a lot lower with only a few hundred lines (at 
    most) needed to perform any one thing, 
  * this will make maintainance and extensions *a lot* easier.
  * Overall highlighting quality should be higher
  * Straightforward languages such as C/similar will be slower, but we don't
    really care
  * More complex langauges which previously used a transition table may be 
    faster, but we don't really care
    
    
Roadmap
-------

  * Implement several varied language lexers to check the idea isn't a 
    non-starter (JavaScript, HTML, CSS, PHP, which should all work correctly
    with each other)
  * Finalise the basic scanner (base class) API, figure out best abstractions
  * Build unit tests on the base classes
  * Figure out basic plugin/extension API for the scanners.
  * Begin integrating into Luminous's existing infrastructure (this will also
    involve porting the 'easyAPI' to the new scanner and language system,
    and scarily, porting the CSS theme parser to the new system, which means
    the new system must be pluginable enough for it to be possible to use it for
    general parsing)
  * Check existing regression test cases and adapt as needed
  * Implement remaining languages (eek)
  