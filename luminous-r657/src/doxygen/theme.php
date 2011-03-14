<?
/**
 * \page theme Creating a theme

Creation of a theme is fairly easy; the best way for now is to start out with an
existing theme, copy it to a new file, and modify it to your preference.



Themes exist within the style/ directory. You should also put yours there so
that %Luminous knows about it. This will also make it available to be used in 
other formatters (other than HTML output)


You may find some rules in a style which don't seem to have any effect; some 
classes such as \c lang_*, and \c user_func are not actually implemented (but
may be in future).

Most class names should be self-explanatory, those that might not be follow:

.comment_note :  \verbatim // comment tags such as NOTE  HACK XXX TODO etc  \endverbatim \n
.heredoc : Some languages implement a 'heredoc' syntax, if you're not familiar with this, it's basically a multiline string.
http://en.wikipedia.org/wiki/Here_document \n
.numeric : any form of numeric literal (e.g. \c 1  \c 24,  \c 3.14159, \c 0x00)\n
.obj / .oo :   \c object->property,  \c object.property, \c object::property, etc, obj is the object, oo is the property.\n
.value : this is something of a catch-all, if it should be highlighted but it's not common to many languages then it's probably called a 'value' \n
.variable : if it's possible to detect variable names, then they'll be tagged with this. This is also used in string interpolation. \n


.regex* : regular expression literals, the more specific properties are used in PCRE syntax highlighting \n
.code  : code is the immediate container for all code, global text properties should be set here.


<h1> Style Guide </h1>

Although %Luminous now includes a LaTeX output formatter, and .css is obviously 
aimed at styling HTML, .css is the standard format in which to define %Luminous 
themes. Styling of other output formats (e.g. LaTeX) is achieved by %Luminous 
parsing the CSS file and picking out the relevent rules and styles. For this 
reason, themes should be very conservative with use of more advanced CSS 
features such as selectors, and pseudo classes, and applications of style to 
elements rather than classes.

All rules should be defined with a '.luminous ' in front of them. This prevents
them from leaking outside of luminous elements.


In the case that you define a property multiple times for a particular rule,
the first will take precedence in non-HTML output formats.

*/