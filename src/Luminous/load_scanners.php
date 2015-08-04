<?php

/*
 * This is a horrible routine to register all the default
 * scanners. The code is distracting at best so it's been factored into this one
 * file.
 *
 * We include it into the main program with a require statement, which
 * due to the literal way PHP includes work, when done within a function gives
 * us access to that function's scope.
 * We are in the scope of a method inside the Luminous_ object, so we refer to
 * $this as being the $luminous_ singleton object.
 */

$this->scanners->AddScanner(array('ada', 'adb', 'ads'), 'Luminous\\Scanners\\AdaScanner', 'Ada');

$this->scanners->AddScanner(array('as', 'actionscript'), 'Luminous\\Scanners\\ActionScriptScanner', 'ActionScript');

$this->scanners->AddScanner(array('bnf'), 'Luminous\\Scanners\\BnfScanner', 'Backus Naur Form');

$this->scanners->AddScanner(array('bash', 'sh'), 'Luminous\\Scanners\\BashScanner', 'Bash');

$this->scanners->AddScanner(array('c', 'cpp', 'h', 'hpp', 'cxx', 'hxx'), 'Luminous\\Scanners\\CppScanner', 'C/C++');

$this->scanners->AddScanner(array('cs', 'csharp', 'c#'), 'Luminous\\Scanners\\CSharpScanner', 'C#');

$this->scanners->AddScanner('css', 'Luminous\\Scanners\\CssScanner', 'CSS');

$this->scanners->AddScanner(array('diff', 'patch'), 'Luminous\\Scanners\\DiffScanner', 'Diff');

$this->scanners->AddScanner(
    array('prettydiff', 'prettypatch', 'diffpretty', 'patchpretty'),
    'Luminous\\Scanners\\PrettyDiffScanner',
    'Diff-Pretty'
);

$this->scanners->AddScanner(array('html', 'htm'), 'Luminous\\Scanners\\HtmlScanner', 'HTML');

$this->scanners->AddScanner(array('ecma', 'ecmascript'), 'Luminous\\Scanners\\EcmaScriptScanner', 'ECMAScript');

$this->scanners->AddScanner(array('erlang', 'erl', 'hrl'), 'Luminous\\Scanners\\ErlangScanner', 'Erlang');

$this->scanners->AddScanner('go', 'Luminous\\Scanners\\GoScanner', 'Go');

$this->scanners->AddScanner(array('groovy'), 'Luminous\\Scanners\\GroovyScanner', 'Groovy');

$this->scanners->AddScanner(array('haskell', 'hs'), 'Luminous\\Scanners\\HaskellScanner', 'Haskell');

$this->scanners->AddScanner('java', 'Luminous\\Scanners\\JavaScanner', 'Java');

$this->scanners->AddScanner(array('js', 'javascript'), 'Luminous\\Scanners\\JavaScriptScanner', 'JavaScript');

$this->scanners->AddScanner('json', 'Luminous\\Scanners\\JsonScanner', 'JSON');

$this->scanners->AddScanner(array('latex', 'tex'), 'Luminous\\Scanners\\LatexScanner', 'LaTeX');

$this->scanners->AddScanner(array('lolcode', 'lolc', 'lol'), 'Luminous\\Scanners\\LolcodeScanner', 'LOLCODE');

$this->scanners->AddScanner(array('m', 'matlab'), 'Luminous\\Scanners\\MatlabScanner', 'MATLAB');

$this->scanners->AddScanner(array('perl', 'pl', 'pm'), 'Luminous\\Scanners\\PerlScanner', 'Perl');

$this->scanners->AddScanner(array('rails','rhtml', 'ror'), 'Luminous\\Scanners\\RailsScanner', 'Ruby on Rails');

$this->scanners->AddScanner(array('ruby','rb'), 'Luminous\\Scanners\\RubyScanner', 'Ruby');

$this->scanners->AddScanner(array('plain', 'text', 'txt'), 'Luminous\\Scanners\\IdentityScanner', 'Plain');

// PHP Snippet does not require an initial <?php tag to begin highlighting
$this->scanners->AddScanner('php_snippet', 'Luminous\\Scanners\\PhpSnippetScanner', 'PHP Snippet');

$this->scanners->AddScanner('php', 'Luminous\\Scanners\\PhpScanner', 'PHP');

$this->scanners->AddScanner(array('python', 'py'), 'Luminous\\Scanners\\PythonScanner', 'Python');

$this->scanners->AddScanner(array('django', 'djt'), 'Luminous\\Scanners\\DjangoScanner', 'Django');

$this->scanners->AddScanner(array('scala', 'scl'), 'Luminous\\Scanners\\ScalaScanner', 'Scala');

$this->scanners->AddScanner('scss', 'Luminous\\Scanners\\ScssScanner', 'SCSS');

$this->scanners->AddScanner(array('sql', 'mysql'), 'Luminous\\Scanners\\SqlScanner', 'SQL');

$this->scanners->AddScanner(array('vim', 'vimscript'), 'Luminous\\Scanners\\VimScriptScanner', 'Vim Script');

$this->scanners->AddScanner(array('vb', 'bas'), 'Luminous\\Scanners\\VisualBasicScanner', 'Visual Basic');

$this->scanners->AddScanner('xml', 'Luminous\\Scanners\\XmlScanner', 'XML');

$this->scanners->SetDefaultScanner('plain');
