<?php

/**
 * \file doxygen.php
 * \brief Just somewhere to put all the doxygen stuff.
 */ 
/** 
 * \mainpage
 * Hello! Welcome to the documentation for %Luminous, a web based multi language 
 * syntax highlighter. %Luminous supports a variety of programming languages
 * and is written in PHP making it suitable for use on most web-servers.
 * 
 * You might be here because...
 * 
 * ...you just want to get started using %Luminous: \ref basic_usage
 * 
 * ...you want to look up the right code for a language \ref supported_languages
 * 
 * ...you want a bit more control over what happens when you call %Luminous 
 *      \ref less_basic_usage
 * 
 * ...you want to start writing a grammar \ref grammar_howto
 * 
 * ...you wrote a grammar but it's not working, %Luminous is spewing error
 *       messages, your rules just aren't matching, output has all these cryptic
 *       symbols in it: \ref halp
 * 
 * 
 * Report bugs to markwatkinson gmail com. Patches, new grammars, other 
 *      useful additions welcome too. 
 * 
 */ 

/**
 * \page verbosity Highlighting Verbosity
 * 
 * <h1>Information for users and grammar writers.</h1>
 * 
 * %Luminous understands verbosity to be the amount of different types which
 * are highlighted. Verbosity level is specified as an integer from 0 upwards
 * and each level adds some more detail. At level 0, only the most important
 * features should be highlighted. At level 1, a few more things are too.
 * By the time we are up to level 4, everything in the grammar is highlighted. 
 * This allows the user some degree of control over the output 
 * (and its CPU requirements) without having to alter a grammar to exclude 
 * certain things.
 * 
 * The general pattern is:
 * 
 * Level 0: Delimited types (strings literals, comments, etc)
 * 
 * Level 1: Numerical literals, misc. language keywords
 * 
 * Level 2: Types and common language functions
 * 
 * Level 3: Variables (if possible) and other things which look nice but aren't
 * really necessary. Level 3 also enables grammars' callback functions to be 
 * executed which handle things like string interpolation and doxygen-like
 * doc comment highlighting.
 * 
 * Level 4: Operators and anything else which doesn't really add much, but 
 * might be desired anyway.
 * 
 * But grammar writers are free to redefine this as they feel necessary. Some
 * languages have greatly different structure to others and it is up to the
 * author to work out a compatible hierarchy.
 * 
 * 
 * <h1>Information for grammar writers</h1>
 * Verbosity must be specified in the constructor method for rule objects. 
 * Typically you will want all delimited types to be at 0, this is because
 * these provide the most visual impact and failure to match one delimited type
 * may skew detection of other delimited types (Matching strings, but not chars 
 * is a bad idea if your source declares: <code>char s = '"';</code>).
 */ 

