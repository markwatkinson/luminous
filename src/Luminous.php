<?php

/*
 * This file contains the public calling interface for Luminous. It's split
 * into two classes: one is basically the user-interface, the other is a
 * wrapper around it. The wrapper allows a single-line function call, and is
 * procedural. It's wrapped in an abstract class for a namespace.
 * The real class is instantiated into a singleton which is manipulated by
 * the abstract class methods.
 */

/// @cond USER

// here's our 'real' UI class, which uses the above singleton. This is all
// static because these are actually procudural functions, we're using the
// class as a namespace.
/**
 * @brief Users' API
 */
abstract class Luminous
{
    /**
     * @brief Highlights a string according to the current settings
     *
     * @param $scanner The scanner to use, this can either be a langauge code,
     *    or it can be an instance of LuminousScanner.
     * @param $source The source string
     * @param $cache Whether or not to use the cache
     * @return the highlighted source code.
     *
     * To specify different output formats or other options, see set().
     */
    public static function highlight($scanner, $source, $cacheOrSettings = null)
    {
        global $luminous_;
        try {
            $settings = null;
            if (is_bool($cacheOrSettings)) {
                $settings = array('cache' => $cacheOrSettings);
            } elseif (is_array($cacheOrSettings)) {
                $settings = $cacheOrSettings;
            }
            $h = $luminous_->highlight($scanner, $source, $settings);
            if ($luminous_->settings->verbose) {
                $errs = self::cacheErrors();
                if (!empty($errs)) {
                    trigger_error("Luminous cache errors were encountered. \nSee luminous::cacheErrors() for details.");
                }
            }
            return $h;
        } catch (InvalidArgumentException $e) {
            // this is a user error, let it bubble
            //FIXME how do we let it bubble without throwing? the stack trace will
            // be wrong.
            throw $e;
        } catch (Exception $e) {
            // this is an internal error or a scanner error, or something
            // it might not technically be Luminous that caused it, but let's not
            // make it kill the whole page in production code
            if (LUMINOUS_DEBUG) {
                throw $e;
            } else {
                $return = $source;
                if ($t = self::setting('failure-tag')) {
                    $return = "<$t>$return</$t>";
                }
                return $return;
            }
        }
    }

    /**
     * @brief Highlights a file according to the current setings.
     *
     * @param $scanner The scanner to use, this can either be a langauge code,
     *    or it can be an instance of LuminousScanner.
     * @param $file the source string
     * @param $cache Whether or not to use the cache
     * @return the highlighted source code.
     *
     * To specify different output formats or other options, see set().
     */
    public static function highlightFile($scanner, $file, $cacheOrSettings = null)
    {
        return self::highlight($scanner, file_get_contents($file), $cacheOrSettings);
    }

    /**
     * @brief Returns a list of cache errors encountered during the most recent highlight
     *
     * @return An array of errors the cache encountered (which may be empty),
     *  or @c FALSE if the cache is not enabled
     */
    public static function cacheErrors()
    {
        global $luminous_;
        $c = $luminous_->cache;
        if ($c === null) {
            return false;
        }
        return $c->errors();
    }

    /**
     * @brief Registers a scanner
     *
     * Registers a scanner with Luminous's scanner table. Utilising this
     * function means that Luminous will handle instantiation and inclusion of
     * the scanner's source file in a lazy-manner.
     *
     * @param $language_code A string or array of strings designating the
     *    aliases by which this scanner may be accessed
     * @param $classname The class name of the scanner, as string. If you
     *    leave this as 'null', it will be treated as a dummy file (you can use
     *    this to handle a set of non-circular include rules, if you run into
     *    problems).
     * @param $readable_language  A human readable language name
     * @param $path The path to the source file containing your scanner
     * @param dependencies An array of other scanners which this scanner
     *    depends on (as sub-scanners, or superclasses). Each item in the
     *    array should be a $language_code for another scanner.
     */
    public static function registerScanner(
        $languageCode,
        $classname,
        $readableLanguage
    ) {
            global $luminous_;
            $luminous_->scanners->AddScanner(
                $languageCode,
                $classname,
                $readableLanguage
            );
    }

    /**
     * @brief Get the full filesystem path to Luminous
     * @return what Luminous thinks its location is on the filesystem
     * @internal
     */
    public static function root()
    {
        return realpath(__DIR__ . '/../');
    }

