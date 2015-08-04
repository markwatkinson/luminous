<?php

/// @cond CORE

namespace Luminous\Core\Scanners;

/**
 * @brief Superclass for languages which may nest, i.e. web languages
 *
 * Web languages get their own special class because they have to deal with
 * server-script code embedded inside them and the potential for languages
 * nested under them (PHP has HTML, HTML has CSS and JavaScript)
 *
 * The relationship is strictly hierarchical, not recursive descent
 * Meeting a '\<?' in CSS bubbles up to HTML and then up to PHP (or whatever).
 * The top-level scanner is ultimately what should have sub-scanner code
 * embedded in its own token stream.
 *
 * The scanners should be persistent, so only one JavaScript scanner exists
 * even if there are 20 javascript tags. This is so they can keep persistent
 * state, which might be necessary if they are interrupted by server-side tags.
 * For this reason, the main() method might be called multiple times, therefore
 * each web sub-scanner should
 *     \li Not rely on keeping state related data in main()'s function scope,
 *              make it a class variable
 *      \li flush its token stream every time main() is called
 *
 * The init method of the class should be used to set relevant rules based
 * on whether or not the embedded flags are set; and therefore the embedded
 * flags should be set before init is called.
 */
abstract class EmbeddedWebScriptScanner extends Scanner
{
    /**
     * @brief Is the source embedded in HTML?
     *
     * Embedded in HTML? i.e. do we need to observe tag terminators like \</script\>
     */
    public $embeddedHtml = false;

    /**
     * @brief Is the source embedded in a server-side script (e.g. PHP)?
     *
     * Embedded in a server side language? i.e. do we need to break at
     * (for example) \<? tags?
     */
    public $embeddedServer = false;

    /**
     * @brief Opening tag for server-side code. This is a regular expression.
     */
    public $serverTags = '/<\?/';

    /// @brief closing HTML tag for our code, e.g \</script\>
    public $scriptTags;

    /** @brief I think this is ignored and obsolete */
    public $interrupt = false;

    /**
     * @brief Clean exit or inconvenient, mid-token forced exit
     *
     * Signifies whether the program exited due to inconvenient interruption by
     * a parent language (i.e. a server-side langauge), or whether it reached
     * a legitimate break. A server-side language isn't necessarily a dirty exit,
     * but if it comes in the middle of a token it is, because we need to resume
     * from it later. e.g.:
     *
     * var x = "this is \<?php echo 'a' ?\> string";
     */
    public $cleanExit = true;

    /**
     * @brief Child scanners
     *
     * Persistent storage of child scanners, name => scanner (instance)
     */
    protected $childScanners = array();

    /**
     * @brief Name of interrupted token, in case of a dirty exit
     *
     * exit state logs our exit state in the case of a dirty exit: this is the
     * rule that was interrupted.
     */
    protected $exitState = null;

    /**
     * @brief Recovery patterns for when we reach an untimely interrupt
     *
     * If we reach a dirty exit, when we resume we need to figure out how to
     * continue consuming the rule that was interrupted. So essentially, this
     * will be a regex which matches the rule without start delimiters.
     *
     * This is a map of rule => pattern
     */
    protected $dirtyExitRecovery = array();

    /**
     * @brief adds a child scanner
     * Adds a child scanner and indexes it against a name, convenience function
     */
    public function addChildScanner($name, $scanner)
    {
        $this->childScanners[$name] = $scanner;
    }

    // override string to hit the child scanners as well
    public function string($str = null)
    {
        if ($str !== null) {
            foreach ($this->childScanners as $s) {
                $s->string($str);
            }
        }
        return parent::string($str);
    }

    /**
     * @brief Sets the exit data to signify the exit is dirty and will need recovering from
     *
     * @param $token_name the name of the token which is being interrupted
     *
     * @throw Exception if no recovery data is associated with the given token.
     */
    public function dirtyExit($tokenName)
    {
        if (!isset($this->dirtyExitRecovery[$tokenName])) {
            throw new Exception('No dirty exit recovery data for '. $tokenName);
            $this->cleanExit = true;
            return;
        }
        $this->exitState = $tokenName;
        $this->interrupt = true;
        $this->cleanExit = false;
    }

