<?php

/** @cond CORE */

namespace Luminous\Core\Scanners;

use Exception;
use Luminous\Core\StringSearch;

/**
 * @brief Base string scanning class
 *
 * The Scanner class is the base class which handles traversing a string while
 * searching for various different tokens.
 * It is loosely based on Ruby's StringScanner.
 *
 * The rough idea is we keep track of the position (a string pointer) and
 * use scan() to see what matches at the current position.
 *
 * It also provides some automation methods, but it's fairly low-level as
 * regards string scanning.
 *
 * Scanner is abstract as far as Luminous is concerned. LuminousScanner extends
 * Scanner significantly with some methods which are useful for recording
 * highlighting related data.
 *
 * @see LuminousScanner
 */
class StringScanner
{
    /**
     * @brief Our local copy of the input string to be scanned.
     */
    private $src;

    /**
     * @brief Length of input string (cached for performance)
     */
    private $srcLen;

    /**
     * @brief The current scan pointer (AKA the offset or index)
     */
    private $index;

    /**
     * @brief Match history
     *
     * History of matches. This is an array (queue), which should have at most
     * two elements. Each element consists of an array:
     *
     *  0 => Scan pointer when the match was found,
     *  1 => Match index (probably the same as scan pointer, but not necessarily),
     *  2 => Match data (match groups, as map, as returned by PCRE)
     *
     * @note Numerical indices are used for performance.
     */
    private $matchHistory = array(null, null);

    /**
     * @brief LuminousStringSearch instance (caches preg_* results)
     */
    private $ss;

    /**
     * @brief Caller defined patterns used by next_match()
     */
    private $patterns = array();

    /**
     * constructor
     */
    public function __construct($src = null)
    {
        $this->string($src);
    }

    /**
     * @brief Gets the remaining string
     *
     * @return The rest of the string, which has not yet been consumed
     */
    public function rest()
    {
        $rest = substr($this->src, $this->index);
        if ($rest === false) {
            $rest = '';
        }
        return $rest;
    }

    /**
     * @brief Getter and setter for the current position (string pointer).
     *
     * @param $new_pos The new position (leave \c NULL to use as a getter), note
     * that this will be clipped to a legal string index if you specify a
     * negative number or an index greater than the string's length.
     * @return the current string pointer
     */
    public function pos($newPos = null)
    {
        if ($newPos !== null) {
            $newPos = max(min((int)$newPos, $this->srcLen), 0);
            $this->index = $newPos;
        }
        return $this->index;
    }

    /**
     * @brief Moves the string pointer by a given offset
     *
     * @param $offset the offset by which to move the pointer. This can be positve
     * or negative, but using a negative offset is currently generally unsafe.
     * You should use unscan() to revert the last operation.
     * @see pos
     * @see unscan
     */
    public function posShift($offset)
    {
        $this->pos($this->pos() + $offset);
    }

    /**
     * @brief Beginning of line?
     *
     * @return @c TRUE if the scan pointer is at the beginning of a line (i.e.
     * immediately following a newline character), or at the beginning of the
     * string, else @c FALSE
     */
    public function bol()
    {
        return $this->index === 0 || $this->src[$this->index - 1] === "\n";
    }

    /**
     * @brief End of line?
     *
     * @return @c TRUE if the scan pointer is at the end of a line (i.e.
     * immediately preceding a newline character), or at the end of the
     * string, else @c FALSE
     */
    public function eol()
    {
        return ($this->eos() || $this->src[$this->index] === "\n");
    }

    /**
     * @brief End of string?
     *
     * @return @c TRUE if the scan pointer at the end of the string, else
     * @c FALSE.
     */
    public function eos()
    {
        return $this->index >= $this->srcLen;
    }

    /**
     * @brief Reset the scanner
     *
     * Resets the scanner: sets the scan pointer to 0 and clears the match
     * history.
     */
    public function reset()
    {
        $this->pos(0);
        $this->matchHistory = array(null, null);
        $this->ss = new StringSearch($this->src);
    }

    /**
     * @brief Getter and setter for the source string
     *
     * @param $s The new source string (leave as @c NULL to use this method as a
     * getter)
     * @return The current source string
     *
     * @note This method triggers a reset()
     * @note Any strings passed into this method are converted to Unix line
     * endings, i.e. @c \\n
     */
    public function string($s = null)
    {
        if ($s !== null) {
            $s = str_replace("\r\n", "\n", $s);
            $s = str_replace("\r", "\n", $s);
            $this->src = $s;
            $this->srcLen = strlen($s);
            $this->reset();
        }
        return $this->src;
    }

