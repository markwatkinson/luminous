<?php

/** @cond CORE */

/**
 * @file
 * @brief The base scanner classes
 *
 * This file contains the base scanning classes. All language scanners should
 * subclass one of these. They are all essentially abstract as far as Luminous
 * is concerned, but it may occasionally be useful to instantiate one.
 *
 * The classes defined here follow a simple hierarchy: Scanner defines basic
 * string scanning methods, LuminousScanner extends this with logic useful to
 * highlighting. LuminousEmbeddedWebScript adds some helpers for web languages,
 * which may be nested in other languages. LuminousSimpleScanner is a
 * generic flat scanner which is driven by token rules.
 * LuminousStatefulScanner is a generic transition table driven scanner.
 */

namespace Luminous\Core\Scanners;

use Exception;
use Luminous\Core\Utils;

/**
 * @brief the base class for all scanners
 *
 * LuminousScanner is the base class for all language scanners. Here we
 * provide a set of methods comprising a highlighting layer. This includes
 * recording a token stream, and ultimately being responsible for
 * producing some XML representing the token stream.
 *
 * We also define here some filters which rely on state information expected
 * to be recorded into the instance variables.
 *
 * Highlighting a string at this level is a four-stage process:
 *
 *      @li string() - set the string
 *      @li init() - set up the scanner
 *      @li main() - perform tokenization
 *      @li tagged() - build the XML
 *
 *
 *
 * A note on tokens: Tokens are stored as an array with the following indices:
 *    @li 0:   Token name   (e.g. 'COMMENT'
 *    @li 1:   Token string (e.g. '// foo')
 *    @li 2:   escaped? (bool) Because it's often more convenient to embed nested
 *              tokens by tagging token string, we need to escape it. This
 *              index stores whether or nto it has been escaped.
 *
 */
class Scanner extends StringScanner
{
    /**
     * scanner version.
     */
    public $version = 'master';

    /**
     * @brief The token stream
     *
     * The token stream is recorded as a flat array of tokens. A token is
     * made up of 3 parts, and stored as an array:
     *  @li 0 => Token name
     *  @li 1 => Token string (from input source code)
     *  @li 2 => XML-Escaped?
     */
    protected $tokens = array();

    /**
     * @brief State stack
     *
     * A stack of the scanner's state, should the scanner wish to use a stack
     * based state mechanism.
     *
     * The top element can be retrieved (but not popped) with stack()
     *
     * TODO More useful functions for manipulating the stack
     */
    protected $state = array();

    /**
     * @brief Individual token filters
     *
     * A list of lists, each filter is an array: (name, token_name, callback)
     */
    protected $filters = array();

    /**
     * @brief Token stream filters
     *
     * A list of lists, each filter is an array: (name, callback)
     */
    protected $streamFilters = array();

    /**
     * @brief Rule remappings
     *
     * A map to handle re-mapping of rules, in the form:
     * OLD_TOKEN_NAME => NEW_TOKEN_NAME
     *
     * This is used by rule_mapper_filter()
     */
    protected $ruleTagMap = array();

    /**
     * @brief Identifier remappings based on definitions identified in the source code
     *
     * A map of remappings of user-defined types/functions. This is a map of
     * identifier_string => TOKEN_NAME
     *
     * This is used by user_def_filter()
     */
    protected $userDefs;

    /**
     * @brief A map of identifiers and their corresponding token names
     *
     * A map of recognised identifiers, in the form
     * identifier_string => TOKEN_NAME
     *
     * This is currently used by map_identifier_filter
     */
    protected $identMap = array();

    /**
     * @brief Whether or not the language is case sensitive
     *
     * Whether or not the scanner is dealing with a case sensitive language.
     * This currently affects map_identifier_filter
     */
    protected $caseSensitive = true;

