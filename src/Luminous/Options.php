<?php

namespace Luminous;

use Exception;
use InvalidArgumentException;
use Luminous as LuminousUi;
use Luminous\Formatters\Formatter;

/**
 * @cond USER
 *
 * @brief Options class.
 *
 * @warning This object's structure isn't guaranteed to be stable so don't read
 * or write these directly. As a user, you should be using luminous::set()
 * and luminous::setting()
 *
 * We use a fair bit of PHP trickery in the implementation here. The keener
 * among you will notice that the options are all private: don't worry about
 * that. We override the __set() method to apply option specific validation.
 * Options can be written to as normal.
 *
 * The option variable names correspond with option strings that can be passed
 * through luminous::set(), however, for historical reasons, underscores can be
 * replaced with dashed in the call.
 */
class Options
{
    /**
     * @brief Whether to use the built-in cache
     */
    private $cache = true;

    /**
     * @brief Maximum age of cache files in seconds
     *
     * Cache files which have not been read for this length of time will be
     * removed from the file system. The file's 'mtime' is used to calculate
     * when it was last used, and a cache hit triggers a 'touch'
     *
     * Set to -1 or 0 to disable cache purges
     */
    private $cacheAge = 7776000; // 90 days

    /**
     * @brief Word wrapping
     *
     * If the formatter supports line wrapping, lines will be wrapped at
     * this number of characters (0 or -1 to disable)
     */
    private $wrapWidth = -1;

    /**
     * @brief Line numbering
     *
     * If the formatter supports line numbering, this setting controls whether
     * or not lines should be numbered
     */
    private $lineNumbers = true;

    /**
     * @brief Line number of first line
     *
     * If the formatter supports line numbering, this setting controls number
     * of the first line
     */
    private $startLine = 1;

    /**
     * @brief Highlighting of lines
     *
     * If the formatter supports highlighting lines, this setting allows
     * the caller to specify the set of line numbers to highlight
     */
    private $highlightLines = array();

    /**
     * @brief Hyperlinking
     *
     * If the formatter supports hyper-linking, this setting controls whether
     * or not URLs will be automatically linked
     */
    private $autoLink = true;

    /**
     * @brief Widget height constraint
     *
     * If the formatter supports heigh constraint, this setting controls whether
     * or not to constrain the widget's height, and to what.
     */
    private $maxHeight = -1;

    /**
     * @brief Output format
     *
     * Chooses which output format to use. Current valid settings are:
     * @li 'html' - standard HTML element, contained in a \<div\> with class 'luminous',
     *    CSS is not included and must be included on the page separately
     *    (probably with luminous::head_html())
     * @li 'html-full' - A complete HTML document. CSS is included.
     * @li 'html-inline' - Very similar to 'html' but geared towards inline display.
     *    Probably not very useful.
     * @li 'latex' - A full LaTeX document
     * @li 'none' or \c NULL - No formatter. Internal XML format is returned.
     *    You probably don't want this.
     */
    private $format = 'html';

    /**
     * @brief Theme
     *
     * The default theme to use. This is observed by the HTML-full and LaTeX
     * formatters, it is also read by luminous::head_html().
     *
     * This should be a valid theme which exists in style/
     */
    private $theme = 'luminous_light.css';

    /**
     * @brief HTML strict standards mode
     *
     * The HTML4-strict doctype disallows a few things which are technically
     * useful. Set this to true if you don't want Luminous to break validation
     * on your HTML4-strict document. Luminous should be valid
     * HTML4 loose/transitional and HTML5 without needing to enable this.
     */
    private $htmlStrict = false;

    /**
     * @brief Location of Luminous relative to your document root
     *
     * If you use luminous::head_html(), it has to try to figure out the
     * path to the style/ directory so that it can return a correct URL to the
     * necessary stylesheets. Luminous may get this wrong in some situations,
     * specifically it is currently impossible to get this right if Luminous
     * exists on the filesystem outside of the document root, and you have used
     * a symbolic link to put it inside. For this reason, this setting allows you
     * to override the path.
     *
     * e.g. If you set this to '/extern/highlighter', the stylesheets will be
     * linked with
     * \<link rel='stylesheet' href='/extern/highlighter/style/luminous.css'\>
     *
     */
    private $relativeRoot = null;

    /**
     * @brief JavaScript extras
     *
     * controls whether luminous::head_html() outputs the javascript 'extras'.
     */
    private $includeJavascript = false;

    /**
     * @brief jQuery
     *
     * Controls whether luminous::head_html() outputs jQuery, which is required
     * for the JavaScript extras. This has no effect if $include_javascript is
     * false.
     */
    private $includeJquery = false;

    /**
     * @brief Failure recovery
     *
     * If Luminous hits some kind of unrecoverable internal error, it should
     * return the input source code back to you. If you want, it can be
     * wrapped in an HTML tag. Hopefully you will never see this.
     */
    private $failureTag = 'pre';

    /**
     * @brief Defines an SQL function which can execute queries on a database
     *
     * An SQL database can be used as a replacement for the file-system cache
     * database.
     * This function should act similarly to the mysql_query function:
     * it should take a single argument (the query string) and return:
     *    @li boolean @c false if the query fails
     *    @li boolean @c true if the query succeeds but has no return value
     *    @li An array of associative arrays if the query returns rows (each
     *      element is a row, and each row is an map keyed by field name)
     */
    private $sqlFunction = null;