    /**
     * @brief Ends scanning of a string
     *
     * Moves the scan pointer to the end of the string, terminating the
     * current scan.
     */
    public function terminate()
    {
        $this->reset();
        $this->pos($this->srcLen);
    }

    /**
     * @brief Lookahead into the string a given number of bytes
     *
     * @param $n The number of bytes.
     * @return The given number of bytes from the string from the current scan
     * pointer onwards. The returned string will be at most n bytes long, it may
     * be shorter or the empty string if the scanner is in the termination
     * position.
     *
     * @note This method is identitical to get(), but it does not consume the
     * string.
     * @note neither get nor peek logs its matches into the match history.
     */
    public function peek($n = 1)
    {
        if ($n === 0 || $this->eos()) {
            return '';
        }
        if ($n === 1) {
            return $this->src[$this->index];
        }
        return substr($this->src, $this->index, $n);
    }

    /**
     * @brief Consume a given number of bytes
     *
     * @param $n The number of bytes.
     * @return The given number of bytes from the string from the current scan
     * pointer onwards. The returned string will be at most n bytes long, it may
     * be shorter or the empty string if the scanner is in the termination
     * position.
     *
     * @note This method is identitical to peek(), but it does consume the
     * string.
     * @note neither get nor peek logs its matches into the match history.
     */
    public function get($n = 1)
    {
        $p = $this->peek($n);
        $this->index += strlen($p);
        return $p;
    }

    /**
     * @brief Get the result of the most recent match operation.
     *
     * @return The return value is either a string or \c NULL depending on
     * whether or not the most recent scanning function matched anything.
     *
     * @throw Exception if no matches have been recorded.
     */
    public function match()
    {
        // $index = false;
        if (isset($this->matchHistory[0])) {
            return $this->matchHistory[0][2][0];
        }
        throw new Exception('match history empty');
    }

    /**
     * @brief Get the match groups of the most recent match operation.
     *
     * @return The return value is either an array/map or \c NULL depending on
     * whether or not the most recent scanning function was successful. The map
     * is the same as PCRE returns, i.e. group_name => match_string, where
     * group_name may be a string or numerical index.
     *
     * @throw Exception if no matches have been recorded.
     */
    public function matchGroups()
    {
        if (isset($this->matchHistory[0])) {
            return $this->matchHistory[0][2];
        }
        throw new Exception('match history empty');
    }

    /**
     * @brief Get a group from the most recent match operation
     *
     * @param $g the group's numerical index or name, in the case of named
     * subpatterns.
     * @return A string represeting the group's contents.
     *
     * @see match_groups()
     *
     * @throw Exception if no matches have been recorded.
     * @throw Exception if matches have been recorded, but the group does not
     * exist.
     */
    public function matchGroup($g = 0)
    {
        if (isset($this->matchHistory[0])) {
            if (isset($this->matchHistory[0][2])) {
                if (isset($this->matchHistory[0][2][$g])) {
                    return $this->matchHistory[0][2][$g];
                }
                throw new Exception("No such group '$g'");
            }
        }
        throw new Exception('match history empty');
    }

    /**
     * @brief Get the position (offset) of the most recent match
     *
     * @return The position, as integer. This is a standard zero-indexed offset
     * into the string. It is independent of the scan pointer.
     *
     * @throw Exception if no matches have been recorded.
     */
    public function matchPos()
    {
        if (isset($this->matchHistory[0])) {
            return $this->matchHistory[0][1];
        }
        throw new Exception('match history empty');
    }

    /**
     * @brief Helper function to log a match into the history
     *
     * @internal
     */
    private function logMatch($index, $matchPos, $matchData)
    {
        if (isset($this->matchHistory[0])) {
            $this->matchHistory[1] = $this->matchHistory[0];
        }
        $m = &$this->matchHistory[0];
        $m[0] = $index;
        $m[1] = $matchPos;
        $m[2] = $matchData;
    }