    /**
     * constructor
     */
    public function __construct($src = null)
    {
        parent::__construct($src);

        $this->addFilter('map-ident', 'IDENT', array($this, 'mapIdentifierFilter'));

        $this->addFilter('comment-note', 'COMMENT', array('Luminous\\Core\\Filters', 'commentNote'));
        $this->addFilter('comment-to-doc', 'COMMENT', array('Luminous\\Core\\Filters', 'genericDocComment'));
        $this->addFilter('string-escape', 'STRING', array('Luminous\\Core\\Filters', 'string'));
        $this->addFilter('char-escape', 'CHARACTER', array('Luminous\\Core\\Filters', 'string'));
        $this->addFilter('pcre', 'REGEX', array('Luminous\\Core\\Filters', 'pcre'));
        $this->addFilter('user-defs', 'IDENT', array($this, 'userDefFilter'));

        $this->addFilter('constant', 'IDENT', array('Luminous\\Core\\Filters', 'upperToConstant'));

        $this->addFilter('clean-ident', 'IDENT', array('Luminous\\Core\\Filters', 'cleanIdent'));

        $this->addStreamFilter('rule-map', array($this, 'ruleMapperFilter'));
        $this->addStreamFilter('oo-syntax', array('Luminous\\Core\\Filters', 'ooStreamFilter'));
    }

    /**
     * @brief Language guessing
     *
     * Each real language scanner should override this method and implement a
     * simple guessing function to estimate how likely the input source code
     * is to be the language which they recognise.
     *
     * @param $src the input source code
     * @return The estimated chance that the source code is in the same language
     *  as the one the scanner tokenizes, as a real number between 0 (least
     *  likely) and 1 (most likely), inclusive
     */
    public static function guessLanguage($src, $info)
    {
        return 0.0;
    }

    /**
     * @brief Filter to highlight identifiers whose definitions are in the source
     *
     * maps anything recorded in LuminousScanner::user_defs to the recorded type.
     * This is called as the filter 'user-defs'
     * @internal
     */
    protected function userDefFilter($token)
    {
        if (isset($this->userDefs[$token[1]])) {
            $token[0] = $this->userDefs[$token[1]];
        }
        return $token;
    }

    /**
     * @brief Rule re-mapper filter
     *
     * Re-maps token rules according to the LuminousScanner::rule_tag_map
     * map.
     * This is called as the filter 'rule-map'
     * @internal
     */
    protected function ruleMapperFilter($tokens)
    {
        foreach ($tokens as &$t) {
            if (array_key_exists($t[0], $this->ruleTagMap)) {
                $t[0] = $this->ruleTagMap[$t[0]];
            }
        }
        return $tokens;
    }

    /**
     * @brief Public convenience function for setting the string and highlighting it
     *
     * Alias for:
     *   $s->string($src)
     *   $s->init();
     *   $s->main();
     *   return $s->tagged();
     *
     * @returns the highlighted string, as an XML string
     */
    public function highlight($src)
    {
        $this->string($src);
        $this->init();
        $this->main();
        return $this->tagged();
    }

    /**
     * @brief Set up the scanner immediately prior to tokenization.
     *
     * The init method is always called prior to main(). At this stage, all
     * configuration variables are assumed to have been set, and it's now time
     * for the scanner to perform any last set-up information. This may include
     * actually finalizing its rule patterns. Some scanners may not need to
     * override this if they are in no way dynamic.
     */
    public function init()
    {
    }

    /**
     * @brief the method responsible for tokenization
     *
     * The main method is fully responsible for tokenizing the string stored
     * in string() at the time of its call. By the time main returns, it should
     * have consumed the whole of the string and populated the token array.
     */
    public function main()
    {
    }