/**
 * \page basic_usage Basic usage (start here)
 * 
 * 
 * \warning First things first, there is
 * a minor risk that a bug in %Luminous could cause an infinite loop [this is also
 * true of any non-trivial syntax highlighter], so I \b strongly recommend you set 
 * <a href="http://php.net/manual/en/function.set-time-limit.php">
 * set_time_limit() </a> before calling %Luminous so you don't risk annoying
 * your web host or enacting a denial of service attack on yourself. Please 
 * report bugs.
 * 
 * 
 * Basic usage is easy! Just call luminous() and away
 * you go:
 * 
 * \code
<!-- this is the layout information -- this is mandatory -->
<link rel="stylesheet" type="text/css" href="/path/to/luminous/style/luminous.css">

<!-- this can be any theming stylesheet you like //-->
<link rel="stylesheet" type="text/css" href="/path/to/luminous/style/luminous_light.css">



<?php
require_once('/path/to/luminous/luminous.php');
echo luminous('cpp', '#include <stdio.h>
int main()
{
  printf("hello, world");
  return 0;
}
');
?>
\endcode
 *
 * \note for convenience, if your path to the Luminous root directory is not
 *  a symbolic link, you can call 
 * <code><?php echo luminous_get_html_head(); ?></code> inside your HTML 
 * \<head\> section to insert all CSS and JavaScript.
 * \sa LuminousEasyAPI::luminous_get_html_head
 *
 * This returns a code 'widget'. You might want to wrap it in a div, or an iframe
 * if you're feeling retro, to make it look a bit neater on your page depending
 * on exactly what you're using it for. If you want to (un)?constrain the height 
 * of the widget look at LuminousEasyAPI::$LUMINOUS_WIDGET_HEIGHT instead of 
 * doing it via a wrapped div. 
 * 
 * 
 * The optional third argument to
 * luminous() specifies whether or not you want to use caching. The default is 
 * yes you do. You will have to create the dir /path/to/luminous/cache/ yourself
 * and make it world writable. You can disable caching by passing false as the
 * third argument.
 * 
 * If you're using a custom grammar, use luminous_grammar() and pass an instance
 * of the grammar in place of the language code in the above example.
 * 
 * The languages accessible by default can be found on \ref supported_languages
 * 
 * If you've got a lot of custom grammars and need to choose from a variety of 
 * languages, don't write your own module, make use of 
 * LuminousEasyAPI::$luminous_grammars. You can use this to overwrite my
 * grammars, I won't be offended.
 * 
 * 
 * 
 * <h1>JavaScript extras</h1>
 * %Luminous comes with a few JavaScript extras. These are not mandatory by
 * any means, but exist to make the resulting widget a bit better. They may also
 * be used to apply browser specific hacks which can't easily be done at the 
 * PHP level, so they are beneficial, but their absence should not cause any
 * problems. If the user (visitor) does not have JavaScript enabled they will 
 * simply not bind themselves
 * to any %Luminous widgets, and they will be none the wiser. If they do have
 * JavaScript enabled, they will see a set of buttons when the mouse-over a
 * %Luminous widget. At the moment these consist of:
 * 
 * \li Expand/collapse widget (i.e. unconstrain the height/width such that all
 *      code is visible)
 * \li search/highlight patterns
 * \li Font size increase/decrease
 * \li Toggle highlighting/plain
 * \li Print
 * \li %Luminous info (i.e. self advertising)
 * 
 * The Javascript is reliant upon 
 * <a href="http://jquery.com/" target=_blank>jQuery</a>. Luminous comes with 
 * jQuery 1.4.2. Simply include the following in your \<head\> section:
 * 
\code
<!-- jQuery must come first //-->
<script type='text/javascript' 
  src='/path/to/luminous/client/jquery-1.4.2.min.js'> 
</script> 

<script type='text/javascript' src='/path/to/luminous/client/luminous.js'>
</script> 
\endcode
 * and you're good to go. 
 * 
 * Some users might not want to include jQuery because they are using a different
 * library and fear conflicts. jQuery has a 'noconflict' mode which you can set
 * after including it. 
 * 
 * \code
<script type='text/javascript'>
$.noConflict();
</script>
  \endcode
  * You are then free to include your other libraries. %Luminous should work as
  * long as noConflict is called *after* including luminous.js.
 */ 




/**
 * \page less_basic_usage More advanced usage
 * 
 * I'm just going to give a quick overview of everything here and you can 
 * explore the API docs to your heart's content.
 * 
 * Caching is enabled by default, you can disable it in the LuminousEasyAPI 
 * by passing the third parameter as false. You can enable a timeout on cached
 * documents and a purge (total deletion) of the cache with the global variables 
 * LuminousEasyAPI::$LUMINOUS_MAX_AGE and LuminousEasyAPI::$LUMINOUS_PURGE_TIME. 
 * Both should be given in seconds (-1 means they are ignored).
 * 
 * If %Luminous is taking too much time/memory you can alter the highlighting
 * detail. See LuminousEasyAPI::$LUMINOUS_HIGHLIGHTING_LEVEL 
 * 
 * To use the caching system directly, see LuminousCache. If you do this, you'll
 * need to use Luminous directly too.
 * 
 * To interact with Luminous directly, see Luminous. You may wish to do this 
 * if you want to disable line wrapping, Luminous::$wrap_length. You will 
 * probably just want to call Luminous::Parse_Full() after that.
 * 
 */ 


/**
 * \page supported_languages Supported language list
 * 
 * A list of supported langauges and their internally recognised langauge codes.
 * Where possible these have been chosen to match up with their standard 
 * filename extensions to make it easy for a program to take a source file and
 * determine its language. You may also call 
 * LuminousEasyAPI::luminous_supported_languages to see which grammars your 
 * distribution knows about.
 * 
 *  \li ActionScript -- \b as
 *  \li Backus Naur Form -- \b bnf
 *  \li Bash -- \b sh 
 *  \li C and C++  --  \b c, \b h, \b cpp, \b hpp, \b cxx and \b hxx
 *  \li C# -- \b cs and \b csharp
 *  \li Changelog -- \b changelog
 *  \li CSS (Cascading Style Sheets) --  \b css 
 *  \li diff -- \b diff
 *  \li Erlang -- \b erl and \b erlang
 *  \li Generic C-like language -- \b generic 
 *  \li Go -- \b go
 *  \li Groovy \b groovy
 *  \li Haskell -- \b hs
 *  \li HTML (+ CSS, JavaScript) -- \b html 
 *  \li Java -- \b java
 *  \li JavaScript -- \b js
 *  \li LaTeX -- \b latex
 *  \li MATLAB M-file -- \b matlab
 *  \li MXML (XML + ActionScript) -- \b mxml
 *  \li Makefile -- \b makefile
 *  \li Pascal -- \b pascal, \b pas
 *  \li Perl -- \b pl
 *  \li PHP (+ HTML) -- \b php
 *  \li Plain (inc. generic config file) -- \b plain
 *  \li Python -- \b  py
 *  \li Ruby -- \b rb 
 *  \li Ruby on Rails (Ruby + HTML) -- \b rhtml 
 *  \li Scala -- \b scala
 *  \li SQL -- \b sql
 *  \li Vim script -- \b vim
 *  \li Visual Basic -- \b vb
 *  \li Whitespace -- \b ws, \b whitespace
 *  \li XML (HTML without JS or CSS) -- \b xml
 * 
 *  
 *  \li A generic gramamr (called \b 'generic') is also included. This should
 *      do a fairly decent job of highlighting anything that's vaguely C-like
 * 
 * (this list might not be up te date. Fire up your luminous/supported.php for
 *  a list of what yours supports)
 * 
 * Had another language in mind or think my grammars could be improved?
 * Have a read of \ref grammar_howto. If you've written a super-awesome 
 * grammar or have made improvements to one of mine, and would like to see yours
 * included in %Luminous by default, just send me an email.
 * 
 */ 

 