    /**
     * @brief Gets a list of installed themes
     *
     * @return the list of theme files present in style/.
     * Each theme will simply be a filename, and will end in .css, and will not
     * have any directory prefix.
     */
    public static function themes()
    {
        $themesUri = self::root() . '/style/';
        $themes = array();
        foreach (glob($themesUri . '/*.css') as $css) {
            $fn = trim(preg_replace("%.*/%", '', $css));
            switch ($fn) {
                // these are special, exclude these
                case 'luminous.css':
                case 'luminous_print.css':
                case 'luminous.min.css':
                    continue;
                default:
                    $themes[] = $fn;
            }
        }
        return $themes;
    }

    /**
     * @brief Checks whether a theme exists
     * @param $theme the name of a theme, which should be suffixed with .css
     * @return @c TRUE if a theme exists in style/, else @c FALSE
     */
    public static function themeExists($theme)
    {
        return in_array($theme, self::themes());
    }

    /**
     * @brief Reads a CSS theme file
     * Gets the CSS-string content of a theme file.
     * Use this function for reading themes as it involves security
     * checks against reading arbitrary files
     *
     * @param $theme The name of the theme to retrieve, which may or may not
     *    include the .css suffix.
     * @return the content of a theme; this is the actual CSS text.
     * @internal
     */
    public static function theme($theme)
    {
        if (!preg_match('/\.css$/i', $theme)) {
            $theme .= '.css';
        }
        if (self::themeExists($theme)) {
            return file_get_contents(self::root() . "/style/" . $theme);
        } else {
            throw new Exception('No such theme file: ' . $theme);
        }
    }

    /**
     * @brief Gets a setting's value
     * @param $option The name of the setting (corresponds to an attribute name
     * in LuminousOptions)
     * @return The value of the given setting
     * @throws Exception if the option is unrecognised
     *
     * Options are stored in LuminousOptions, which provides documentation of
     * each option.
     * @see LuminousOptions
     */
    public static function setting($option)
    {
        global $luminous_;
        $option = str_replace('-', '_', $option);
        return $luminous_->settings->$option;
    }

    /**
     * @brief Sets the given option to the given value
     * @param $option The name of the setting (corresponds to an attribute name
     * in LuminousOptions)
     * @param $value The new value of the setting
     * @throws Exception if the option is unrecognised (and in various other
     * validation failures),
     * @throws InvalidArgumentException if the argument fails the type-validation
     * check
     *
     * @note This function can also accept multiple settings if $option is a
     * map of option_name=>value
     *
     * Options are stored in LuminousOptions, which provides documentation of
     * each option.
     *
     * @note as of 0.7 this is a thin wrapper around LuminousOptions::set()
     *
     * @see LuminousOptions::set
     */
    public static function set($option, $value = null)
    {
        global $luminous_;
        $luminous_->settings->set($option, $value);
    }

    /**
     * @brief Gets a list of registered scanners
     *
     * @return a list of scanners currently registered. The list is in the
     * format:
     *
     *    language_name => codes,
     *
     * where language_name is a string, and codes is an array of strings.
     *
     * The array is sorted alphabetically by key.
     */
    public static function scanners()
    {
        global $luminous_;
        $scanners = $luminous_->scanners->ListScanners();
        ksort($scanners);
        return $scanners;
    }

    /**
     * @brief Gets a formatter instance
     *
     * @return an instance of a LuminousFormatter according to the current
     * format setting
     *
     * This shouldn't be necessary for general usage, it is only implemented
     * for testing.
     * @internal
     */
    public static function formatter()
    {
        global $luminous_;
        return $luminous_->getFormatter();
    }

    /**
     * @brief Attempts to guess the language of a piece of source code
     * @param $src The source code whose language is to be guessed
     * @param $confidence The desired confidence level: if this is 0.05 but the
     *  best guess has a confidence of 0.04, then $default is returned. Note
     *  that the confidence level returned by scanners is quite arbitrary, so
     *  don't set this to '1' thinking that'll give you better results.
     *  A realistic confidence is likely to be quite low, because a scanner will
     *  only return 1 if it's able to pick out a shebang (#!) line or something
     *  else definitive. If there exists no such identifier, a 'strong'
     *  confidence which is right most of the time might be as low as 0.1.
     *  Therefore it is recommended to keep this between 0.01 and 0.10.
     * @param $default The default name to return in the event that no scanner
     * thinks this source belongs to them (at the desired confidence).
     *
     * @return A valid code for the best scanner, or $default.
     *
     * This is a wrapper around luminous::guess_language_full
     */
    public static function guessLanguage($src, $confidence = 0.05, $default = 'plain')
    {
        $guess = self::guessLanguageFull($src);
        if ($guess[0]['p'] >= $confidence) {
            return $guess[0]['codes'][0];
        } else {
            return $default;
        }
    }

