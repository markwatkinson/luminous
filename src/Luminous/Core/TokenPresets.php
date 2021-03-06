<?php

/** @cond CORE */

namespace Luminous\Core;

/**
 * @brief A set of pre-defined patterns to match various common tokens
 */
abstract class TokenPresets
{
    /**
     * multi-line double quoted string using backslash escapes
     */
    public static $DOUBLE_STR = '/" (?> [^"\\\\]+ | \\\\.)* (?:"|$)/xs';

    /**
     * single line double quoted string using backslash escapes
     */
    public static $DOUBLE_STR_SL = "/\"(?> [^\\\\\"\n]+ | \\\\.)*(?:\$|\")/xms";

    /**
     * multi-line single quote string using backslash escapes
     */
    public static $SINGLE_STR = "/' (?> [^'\\\\]+ | \\\\.)* (?:'|\$)/xs";

    /**
     * single line single quoted string using backslash escapes
     */
    public static $SINGLE_STR_SL = "/'(?> [^\\\\'\n]+ | \\\\.)*(?:\$|')/xms";

    /**
     * Single quoted c-style character
     */
    public static $CHAR = "(?: \\\\(?: x[A-F0-9]{1,2}| . ) | . ) (?: '|\$)/ixm";

    /**
     * hexadecimal literal
     */
    public static $NUM_HEX = '/0[Xx][a-fA-F0-9]+/';

    /**
     * Real number, i.e. an integer or a float, optionally with an exponent
     */
    public static $NUM_REAL = '/
        (?: \d+ (?: \.\d+ )? | \.?\d+)     # int, fraction or significand
        (?:e[+-]?\d+)?                     # exponent
    /ix';

    /**
     * Single line c++ style comment
     */
    public static $C_COMMENT_SL = '% // .* %x';

    /**
     * Multiline C style comment
     */
    public static $C_COMMENT_ML = '% / \* (?> [^\\*]+ | \\*(?!/) )* (?: \\*/ | $)  %sx';

    /**
     * Perl/Python/Ruby style hash-comment (single line)
     */
    public static $PERL_COMMENT = '/#.*/';

    /**
     * SQL style single quoted string using '' to escape
     */
    public static $SQL_SINGLE_STR = "/ ' (?> [^']+ | '' )* (?: '|\$)/x";

    /**
     * SQL style single quoted string using '' or \' to escape
     */
    public static $SQL_SINGLE_STR_BSLASH = "/ ' (?> [^'\\\\]+ | '' | \\\\. )* (?: '|\$)/x";
}

/** @endcond */