    /**
     *
     * @brief Revert the most recent scanning operation.
     *
     * Unscans the most recent match. The match is removed from the history, and
     * the scan pointer is moved to where it was before the match.
     *
     * Calls to get(), and peek() are not logged and are therefore not
     * unscannable.
     *
     * @warning Do not call unscan more than once before calling a scanning
     * function. This is not currently defined.
     */
    public function unscan()
    {
        if (isset($this->matchHistory[0])) {
            $this->index = $this->matchHistory[0][0];
            if (isset($this->matchHistory[1])) {
                $this->matchHistory[0] = $this->matchHistory[1];
                $this->matchHistory[1] = null;
            } else {
                $this->matchHistory[0] = null;
            }
        } else {
            throw new Exception('match history empty');
        }
    }

    /**
     * @brief Helper function to consume a match
     *
     * @param $pos (int) The match position
     * @param $consume_match (bool) Whether or not to consume the actual matched
     * text
     * @param $match_data The matching groups, as returned by PCRE.
     * @internal
     */
    private function consume($pos, $consumeMatch, $matchData)
    {
        $this->index = $pos;
        if ($consumeMatch) {
            $this->index += strlen($matchData[0]);
        }
    }

    /**
     * @brief The real scanning function
     *
     * @internal
     * @param $pattern The pattern to scan for
     * @param $instant Whether or not the only legal match is at the
     * current scan pointer or whether one beyond the scan pointer is also
     * legal.
     * @param $consume Whether or not to consume string as a result of matching
     * @param $consume_match Whether or not to consume the actual matched string.
     * This only has effect if $consume is @c TRUE. If $instant is @c TRUE,
     * $consume is true and $consume_match is @c FALSE, the intermediate
     * substring is consumed and the scan pointer moved to the beginning of the
     * match, and the substring is recorded as a single-group match.
     * @param $log whether or not to log the matches into the match_register
     * @return The matched string or null. This is subsequently
     * equivalent to match() or match_groups()[0] or match_group(0).
     */
    private function checkInternal(
        $pattern,
        $instant = true,
        $consume = true,
        $consumeMatch = true,
        $log = true
    ) {
        $matches = null;
        $index = $this->index;
        $pos = null;
        if (($pos = $this->ss->match($pattern, $this->index, $matches)) !== false) {
            if ($instant && $pos !== $index) {
                $matches = null;
            }
            // don't consume match and not instant: the match we are interested in
            // is actually the substring between the start and the match.
            // this is used by scan_to
            if (!$consumeMatch && !$instant) {
                $matches = array(substr($this->src, $this->index, $pos-$this->index));
            }
        } else {
            $matches = null;
        }

        if ($log) {
            $this->logMatch($index, $pos, $matches);
        }
        if ($matches !== null && $consume) {
            $this->consume($pos, $consumeMatch, $matches);
        }
        return ($matches === null) ? null : $matches[0];
    }

    /**
     * @brief Scans at the current pointer
     *
     * Looks for the given pattern at the current index and consumes and logs it
     * if it is found.
     * @param $pattern the pattern to search for
     * @return @c null if not found, else the full match.
     */
    public function scan($pattern)
    {
        return $this->checkInternal($pattern);
    }

    /**
     * @brief Scans until the start of a pattern
     *
     * Looks for the given pattern anywhere beyond the current index and
     * advances the scan pointer to the start of the pattern. The match is logged.
     *
     * The match itself is not consumed.
     *
     * @param $pattern the pattern to search for
     * @return The substring between here and the given pattern, or @c null if it
     * is not found.
     */
    public function scanUntil($pattern)
    {
        return $this->checkInternal($pattern, false, true, false, true);
    }


    /**
     * @brief Non-consuming lookahead
     *
     * Looks for the given pattern at the current index and logs it
     * if it is found, but does not consume it. This is a look-ahead.
     * @param $pattern the pattern to search for
     * @return @c null if not found, else the matched string.
     */
    public function check($pattern)
    {
        return $this->checkInternal($pattern, true, false, false, true);
    }

    /**
     * @brief Find the index of the next occurrence of a pattern
     *
     * @param $pattern the pattern to search for
     * @return The next index of the pattern, or -1 if it is not found
     */
    public function index($pattern)
    {
        $ret = $this->ss->match($pattern, $this->index, $dontcareRef);
        return ($ret !== false) ? $ret : -1;
    }

    /**
     * @brief Find the index of the next occurrence of a named pattern
     * @param $patterns A map of $name=>$pattern
     * @return An array: ($name, $index, $matches). If there is no next match,
     * name will be null, index will be -1 and matches will be null.
     *
     * @note consider using this method to build a transition table
     */
    public function getNextNamed($patterns)
    {
        $next = -1;
        $matches = null;
        $name = null;
        $m;
        foreach ($patterns as $name_ => $p) {
            $index = $this->ss->match($p, $this->index, $m);
            if ($index === false) {
                continue;
            }
            if ($next === -1 || $index < $next) {
                $next = $index;
                $matches = $m;
                assert($m !== null);
                $name = $name_;
                if ($index === $this->index) {
                    break;
                }
            }
        }
        return array($name, $next, $matches);
    }