    private $verbose = true;

    public function __construct($opts = null)
    {
        if (is_array($opts)) {
            $this->set($opts);
        }
    }

    public function set($nameOrArray, $value = null)
    {
        $array = $nameOrArray;
        if (!is_array($array)) {
            $array = array($nameOrArray => $value);
        }
        foreach ($array as $option => $value) {
            // for backwards compatibility we need to convert param-case and
            // snake_case to camelCaps here.
            $option = ucwords(str_replace(array('-', '_'), ' ', $option));
            $option = lcfirst(str_replace(' ', '', $option));
            $this->__set($option, $value);
        }
    }

    private static function checkType($value, $type, $nullable = false)
    {
        if ($nullable && $value === null) {
            return true;
        }
        $func = null;
        if ($type === 'string') {
            $func = 'is_string';
        } elseif ($type === 'int') {
            $func = 'is_int';
        } elseif ($type === 'numeric') {
            $func = 'is_numeric';
        } elseif ($type === 'bool') {
            $func = 'is_bool';
        } elseif ($type === 'func') {
            $func = 'is_callable';
        } elseif ($type === 'array') {
            $func = 'is_array';
        } else {
            assert(0);
            return true;
        }

        $test = call_user_func($func, $value);
        if (!$test) {
            throw new InvalidArgumentException('Argument should be type ' . $type . ($nullable ? ' or null' : ''));
        }
        return $test;
    }

    public function __get($name)
    {
        // for bc we need to convert snake_case to camelCaps here.
        $name = ucwords(str_replace('_', ' ', $name));
        $name = lcfirst(str_replace(' ', '', $name));
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            throw new Exception('Unknown property: ' . $name);
        }
    }

    public function __set($name, $value)
    {
        if ($name === 'autoLink') {
            $this->setBool($name, $value);
        } elseif ($name === 'cache') {
            $this->setBool($name, $value);
        } elseif ($name === 'cacheAge') {
            if (self::checkType($value, 'int')) {
                $this->$name = $value;
            }
        } elseif ($name === 'failureTag') {
            if (self::checkType($value, 'string', true)) {
                $this->$name = $value;
            }
        } elseif ($name === 'format') {
            $this->setFormat($value);
        } elseif ($name === 'htmlStrict') {
            if (self::checkType($value, 'bool')) {
                $this->$name = $value;
            }
        } elseif ($name === 'includeJavascript' || $name === 'includeJquery') {
            $this->setBool($name, $value);
        } elseif ($name === 'lineNumbers') {
            $this->setBool($name, $value);
        } elseif ($name === 'startLine') {
            $this->setStartLine($value);
        } elseif ($name === 'highlightLines') {
            if (self::checkType($value, 'array')) {
                $this->highlightLines = $value;
            }
        } elseif ($name === 'maxHeight') {
            $this->setHeight($value);
        } elseif ($name === 'relativeRoot') {
            if (self::checkType($value, 'string', true)) {
                $this->$name = $value;
            }
        } elseif ($name === 'theme') {
            $this->setTheme($value);
        } elseif ($name === 'wrapWidth') {
            if (self::checkType($value, 'int')) {
                $this->$name = $value;
            }
        } elseif ($name === 'sqlFunction') {
            if (self::checkType($value, 'func', true)) {
                $this->$name = $value;
            }
        } elseif ($name === 'verbose') {
            $this->setBool($name, $value);
        } else {
            throw new Exception('Unknown option: ' . $name);
        }
    }

    private function setBool($key, $value)
    {
        if (self::checkType($value, 'bool')) {
            $this->$key = $value;
        }
    }

    private function setString($key, $value, $nullable = false)
    {
        if (self::checkType($value, 'string', $nullable)) {
            $this->$key = $value;
        }
    }

    private function setStartLine($value)
    {
        if (is_numeric($value) && $value > 0) {
            $this->startLine = $value;
        } else {
            throw new InvalidArgumentException('Start line must be a positive number');
        }
    }

    private function setFormat($value)
    {
        // formatter can either be an instance or an identifier (string)
        $isObj = $value instanceof Formatter;
        if ($isObj || self::checkType($value, 'string', true)) {
            // validate the string is a known type
            $formats = array('html', 'html-full', 'html-inline', 'latex', 'ansi', 'none', null);
            if (!$isObj && !in_array($value, $formats, true)) {
                throw new Exception('Invalid formatter: ' . $value);
            } else {
                $this->format = $value;
            }
        }
    }

    private function setTheme($value)
    {
        if (self::checkType($value, 'string')) {
            if (!preg_match('/\.css$/', $value)) {
                $value .= '.css';
            }
            if (!LuminousUi::themeExists($value)) {
                throw new Exception('No such theme: ' . LuminousUi::root() . '/style/' . $value);
            } else {
                $this->theme = $value;
            }
        }
    }

    private function setHeight($value)
    {
        // height should be either a number or a numeric string with some units at
        // the end
        if (is_numeric($value) || (is_string($value) && preg_match('/^\d+/', $value))) {
            $this->maxHeight = $value;
        } else {
            throw new InvalidArgumentException('Unrecognised format for height');
        }
    }
}
/** @endcond */