    /**
     * @brief Attempts to guess the language of a piece of source code
     * @param $src The source code whose language is to be guessed
     * @return An array - the array is ordered by probability, with the most
     *    probable language coming first in the array.
     *    Each array element is an array which represents a language (scanner),
     *    and has the keys:
     *    \li \c 'language' => Human-readable language description,
     *    \li \c 'codes' => valid codes for the language (array),
     *    \li \c 'p' => the probability (between 0.0 and 1.0 inclusive),
     *
     * note that \c 'language' and \c 'codes' are the key => value pair from
     * luminous::scanners()
     *
     * @warning Language guessing is inherently unreliable but should be right
     * about 80% of the time on common languages. Bear in mind that guessing is
     * going to be severely hampered in the case that one language is used to
     * generate code in another language.
     *
     * Usage for this function will be something like this:
     * @code
     * $guesses = luminous::guess_language($src);
     * $output = luminous::highlight($guesses[0]['codes'][0], $src);
     * @endcode
     *
     * @see luminous::guess_language
     */
    public static function guessLanguageFull($src)
    {
        global $luminous_;
        // first we're going to make an 'info' array for the source, which
        // precomputes some frequently useful things, like how many lines it
        // has, etc. It prevents scanners from redundantly figuring these things
        // out themselves
        $lines = preg_split("/\r\n|[\r\n]/", $src);
        $shebang = '';
        if (preg_match('/^#!.*/', $src, $m)) {
            $shebang = $m[0];
        }

        $info = array(
            'lines' => $lines,
            'num_lines' => count($lines),
            'trimmed' => trim($src),
            'shebang' => $shebang
        );

        $return = array();
        foreach (self::scanners() as $lang => $codes) {
            $scannerName = $luminous_->scanners->GetScanner($codes[0], false, false);
            assert($scannerName !== null);
            $return[] = array(
                'language' => $lang,
                'codes' => $codes,
                'p' => call_user_func(array($scannerName, 'guessLanguage'), $src, $info)
            );
        }
        uasort($return, function ($a, $b) {
            $c = $a['p'] - $b['p'];
            if ($c === 0) {
                return 0;
            }
            if ($c < 0) {
                return -1;
            }
            return 1;
        });
        $return = array_reverse($return);
        return $return;
    }

    /**
     * @brief Gets the markup you need to include in your web page
     * @return a string representing everything that needs to be printed in
     * the \<head\> section of a website.
     *
     * This is influenced by the following settings:
     *     relative-root,
     *     include-javascript,
     *     include-jquery
     *     theme
     */
    public static function headHtml()
    {
        global $luminous_;
        $theme = self::setting('theme');
        $relativeRoot = self::setting('relative-root');
        $js = self::setting('include-javascript');
        $jquery = self::setting('include-jquery');

        if (!preg_match('/\.css$/i', $theme)) {
            $theme .= '.css';
        }
        if (!self::themeExists($theme)) {
            $theme = 'luminous_light.css';
        }

        if ($relativeRoot === null) {
            $relativeRoot = str_replace($_SERVER['DOCUMENT_ROOT'], '/', __DIR__);
            $relativeRoot = str_replace('\\', '/', $relativeRoot); // bah windows
            $relativeRoot = rtrim($relativeRoot, '/');
            // go up one level.
            $relativeRoot = preg_replace('%/[^/]*$%', '', $relativeRoot);
        }
        // if we ended up with any double slashes, let's zap them, and also
        // trim any trailing ones
        $relativeRoot = preg_replace('%(?<!:)//+%', '/', $relativeRoot);
        $relativeRoot = rtrim($relativeRoot, '/');
        $out = '';
        $linkTemplate = "<link rel='stylesheet' type='text/css' href='$relativeRoot/style/%s' id='%s'>\n";
        $scriptTemplate = "<script type='text/javascript' src='$relativeRoot/client/%s'></script>\n";
        $out .= sprintf($linkTemplate, 'luminous.css', 'luminous-style');
        $out .= sprintf($linkTemplate, $theme, 'luminous-theme');
        if ($js) {
            if ($jquery) {
                $out .= sprintf($scriptTemplate, 'jquery-1.6.4.min.js');
            }
            $out .= sprintf($scriptTemplate, 'luminous.js');
        }

        return $out;
    }
}

/// @endcond
// ends user