    /**
     * @brief Look for the next occurrence of a set of patterns
     *
     * Finds the next match of the given patterns and returns it. The
     * string is not consumed or logged.
     * Convenience function.
     * @param $patterns an array of regular expressions
     * @return an array of (0=>index, 1=>match_groups). The index may be -1 if
     * no pattern is found.
     */
    public function getNext($patterns)
    {
        $next = -1;
        $matches = null;
        foreach ($patterns as $p) {
            $m;
            $index = $this->ss->match($p, $this->index, $m);
            if ($index === false) {
                continue;
            }
            if ($next === -1 || $index < $next) {
                $next = $index;
                $matches = $m;
                assert($m !== null);
            }
        }
        return array($next, $matches);
    }

    /**
     * @brief Look for the next occurrence of a set of substrings
     *
     * Like get_next() but uses strpos instead of preg_*
     * @return An array: 0 => index 1 => substring. If the substring is not found,
     *    index is -1 and substring is null
     * @see get_next()
     */
    public function getNextStrpos($patterns)
    {
        $next = -1;
        $match = null;
        foreach ($patterns as $p) {
            $index = strpos($this->src, $p, $this->index);
            if ($index === false) {
                continue;
            }
            if ($next === -1 || $index < $next) {
                $next = $index;
                $match = $p;
            }
        }
        return array($next, $match);
    }

    /**
     * @brief Allows the caller to add a predefined named pattern
     *
     * Adds a predefined pattern which is visible to next_match.
     *
     * @param $name A name for the pattern. This does not have to be unique.
     * @param $pattern A regular expression pattern.
     */
    public function addPattern($name, $pattern)
    {
        $this->patterns[] = array($name, $pattern . 'S', -1, null);
    }

    /**
     * @brief Allows the caller to remove a named pattern
     *
     * @param $name the name of the pattern to remove, this should be as it was
     * supplied to add_pattern().
     * @warning If there are multiple patterns with the same name, they will all
     * be removed.
     */
    public function removePattern($name)
    {
        foreach ($this->patterns as $k => $p) {
            if ($p[0] === $name) {
                unset($this->patterns[$k]);
            }
        }
    }

    /**
     * @brief Automation function: returns the next occurrence of any known patterns.
     *
     * Iterates over the predefined patterns array (add_pattern) and consumes/logs
     * the nearest match, skipping unrecognised segments of string.
     * @return An array:
     *    0 => pattern name  (as given to add_pattern)
     *    1 => match index (although the scan pointer will have progressed to the
     *            end of the match if the pattern is consumed).
     * When no more matches are found, return value is @c NULL and nothing is
     * logged.
     *
     * @param $consume_and_log If this is @c FALSE, the pattern is not consumed
     * or logged.
     *
     * @warning this method is not the same as get_next. This does not return
     * the match groups, instead it returns a name. The ordering of the return
     * array is also different, but the array does in fact hold different data.
     */
    public function nextMatch($consumeAndLog = true)
    {
        $target = $this->index;

        $nearestIndex = -1;
        $nearestKey = -1;
        $nearestName = null;
        $nearestMatchData = null;

        foreach ($this->patterns as &$pData) {
            $name = $pData[0];
            $pattern = $pData[1];
            $index = &$pData[2];
            $matchData = &$pData[3];

            if ($index !== false && $index < $target) {
                $index = $this->ss->match($pattern, $target, $matchData);
            }

            if ($index === false) {
                unset($pData);
                continue;
            }

            if ($nearestIndex === -1 || $index < $nearestIndex) {
                $nearestIndex = $index;
                $nearestName = $name;
                $nearestMatchData = $matchData;
                if ($index === $target) {
                    break;
                }
            }
        }

        if ($nearestIndex !== -1) {
            if ($consumeAndLog) {
                $this->logMatch($nearestIndex, $nearestIndex, $nearestMatchData);
                $this->consume($nearestIndex, true, $nearestMatchData);
            }
            return array($nearestName, $nearestIndex);
        }
        return null;
    }
}

/** @endcond */