    /**
     * @brief Add an individual token filter
     *
     * Adds an indivdual token filter. The filter is bound to the given
     * token_name. The filter is a callback which should take a token and return
     * a token.
     *
     * The arguments are: [name], token_name, filter
     *
     * Name is an optional argument.
     */
    public function addFilter($arg1, $arg2, $arg3 = null)
    {
        $filter = null;
        $name = null;
        $token = null;
        if ($arg3 === null) {
            $filter = $arg2;
            $token = $arg1;
        } else {
            $filter = $arg3;
            $token = $arg2;
            $name = $arg1;
        }
        if (!isset($this->filters[$token])) {
            $this->filters[$token] = array();
        }
        $this->filters[$token][] = array($name, $filter);
    }

    /**
     * @brief Removes the individual filter(s) with the given name
     */
    public function removeFilter($name)
    {
        foreach ($this->filters as $token => $filters) {
            foreach ($filters as $k => $f) {
                if ($f[0] === $name) {
                    unset($this->filters[$token][$k]);
                }
            }
        }
    }

    /**
     * @brief Removes the stream filter(s) with the given name
     */
    public function removeStreamFilter($name)
    {
        foreach ($this->streamFilters as $k => $f) {
            if ($f[0] === $name) {
                unset($this->streamFilters[$k]);
            }
        }
    }

    /**
     * @brief Adds a stream filter
     *
     * A stream filter receives the entire token stream and should return it.
     *
     * The parameters are: ([name], filter). Name is an optional argument.
     */
    public function addStreamFilter($arg1, $arg2 = null)
    {
        $filter = null;
        $name = null;
        if ($arg2 === null) {
            $filter = $arg1;
        } else {
            $filter = $arg2;
            $name = $arg1;
        }
        $this->streamFilters[] = array($name, $filter);
    }

    /**
     * @brief Gets the top element on $state_ or null if it is empty
     */
    public function state()
    {
        if (!isset($this->state[0])) {
            return null;
        }
        return $this->state[count($this->state) - 1];
    }

    /**
     * @brief Pushes some data onto the stack
     */
    public function push($state)
    {
        $this->state[] = $state;
    }

    /**
     * @brief Pops the top element of the stack, and returns it
     * @throw Exception if the state stack is empty
     */
    public function pop()
    {
        if (empty($this->state)) {
            throw new Exception('Cannot pop empty state stack');
        }
        return array_pop($this->state);
    }

    /**
     * @brief Flushes the token stream
     */
    public function start()
    {
        $this->tokens = array();
    }

    /**
     * @brief Records a string as a given token type.
     * @param $string The string to record
     * @param $type The name of the token the string represents
     * @param $pre_escaped Luminous works towards getting this in XML and
     * therefore at some point, the $string has to be escaped. If you have
     * already escaped it for some reason (or if you got it from another scanner),
     * then you want to set this to @c TRUE
     * @see LuminousUtils::escape_string
     * @throw Exception if $string is @c NULL
     */
    public function record($string, $type, $preEscaped = false)
    {
        if ($string === null) {
            throw new Exception('Tagging null string');
        }
        $this->tokens[] = array($type, $string, $preEscaped);
    }

    /**
     * @brief Helper function to record a range of the string
     * @param $from the start index
     * @param $to the end index
     * @param $type the type of the token
     * This is shorthand for
     * <code> $this->record(substr($this->string(), $from, $to-$from)</code>
     *
     * @throw RangeException if the range is invalid (i.e. $to < $from)
     *
     * An empty range (i.e. $to === $from) is allowed, but it is essentially a
     * no-op.
     */
    public function recordRange($from, $to, $type)
    {
        if ($to === $from) {
            return;
        }
        if ($to > $from) {
            $this->record(substr($this->string(), $from, $to - $from), $type);
            return;
        }
        throw new RangeException("Invalid range supplied [$from, $to]");
    }