/**
 * \page grammar_howto Writing your own grammar
 * 
 * To begin writing rules for your new grammar, have a browse through 
 * the $LUMINOUS_ROOT/languages/ directory to pick up how it works, 
 * or keep reading (preferably both).
 * 
 * We'll demonstrates a simple C grammar here. 
 * 
 * A grammar object will subclass the LuminousGrammar class, so that's where 
 * you start:
 * 
 * 
 * \code
class MyAwesomeCGrammar extends LuminousGrammar
{
  public function __construct() 
  {

  } 
}
\endcode
 * 
 * 
 * All code will go into the constructor. To begin with, set some meta-data for 
 * the grammar. 
 * 
 * 
  \code
$this->SetInfoLanguage('c'); // The language language the grammar matches

$this->SetInfoVersion('0.1a'); // a for awesome

///// do you have some webspace specifically for your grammar(s)? put it here!
$this->SetInfoWebsite('http://www.theawesomestcgrammarever.com'); 

$this->SetInfoAuthor(  
        array('name'=> 'your name', 
              'email'=>'your email',
              'website'=>'your website') 
);
      \endcode 
 
 *
 * Only language is actually required. The author attribute is basically
 * for vanity. 
 * 
 * \note Language is restricted to a-z characters as it is used in the CSS 
 *      output. For C++, use cpp. For C#, use cs, and so on.
 * 
 * 
 * 
 * The first thing to consider are delimited types. These are things like 
 * strings, comments, etc. These are stored in LuminousGrammar::$delimited_types,
 * which is an array, and they are represented using LuminousDelimiterRule 
 * objects. To create a simple rule to match Java/C++ style comments, strings 
 * and characters.
 * 
 * \code 
$this->delimited_types = array(
  new LuminousDelimiterRule(0, 'COMMENT', 0, '//', "\n"),
  new LuminousDelimiterRule(0, 'COMMENT', 0, '/*', '* /')
  new LuminousDelimiterRule(0, 'STRING', 0, '"', '"'),
  new LuminousDelimiterRule(0, 'CHARACTER', LUMINOUS_REGEX|LUMINOUS_COMPLETE, "/'(\\\\)?.'/"),
);
 * \endcode
 * 
 * \note the second rule has a space between the * and the /, only because it
 * would otherwise terminate the comment I'm writing in to now! (remove the 
 * space yourself)
 * 
 * In order, the arguments are: Verbosity, type name, flags, opening deimiter,
 * and closing delimiter. Don't worry about verbosity, \ref verbosity for 
 * now but read it later.
 * 
 *  Flags (a
 * \ref LuminousRuleFlag) determines behaviour; we only want a simple substring
 * match so we pass none. The last rule is a little more complicated;
 * it's a regex match so we pass LUMINOUS_REGEX (the regex is a little cryptic
 * but should just match 1-2 chars between single quotes, where the first char
 * must be either a backslash or not present). The 5th argument isn't passed 
 * because we specified LUMINOUS_COMPLETE, which means the match is a full type; 
 * the ending deimiter is already handled within the pattern. We have to match
 * characters here, otherwise a string like:
 * 
 * \code char s = '"'; \endcode would cause problems as the parser would think
 * the nested double quote was the beginning of a string type.
 * 
 * 
 * Next we want to fill up the LuminousGrammar::$simple_types array. Simple 
 * types are basically word matches. They aren't delimited, they're just 
 * standalone symbols. Things like keywords, standard library function names,
 * number literals, etc. The easiest way to do this is to fill up the four
 * inherited member arrays (the latter is filled with common operator symbols 
 * by default): 
 * 
 * \li LuminousGrammar::$types 
 * \li LuminousGrammar::$functions 
 * \li LuminousGrammar::$keywords 
 * \li LuminousGrammar::$operators, 
 * 
 * and then call the inherited method LuminousGrammar::SetSimpleTypeRules.
 *
 * \note LuminousGrammar::SetSimpleTypeRules also sets a generic numerical rule,
 * which is defined in LuminousGrammar::$numeric_regex. You may override this if 
 * it needs to be changed, or set it to \c null if you do not want want it.
 * 
 * \warning These arrays are never read by Luminous, they are just provided 
 * as a convenient way to define lists of data. If their definition is not 
 * followed by a call to LuminousGrammar::SetSimpleTypeRules, they will have no 
 * effect.
 * 
 * \warning These lists are 'compiled' into a lot of individual regular 
 *      expressions by a LuminousSimpleRuleList object. You don't need to worry 
 *      about this, now, but if you want more advanced behaviour you should be 
 *      aware of this.The template which it uses is one of: 
 *      LuminousGrammar::$type_regex,  LuminousGrammar::$function_regex, 
 *      LuminousGrammar::$keyword_regex, LuminousGrammar::$operator_pattern. 
 *      You are free to override these if you need.
 * 
 * If you do it like this, each defaults to a regex so you need to escape 
 * characters as appropriate. If you can get by on a simple substring match, you 
 * will need to populate each of the $simple_types array yourself and set each
 * rule's flags appropriately.
 * 
 * A C simple_types might look like this:
 * 
 * \code
 * 
$this->keywords = array('break', 'continue', 'else', 'for', 'switch', 'case', 
  'default', 'goto', 'typedef', 'do', 'if', 'return', 'static', 'while');

$this->types = array('double', 'enum', 'float', 'int', 'short', 'struct', 
  'unsigned', 'long', 'signed', 'void', 'char', 'union', 'const');

$this->functions = array('sizeof', 'malloc', 'free');

$this->SetSimpleTypeRules();

 \endcode
 
 * And that's all for the C grammar; it's now in a state to be used by Luminous.
 * 
 * If you have any additional types you want to match, append a rule into 
 * LuminousGrammar::$simple_types, like so, to roughly match a PHP variable:
 * 
 * \code
$this->simple_types[] = new LuminousSimpleRule(3, 'VARIABLE', LUMINOUS_REGEX, 
  "/\\$[a-z0-9_]+/i"); 
 * \endcode
 * 
 * Finally, you might wish to specify the language may be nested. If so, make 
 * use of the LuminousGrammar::$ignore_outside array. This is an array of 
 * \link LuminousDelimiterRule LuminousDelimiterRules\endlink just like 
 * LuminousGrammar::$delimited_types. After setting the valid delimiter ranges 
 * for the language, everything outside of that may wish to be parsed as a 
 * different language. If so, set the LuminousGrammar::$child_grammar as 
 * a corresponding instance of LuminousGrammar.
 * 
 * 
 * If you want to use this with LuminousEasyAPI (you do), you'll need to do 
 * just one more thing. After your application includes luminous.php you will 
 * want to add your grammar into the global list of 
 * grammars. First you need to save your grammar inside its own file 
 * (if you write a bunch, you may put them all inside the same file. %Luminous
 * just needs to know where it can find them), like so:
 * 
\code
$luminous_grammars->AddGrammar(array('c', 'h'), 'MyAwesomeCGrammar', 'C',
  '/path/to/yourcgrammar.php');
\endcode
 * 
 * 
 * \li The first argument is a string or array of strings, any of which can be 
 * used as the code to select this grammar in the LuminousEasyAPI::luminous call.
 * This is the language_code.
 * \li The second argument is the name of the class of your grammar. %Luminous
 * will instantiate it if and when it's needed (someone with 100 grammars 
 * installed doesn't want them all being instantiated on every page load if 
 * only one of them is to be used).
 * \li The third argument is a text description of the language. C is already
 * pretty descriptive, but consider a language_code like hs or m
 * (haskell, matlab), which aren't necessarily obvious; this is a human-readable
 * description.
 * \li The fourth argument is optional (default==null); the path to the file 
 * that defines the grammar 
 * (php needs to know about this when %Luminous tries to instantiate it)
 * %Luminous will include this if and when needed. If you don't set this (or
 * leave it as null), make sure your class is somewhere in the global scope at
 * runtime.
 * \li An optional 5th argument to allows you to 
 * define dependency relationships, e.g. if you now write a C++ grammar and
 * choose to subclass it from your C grammar and save them in different files, 
 * then you might do the following to ensure that the path to your C grammar 
 * is included before your C++ grammar is instantiated:
 * 
\code  
 $luminous_grammars->AddGrammar(array('cpp', 'hpp'), 'MyAwesomeCppGrammar', 'C++',
 '/path/to/yourcppgrammar.php', 'c');
\endcode

 * 
 * Thus a complete code listing for using your C grammar would be:
\code
<?php

include ('./luminous/luminous.php');

class MyAwesomeCGrammar extends LuminousGrammar
{
  public function __construct() 
  {
    $this->SetInfoLanguage('c'); // The language language the grammar matches
    $this->SetInfoVersion('0.1a'); // a for awesome
    $this->SetInfoWebsite('http://www.theawesomestcgrammarever.com'); 
    $this->SetInfoAuthor(
      array('name'=> 'your name', 
           'email'=>'your email',
           'website'=>'your website') 
    );
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'COMMENT', 0, '//', "\n"),
      new LuminousDelimiterRule(0, 'COMMENT', 0, '/*', '* /'),                  
      new LuminousDelimiterRule(0, 'STRING', 0, '"', '"'),                      
      new LuminousDelimiterRule(0, 'CHARACTER', LUMINOUS_REGEX|LUMINOUS_COMPLETE, "/'(\\\\)?.'/"),
    );
    
    $this->keywords = array('break', 'continue', 'else', 'for', 'switch', 'case', 
      'default', 'goto', 'typedef', 'do', 'if', 'return', 'static', 'while');
    
    $this->types = array('double', 'enum', 'float', 'int', 'short', 'struct', 
      'unsigned', 'long', 'signed', 'void', 'char', 'union', 'const');
    
    $this->functions = array('sizeof', 'malloc', 'free');
    
    $this->SetSimpleTypeRules();
  }  
}


$luminous_grammars->AddGrammar(array('c', 'h'), 'MyAwesomeCGrammar', 'C');


$src_to_highlight = <<<EOF
include <stdio.h>
int main()
{
  printf("hello, world");
  return 0;
}
EOF;

echo luminous('c', $src_to_highlight);

\endcode
 * 
 * 
 * 
 * Hopefully now you're in a position to begin writing grammars for yourself.
 * 
 * 
 * 
 * Some notes:
 * <ul>
 * <li> %Luminous converts line endings to Unix mode, by converting DOS endings,
 *      \c \\r\\n, and then Mac endings, \c \\r. Don't worry about using 
 *      regexes just to match end-of-lines, use \c \\n
 * </li>
 * <li>
 *     Avoiding regular expressions isn't always as fast as you would expect.
 *     Depending on the flags you set, Luminous has to do various things before
 *     it can accept a string as being a 'match' for a rule. This is done in 
 *     PHP (obviously), whereas if you can do it all in a regex, the PCRE 
 *     engine may be considerably faster than the PHP interpretor. Index based
 *     string iteration in PHP seems painfully slow.
 *
 * Therefore you might find a  LUMINOUS_COMPLETE|LUMINOUS_REGEX is faster than 
 *     passing a delimiter rule with two non-regex delimiter patterns. 
 *     Experiment! (but remember to handle escaping)
 * </li>
 * <li> If a rule seems to have no effect or at some point suddenly ceases to
 *      have an effec, check it's not possible to capture 
 *      zero length matches. If this happens, %Luminous considers the rule to be
 *      broken (because essentially, it doesn't progress the string traversal,
 *      so such a rule as given equates to an infinte loop), and discards it.
 * </li>
 * <li>
 *      Feel free to pile on a lot of rules into the delimited_types array. 
 *      %Luminous does traverse the input string (obviously) and at each 
 *      iteration it does have to run a search to figure out where the next 
 *      match is, BUT, the results of these are cached, and as soon as no 
 *      matches are found in the remainder of the string, the rule is discarded.
 *      That means if you want to apply 20 different rules to match slightly 
 *      different doc comment standards, e.g. \c /**, \c /**<, \c ///, \c //!, 
 *      \c //*!, \c ///<, then feel free; 19 of them will probably be 
 *     discarded on the first iteration.
 * </li>
 * <li> If you need stateful rules, see \ref stateful_grammars </li>
 * </ul>
 * 
 * I recommend you now have a quick look over \ref halp to avoid some common
 * pitfalls.
 * 
 * \sa LuminousGrammar
 * \sa LuminousSimpleRule
 * \sa LuminousSimpleRuleList
 * \sa LuminousDelimiterRule
 * 
 * \sa LuminousRuleFlag
 * 
 * For exhaustive implementation examples, see the languages/ directory.
 * 
 */ 
 
 
