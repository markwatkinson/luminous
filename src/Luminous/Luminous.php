<?php

namespace Luminous;

use Luminous as LuminousUi;
use Luminous\Core\Scanner;
use Luminous\Caches\SqlCache;
use Luminous\Caches\FileSystemCache;
use Luminous\Formatters\Formatter;
use Luminous\Formatters\HtmlFormatter;
use Luminous\Formatters\InlineHtmlFormatter;
use Luminous\Formatters\FullPageHtmlFormatter;
use Luminous\Formatters\LatexFormatter;
use Luminous\Formatters\AnsiFormatter;
use Luminous\Formatters\IdentityFormatter;

/**
 * @cond ALL
 *  This is kind of a pseudo-UI class. It's a singleton which will be
 * manipulated by a few procedural functions, for ease of use.
 * It's technically supposed to be private to this class, but this is a sort
 * of 'agreed' privateness, and we expose an instance of it globally.
 *
 * In fact, it is used by (at least) the diff scanner, which uses its
 * scanner table.
 *
 * @internal
 */
class Luminous
{
    /**
     * Settings array
     * @see LuminousOptions
     */
    public $settings;

    /**
     * the scanner table
     */
    public $scanners;

    public $cache = null;

    /**
     * The language is passed to the formatter which may choose to do something
     * interesting with it. If you use the scanners table this can be figured out
     * automatically, but if you pass in your own scanner, you will need to
     * give a language name if you want the formatter to consider it.
     */
    public $language = null;

    public function __construct()
    {
        $this->scanners = new Scanners();
        $this->registerDefaultScanners();
    }

    /**
     * registers builtin scanners
     */
    private function registerDefaultScanners()
    {
        $this->settings = new Options();

        $this->scanners->AddScanner(array('ada', 'adb', 'ads'), 'Luminous\\Scanners\\AdaScanner', 'Ada');
        $this->scanners->AddScanner(
            array('as', 'actionscript'),
            'Luminous\\Scanners\\ActionScriptScanner',
            'ActionScript'
        );
        $this->scanners->AddScanner(array('bnf'), 'Luminous\\Scanners\\BnfScanner', 'Backus Naur Form');
        $this->scanners->AddScanner(array('bash', 'sh'), 'Luminous\\Scanners\\BashScanner', 'Bash');
        $this->scanners->AddScanner(
            array('c', 'cpp', 'h', 'hpp', 'cxx', 'hxx'),
            'Luminous\\Scanners\\CppScanner',
            'C/C++'
        );
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
    }

    /**
     * Returns an instance of the current formatter
     */
    public function getFormatter()
    {
        $fmt = $this->settings->format;
        $formatter = null;
        if (!is_string($fmt) && is_subclass_of($fmt, 'Luminous\\Formatters\\Formatter')) {
            $formatter = clone $fmt;
        } elseif ($fmt === 'html') {
            $formatter = new HtmlFormatter();
        } elseif ($fmt ===  'html-inline') {
            $formatter = new InlineHtmlFormatter();
        } elseif ($fmt ===  'html-full') {
            $formatter = new FullPageHtmlFormatter();
        } elseif ($fmt === 'latex') {
            $formatter = new LatexFormatter();
        } elseif ($fmt === 'ansi') {
            $formatter = new AnsiFormatter();
        } elseif ($fmt ===  null || $fmt === 'none') {
            $formatter = new IdentityFormatter();
        }

        if ($formatter === null) {
            throw new Exception('Unknown formatter: ' . $this->settings->format);
            return null;
        }
        $this->setFormatterOptions($formatter);
        return $formatter;
    }

    /**
     * Sets up a formatter instance according to our current options/settings
     */
    private function setFormatterOptions(&$formatter)
    {
        $formatter->wrapLength = $this->settings->wrapWidth;
        $formatter->lineNumbers = $this->settings->lineNumbers;
        $formatter->startLine = $this->settings->startLine;
        $formatter->link = $this->settings->autoLink;
        $formatter->height = $this->settings->maxHeight;
        $formatter->strictStandards = $this->settings->htmlStrict;
        $formatter->setTheme(LuminousUi::theme($this->settings->theme));
        $formatter->highlightLines = $this->settings->highlightLines;
        $formatter->language = $this->language;
    }

    /**
     * calculates a 'cache_id' for the input. This is dependent upon the
     * source code and the settings. This should be (near-as-feasible) unique
     * for any cobmination of source, language and settings
     */
    private function cacheId($scanner, $source)
    {
        // to figure out the cache id, we mash a load of stuff together and
        // md5 it. This gives us a unique (assuming no collisions) handle to
        // a cache file, which depends on the input source, the relevant formatter
        // settings, the version, and scanner.
        $settings = array(
            $this->settings->wrapWidth,
            $this->settings->lineNumbers,
            $this->settings->startLine,
            $this->settings->autoLink,
            $this->settings->maxHeight,
            $this->settings->format,
            $this->settings->theme,
            $this->settings->htmlStrict,
            LUMINOUS_VERSION,
        );

        $id = md5($source);
        $id = md5($id . serialize($scanner));
        $id = md5($id . serialize($settings));
        return $id;
    }

    /**
     * The real highlighting function
     * @throw InvalidArgumentException if $scanner is not either a string or a
     *    LuminousScanner instance, or if $source is not a string.
     */
    public function highlight($scanner, $source, $settings = null)
    {
        $oldSettings = null;
        if ($settings !== null) {
            if (!is_array($settings)) {
                throw new Exception('Luminous internal error: Settings is not an array');
            }
            $oldSettings = clone $this->settings;
            foreach ($settings as $k => $v) {
                $this->settings->set($k, $v);
            }
        }
        $shouldResetLanguage = false;
        $this->cache = null;
        if (!is_string($source)) {
            throw new InvalidArgumentException('Non-string supplied for $source');
        }

        if (!($scanner instanceof Scanner)) {
            if (!is_string($scanner)) {
                throw new InvalidArgumentException('Non-string or LuminousScanner instance supplied for $scanner');
            }
            $code = $scanner;
            $scanner = $this->scanners->GetScanner($code);
            if ($scanner === null) {
                throw new Exception("No known scanner for '$code' and no default set");
            }
            $shouldResetLanguage = true;
            $this->language = $this->scanners->GetDescription($code);
        }
        $cacheHit = true;
        $out = null;
        if ($this->settings->cache) {
            $cacheId = $this->cacheId($scanner, $source);
            if ($this->settings->sqlFunction !== null) {
                $this->cache = new SqlCache($cacheId);
                $this->cache->setSqlFunction($this->settings->sqlFunction);
            } else {
                $this->cache = new FileSystemCache($cacheId);
            }
            $this->cache->setPurgeTime($this->settings->cacheAge);
            $out = $this->cache->read();
        }
        if ($out === null) {
            $cacheHit = false;
            $outRaw = $scanner->highlight($source);
            $formatter = $this->getFormatter();
            $out = $formatter->format($outRaw);
        }

        if ($this->settings->cache && !$cacheHit) {
            $this->cache->write($out);
        }
        if ($shouldResetLanguage) {
            $this->language = null;
        }
        if ($oldSettings !== null) {
            $this->settings = $oldSettings;
        }
        return $out;
    }
}

/** @endcond */