    /**
     * @brief Returns the XML representation of the token stream
     *
     * This function triggers the generation of the XML output.
     * @return An XML-string which represents the tokens recorded by the scanner.
     */
    public function tagged()
    {
        $out = '';

        // call stream filters.
        foreach ($this->streamFilters as $f) {
            $this->tokens = call_user_func($f[1], $this->tokens);
        }
        foreach ($this->tokens as $t) {
            $type = $t[0];

            // speed is roughly 10% faster if we process the filters inside this
            // loop instead of separately.
            if (isset($this->filters[$type])) {
                foreach ($this->filters[$type] as $filter) {
                    $t = call_user_func($filter[1], $t);
                }
            }
            list($type, $string, $esc) = $t;

            if (!$esc) {
                $string = Utils::escapeString($string);
            }
            if ($type !== null) {
                $out .= Utils::tagBlock($type, $string);
            } else {
                $out .= $string;
            }
        }
        return $out;
    }

    /**
     * @brief Gets the token array
     * @return The token array
     */
    public function tokenArray()
    {
        return $this->tokens;
    }

    /**
     * @brief Identifier mapping filter
     *
     * Tries to map any 'IDENT' token to a TOKEN_NAME in
     * LuminousScanner::$ident_map
     * This is implemented as the filter 'map-ident'
     */
    public function mapIdentifierFilter($token)
    {
        $ident = $token[1];
        if (!$this->caseSensitive) {
            $ident = strtolower($ident);
        }
        foreach ($this->identMap as $n => $hits) {
            if (isset($hits[$ident])) {
                $token[0] = $n;
                break;
            }
        }
        return $token;
    }

    /**
     * @brief Adds an identifier mapping which is later analysed by map_identifier_filter
     * @param $name The token name
     * @param $matches an array of identifiers which correspond to this token
     * name, i.e. add_identifier_mapping('KEYWORD', array('if', 'else', ...));
     *
     * This method observes LuminousScanner::$case_sensitive
     */
    public function addIdentifierMapping($name, $matches)
    {
        $array = array();
        foreach ($matches as $m) {
            if (!$this->caseSensitive) {
                $m = strtolower($m);
            }
            $array[$m] = true;
        }
        if (!isset($this->identMap[$name])) {
            $this->identMap[$name] = array();
        }
        $this->identMap[$name] = array_merge($this->identMap[$name], $array);
    }

    /**
     * Convenience function
     * @brief Skips whitespace, and records it as a null token.
     */
    public function skipWhitespace()
    {
        if (ctype_space($this->peek())) {
            $this->record($this->scan('/\s+/'), null);
        }
    }

    /**
     * @brief Handles tokens that may nest inside themselves
     *
     * Convenience function. It's fairly common for many languages to allow
     * things like nestable comments. Handling these is easy but fairly
     * long winded, so this function will take an opening and closing delimiter
     * and consume the token until it is fully closed, or until the end of
     * the string in the case that it is unterminated.
     *
     * When the function returns, the token will have been consumed and appended
     * to the token stream.
     *
     * @param $token_name the name of the token
     * @param $open the opening delimiter pattern (regex), e.g. '% /\\* %x'
     * @param $close the closing delimiter pattern (regex), e.g. '% \\* /%x'
     *
     * @warning Although PCRE provides recursive regular expressions, this
     * function is far preferable. A recursive regex will easily crash PCRE
     * on garbage input due to it having a fairly small stack: this function
     * is much more resilient.
     *
     * @throws Exception if called at a non-matching point (i.e.
     * <code>$this->scan($open)</code> does not match)
     */
    public function nestableToken($tokenName, $open, $close)
    {
        if ($this->check($open) === null) {
            throw new Exception('Nestable called at a non-matching point');
            return;
        }
        $patterns = array('open' => $open, 'close' => $close);

        $stack = 0;
        $start = $this->pos();
        do {
            list($name, $index, $matches) = $this->getNextNamed($patterns);
            if ($name === 'open') {
                $stack++;
            } elseif ($name === 'close') {
                $stack--;
            } else {
                $this->terminate();
                break;
            }
            $this->pos($index + strlen($matches[0]));
        } while ($stack);
        $substr = substr($this->string(), $start, $this->pos() - $start);
        $this->record($substr, $tokenName);
    }
}

/** @endcond */