    /**
     * @brief Attempts to recover from a dirty exit.
     *
     * This method should be called on @b every iteration of the main loop when
     * LuminousEmbeddedWebScript::$clean_exit is @b FALSE. It will attempt to
     * recover from an interruption which left the scanner in the middle of a
     * token. The remainder of the token will be in Scanner::match() as usual.
     *
     * @return the name of the token which was interrupted
     *
     * @note there is no reason why a scanner should fail to recover from this,
     * and failing is classed as an implementation error, therefore assertions
     * will be failed and errors will be spewed forth. A failure can either be
     * because no recovery regex is set, or that the recovery regex did not
     * match. The former should never have been tagged as a dirty exit and the
     * latter should be rewritten so it must definitely match, even if the match
     * is zero-length or the remainder of the string.
     *
     */
    public function resume()
    {
        assert(!$this->cleanExit);
        $this->cleanExit = true;
        $this->interrupt = false;
        if (!isset($this->dirtyExitRecovery[$this->exitState])) {
            throw new Exception(
                "Not implemented error: The scanner was interrupted mid-state (in state {$this->exitState}), but there "
                . "is no recovery associated with this state"
            );
            return null;
        }
        $pattern = $this->dirtyExitRecovery[$this->exitState];
        $m = $this->scan($pattern);
        if ($m === null) {
            throw new Exception('Implementation error: recovery pattern for ' . $this->exitState . ' failed to match');
        }
        return $this->exitState;
    }

    /**
     * @brief Checks for a server-side script inside a matched token
     *
     * @param $token_name The token name of the matched text
     * @param $match The string from the last match. If this is left @c NULL then
     * Scanner::match() is assumed to hold the match.
     * @param $pos The position of the last match. If this is left @c NULL then
     * Scanner::match_pos() is assumed to hold the offset.
     * @return @c TRUE if the scanner should break, else @c FALSE
     *
     *
     * This method checks whether an interruption by a server-side script tag,
     * LuminousEmbeddedWebScript::server_tags, occurs within a matched token.
     * If it does, this method records the substring up until that point as
     * the provided $token_name, and also sets up a 'dirty exit'. This means that
     * some type was interrupted and we expect to have to recover from it when
     * the server-side language's scanner has ended.
     *
     * Returning @c TRUE is a signal that the scanner should break immediately
     * and let its parent scanner take over.
     *
     */
    public function serverBreak($tokenName, $match = null, $pos = null)
    {
        if (!$this->embeddedServer) {
            return false;
        }
        if ($match === null) {
            $match = $this->match();
        }
        if ($match === null) {
            return false;
        }

        if (preg_match($this->serverTags, $match, $m_, PREG_OFFSET_CAPTURE)) {
            $pos_ = $m_[0][1];
            $this->record(substr($match, 0, $pos_), $tokenName);
            if ($pos === null) {
                $pos = $this->matchPos();
            }
            $this->pos($pos + $pos_);
            $this->dirtyExit($tokenName);
            return true;
        }
        return false;
    }

    /**
     * @brief Checks for a script terminator tag inside a matched token
     *
     * @param $token_name The token name of the matched text
     * @param $match The string from the last match. If this is left @c NULL then
     * Scanner::match() is assumed to hold the match.
     * @param $pos The position of the last match. If this is left @c NULL then
     * Scanner::match_pos() is assumed to hold the offset.
     * @return @c TRUE if the scanner should break, else @c FALSE
     *
     * This method checks whether the string provided as match contains the
     * string in LuminousEmbeddedWebScript::script_tags. If yes, then it records
     * the substring as $token_name, advances the scan pointer to immediately
     * before the script tags, and returns @c TRUE. Returning @c TRUE is a
     * signal that the scanner should break immediately and let its parent
     * scanner take over.
     *
     * This condition is a 'clean_exit'.
     */
    public function scriptBreak($tokenName, $match = null, $pos = null)
    {
        if (!$this->embeddedHtml) {
            return false;
        }
        if ($match === null) {
            $match = $this->match();
        }
        if ($match === null) {
            return false;
        }

        if (($pos_ = stripos($this->match(), $this->scriptTags)) !== false) {
            $this->record(substr($match, 0, $pos_), $tokenName);
            if ($pos === null) {
                $pos = $this->matchPos();
            }
            $this->pos($pos + $pos_);
            $this->cleanExit = true;
            return true;
        }
        return false;
    }
}

/// @endcond