/**
 * \page stateful_grammars Stateful parsing
 * 
 * As of 0.5.0, Luminous supports a simple stateful parsing model. This should be
 * treated as a experimental, unstable feature (i.e. it's likely to both change
 * in future and break at the moment).
 * 
 * 
 * What's the difference? Well usually, %Luminous has just one level of matching.
 * Either a string matches or it doesn't. This is sufficient for the vast 
 * majority of languages. It doesn't matter whether an 'if' appears in main(),
 * or in a switch, while, for, etc, it's still an 'if'. %Luminous won't detect
 * it inside a string because there's only one level of matching and the string
 * is occupying it. Not worrying about statefulness is preferable for
 * most languages because it's less complicated.
 * 
 * But a select few languages' recognition benfit greatly from knowing more 
 * about symbols' context. e.g. in CSS, if alphanumeric characters are encounted
 * it's probably a rule definition. Unless it's inisde a {} block, in which case
 * it's probably a property name. Unless it's after a ':', in which case it's
 * probably a value. This is where statefulness comes in. CSS will be used as
 * an example here.
 * 
 * Using stateful mode essentially makes %Luminous's a pushdown automota (with 
 * a stack), so states can be considered hierarchial, like a tree.
 * 
 * To tell %Luminous to use stateful mode, you need to define some states and 
 * some transitions.
 * 
 * States are defined in the LuminousGrammar::delimited_types array, in the 
 * form of LuminousDelimiterRule. 
 * 
 * Consider a LuminousGrammar instance that has the following in its 
 * constructor method.
 * 
\code
$this->delimited_types = array(  


  new LuminousDelimiterRule(0, 'RULE', LUMINOUS_REGEX|LUMINOUS_COMPLETE|LUMINOUS_STOP_AT_END,
    '/[a-z0-9_\.#]+/i'
  ),

  new LuminousDelimiterRule(0, 'BLOCK', LUMINOUS_STOP_AT_END, '{', '}'),
  
  new LuminousDelimiterRule(0, 'PROPERTY', LUMINOUS_REGEX|LUMINOUS_COMPLETE|LUMINOUS_STOP_AT_END,
  '/[a-z\-@]+(?=:)/is'),
  
  new LuminousDelimiterRule(0, 'VALUE', LUMINOUS_REGEX|LUMINOUS_STOP_AT_END,
  '/(?=:)/', '/(?=[;\\}])/'), 
  
  new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
  '%/\*(?:.*?)\* /%sx', null, 'luminous_type_callback_comment'),
  
  new LuminousDelimiterRule(0, 'STRING', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
  '%([\'"]).*?(?<!\\\\)(?:\\\\\\\\)*\\1%s'),
    
);
\endcode

 * here we have a simple CSS ruleset definition. Note the element 'block' is 
 * not given as a LUMINOUS_COMPLETE. This is simply because the ending delimiter,
 * }, might appear inside a string or comment. Separating the rule into two
 * makes %Luminous treat the rule's end as 'indefinite' and will evaluate it
 * when it can.
 * 
 * \warning Note all the LUMINOUS_STOP_AT_ENDs. This is for embedded CSS:
 * if someone writes \<style\> body {font-size: 29; \</style\> then you don't
 * want to ignore the closing style tag just because the block {.. wasn't
 * terminated.
 * 
 * So far, this is no different to using %Luminous's grammars normally. To make
 * %Luminous invoke statefulness, we need to define the transition table:
 * 
\code
$this->state_transitions = array(
  'GLOBAL' => array('RULE', 'COMMENT', 'STRING', 'BLOCK'),
  'COMMENT' => null,
  'STRING' => null,
  'BLOCK' => array('COMMENT', 'STRING', 'VALUE', 'PROPERTY'),
  'VALUE' => array('STRING', 'COMMENT'),
  'RULE' => null,
  'PROPERTY' => array('COMMENT'),
  );
\endcode
  *
  * Note the 'GLOBAL' state. We don't explicitly define this, but it's what
  * %Luminous is in when its stack is empty, so we need some transitions from 
  * it. The syntax of the transition table should be obvious. Instead of 
  * array(...) or null, we can also use '*' or '!' to mean 'every state', and
  * 'every OTHER state (excluding myself)', respectively.
  * 
  * Finally, we can map state names to actual classes for highlighting, i.e.
  * things like 'RULE' and 'PROPERTY' are words specific to CSS, so instead
  * we probably want to map those to 'KEYWORD' and 'TYPE', which are generic
  * identifiers %Luminous uses.  Also, 'BLOCK' isn't
  * something we really want tagged, it's only a state, not a highlighting 
  * class. We call this a dummy state, which we can specify in the mappings
  * by mapping it to null.
\code
$this->state_type_mappings = array(
    'RULE' => 'KEYWORD',
    'PROPERTY'=>'TYPE'
    'BLOCK' => null,
  );
\endcode
  * 
  * 
  * \warning Test your grammars! This is a new and unstable feature, and if 
  * you mess up your rules, %Luminous might not respond elegantly.
  * 
  * 
  * Notes:
  * \li Some flags might not work, but the basic ones do
  * \li The callback function given in the rule is still executed so if you
  *     prefer, you can still use a callback to evaluate things like escape
  *     sequences or documentation comment formats (which might be easier or
  *     faster).
  * \li The LuminousGrammar::$simple_types array is still read and evaluated so
  * you can mix and match stateful delimiter rules with more simplistic word
  * level rules. 
  * 
  * 
  * \see cpp_stateful an example of C++ implemented in a stateful manner
  * 
  * 
  * 
*/
 
/**
 * \page grammar_optimisation Optimising your rules
 * 
 * On the keyword-type rules where you tend to match a list of simple regexes 
 * wrapped by some kind of lookahead/behind assertion to check adjacent 
 * characters, I have found that the fastest way to arrange your expressions
 * is in the form:
 * 
 * rule1 = '/(?<=..) c(?:atch|ase|ontinue) (?=...)/';
 * 
 * rule2 = '/(?<=..) d(?:efault|o) (?=...)/';
 * 
 * rule3 = '/(?<=..) if (?=...)/';
 * 
 * rule4 = '/(?<=..) else (?=...)/';
 * 
 * rule5 = /(?<=..) f(?:or|unction) (?=...)/';
 * 
 * etc.
 * 
 * For those interested, I have benchmarked several arrangements, the 
 * results of which follows.
 * 
 * These were done on C++ code using the full C++ keyword-set (not the stripped
 * down set shown here for brevity).
 * The tests were repeated 100 times on a 50KiB input source. The assertions 
 * used were: <code>(?<![a-zA-Z0-9_])</code> and <code>(?![a-zA-Z0-9_])</code>
 * 
 * Method1: Sequential simple calls
 \code
 preg_replace("/(?<=..) catch (?=...)/" ...); 
 preg_replace("/(?<=..) case (?=...)/" ...); 
 preg_replace("/(?<=..) continue (?=...)/" ...); 
 ... \endcode 
 Execution time: 1.69s
 
 Method2: One complex call
 \code
 $s = preg_replace("/(?<=..) catch|case|continue|default|...|function (?=...)/" ...);  \endcode
 Execution time: 7.40s
 
 Method 3: (method#2 split alphabetically)
 \code
 preg_replace("/(?<=..) catch|case|continue (?=...)/"...)
 preg_replace("/(?<=..) default|do (?=...)/")
 ... \endcode
 Execution time: 0.800s
 
 Method 4:  a tree built to reduce redundancy
 \code
 preg_replace("/(?<=..) c(?:a(?:se|tc)|ontinue)|d(?:efault|o).....|  \endcode
 Execution time: 3.48s 
 
 Method 5: (method#4 split alphabetically)
 \code
 preg_replace("/(?<=..) c(?:a(?:se|tc)|ontinue) (?=...)"/...
 preg_replace("/(?<=..) d(?:efault|o) (?=...)"/...
 ... \endcode
 Execution time: 0.815s
 
 Method 6: A simplified tree (depth=2), split alphabetically
 \code
 preg_replace("/(?<=..) c(?:atch|ase|ontinue) (?=...)"/...
 preg_replace("/(?<=..) d(?:efault|o) (?=...)"/...
 ... \endcode
 Execution time: 0.671s
 
 As you can see, the difference between methods 3, 5 and 6 is quite minimal
 and unlikely to make a measurable effect in actual usage of %Luminous. Method 3
 is probably the most readable.
 
 However, this also shows that using the simpler methods can introduce a 
 very measurable and unnecessary bottleneck. Presumably, the PCRE engine is able
 to make some significant optimisations when the data are entered as in methods
 3, 5 and 6. Methods 1, 2 and 4 should be avoided and rules rewritten if 
 necessary.
 
 */ 


 
 /**
  * \page halp HELP! My rules aren't working/are breaking %Luminous
  * 
  * 
  * \image html halp.jpg
  * 
  * 
  * 
  * So you've got problems, have you.
  * 
  * 
  * 
  * 
  * Writing your own grammar is fairly easy, but here are some points you 
  * need to watch out for:
  * 
  * 
  *   
  *      %Luminous doesn't operate on the exact input string it receives. It uses
  *      its own internal specification for inserting meta data into the string
  *      (basically HTML/XML-like tagging of sections of string)
  *      As a result, Some characters in the input string are escaped to html 
  *      entity codes:  
  *      <ol>
  *      <li> \c \& => \c \&amp; </li> 
  *      <li> \c < => \c \&lt; </li>
  *      <li> \c > => \c \&gt; </li>
  *      </ol>
  *      You should match these instead, or you'll start attacking Luminous's 
  *      meta data. 
  * 
  *     <h2> Internal Data </h2>
  * 
  *     Sloppy regex rules can cause problems. Luminous's meta 
  *     data which appears in matchable sections of string looks like the 
  *     following: <code> &lt;\&R_[0-9]+&gt; </code> and <code> &lt;\&WRAP&gt; 
  *     </code> for word wrap markers.
  * 
  *     The former is a place-holder for a previously matched piece of string.
  *     This is good in that you can easily match keywords without worrying
  *     about matching the word 'if' in a comment or a string, but it's bad in
  *     that you have to work around things like <code> &lt;\&R_21&gt;. </code>
  *     This sounds needlessly complicated, but in theory at least, 
  *     <b>accurate rules will not match Luminous's tagging system </b> and no 
  *     special attention needs to be given to avoiding the tags. 
  * 
  *     
  *     To illustrate, one might sloppily define a numerical match as:
  *     <code>/[0-9]+/</code>. This is wrong anyway because it will match in a 
  *     variable like \c $h3llo, but it will also target the tagging system's 
  *     'R' tag. 
  * 
  *     The solution is to use lookahead/behind assertions to check for legal 
  *     adjacent characters. This is the default in the LuminousGrammar
  *     regex templates. Even so, you need to be a bit careful with your
  *     own numeric regexes because one such as:
  * 
  * <code> /(?<![[:alpha:]_])  [0-9]+   /x</code>,
  *    
  * is wrong. Why is it wrong? It looks okay, it's got the negative lookbehind 
  *  assertion in the first bracket, hasn't it? Yes, but it only checks for 
  * [a-zA-Z]. It will match in something like: <code>$variable21</code> and  
  * <code>&lt;\&R_21&gt;</code>; the '2' will be fine but the '1' will be 
  * caught. This will split up the meta-tag and make it 
  * unrecognisable to Luminous when it comes to do all the substitutions later,
  * so a literal  <code> &lt;\&R_21&gt; </code> will be left in (with the final
  * '1' highlighted as a number). Therefore the regex should be:
  * 
  * <code> / (?<![[:alnum:]_])  [0-9]+   /x </code>,
  * 
  *     Don't worry about meta-tags getting caught up inside delimiter rules. 
  *     That's fine and expected. The problems only start when you make a rule 
  *     which matches \em part of a tag and thereby splits it up such that it 
  *     is unrecognisable to %Luminous.
  * 
  *     This of course looks like a horrible hack and to some extent that's
  *     correct. It greatly complicates the internals of Luminous and it 
  *     can be a pain to write a language definition. The reason that it's 
  *     done is performance: While everything could be identified in a single
  *     pass, it's painfully slow. By hiding things we can offload most of the
  *     hard work to the inbuilt PCRE functions. The reduction in required 
  *     runtime is something like 3x, so it is worth it.
  * 
  *     If you trigger something like this, %Luminous will spit out a warning
  * 
  * 
  * 
  * 
  * 
  * <h2>The matching process</h2>
  * 
  * Matching comes in two phases. First there's the delimited types, then 
  * there's the simple types. The reason for separating the two is mainly 
  * performance, but they represent conceptually very different things too. 
  * 
  * The order in which matches occurs is important. Anything which has been 
  * matched by one rule may not be matched again by another rule. Therefore
  * given a rule A, which needs to rely on reading but not capturing contextual
  * data matched by a different rule, B, rule A must be executed first. Rules
  * are executed in the order they are defined in their respective arrays. If 
  * you use LuminousGrammar::SetSimpleTypeRules to populate your simple_types 
  * array, the order is:
  * 
  * \li numeric, 
  * \li types, 
  * \li keywords, 
  * \li functions, 
  * \li operators.
  * 
  * If you need a different order you will have to populate the array yourself.
  * 
  * 
  * 
  * Making captured string segments unmatchable is to prevent unwanted nesting:
  * you don't want to match the word 'if' in: 
  * 
  * <code>
  *  // x redefined to z if y == TRUE
  * 
  * $x = ($y)? $z : $x;
  * </code>,
  * 
  * and you \em certainly don't want to match the single quote in:
  * 
  * <code>
  * // I'm not sure what this number is
  * 
  * $x = 28;
  * </code>,
  * 
  * as it will cascade throughout the rest of the source and invert your string
  * matching (very ugly!)
  * 
  * But on occasions you may \em want nesting. Situations
  * include highlighting doc comment tags (doxygen, javadoc, etc) and 
  * highlighting variables in string interpolation. 
  *
  * This currently needs to be done via a callback function bound to the 
  * specific rule, \ref LuminousDelimiterRule::__construct, and the logic must 
  * be implemented yourself. This is a permanent solution: There are no plans to
  * move this inside Luminous, because it seems too complicated for the sake of
  * a line or two of preg_replace, and for the sake of types which are matched 
  * frequently, it would be a performance drain to be instantiating new 
  * Luminous objects to handle 20 characters.
  * 
  * 
  */


?>
