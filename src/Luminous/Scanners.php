<?php

/** @cond ALL */

/**
 * @file scanners.class.php
 * @brief Scanner lookup table definition.
 */

 namespace Luminous;

/**
 * @class LuminousScanners
 * @author Mark Watkinson
 * @brief A glorified lookup table for languages to scanners.
 * One of these is instantiated in the global scope at the bottom of this source.
 * The parser assumes it to exist and uses it to look up scanners.
 * Users seeking to override scanners or add new scanners should add their
 * scanner into '$luminous_scanners'.
 */
class Scanners
{
    /**
     * The language=>scanner lookup table. Scanner is an array with keys:
     * scanner (the string of the scanner's class name),
     * file (the path to the file in which its definition resides)
     * dependencies (the language name for any scanners it this scanner
     * either depends on or needs to instantiate itself) */
    private $lookupTable = array();

    /**
     * Language name of the default scanner to use if none is found
     * for a particular language
     */
    private $defaultScanner = null;

    private $descriptions = array();

    /**
     * Adds a scanner into the table, or overwrites an existing scanner.
     *
     * @param language_name may be either a string or an array of strings, if
     *    multiple languages are to use the same scanner
     * @param $scanner the name of the LuminousScanner object as string, (not an
     * actual instance!). If the file is actually a dummy file (say for includes),
     * leave $scanner as @c null.
     * @param lang_description a human-readable description of the language.
     */
    public function addScanner($languageName, $scanner, $langDescription)
    {
        $dummy = $scanner === null;
        $d = array();

        $insert = array(
            'scanner'      => $scanner,
            'description'  => $langDescription
        );
        if (!is_array($languageName)) {
            $languageName = array($languageName);
        }
        foreach ($languageName as $l) {
            $this->lookupTable[$l] = $insert;
            if (!$dummy) {
                $this->addDescription($langDescription, $l);
            }
        }
    }

    private function addDescription($languageName, $languageCode)
    {
        if (!isset($this->descriptions[$languageName])) {
            $this->descriptions[$languageName] = array();
        }
        $this->descriptions[$languageName][] = $languageCode;
    }


    private function unsetDescription($languageName)
    {
        foreach ($this->descriptions as &$d) {
            foreach ($d as $k => $l) {
                if ($l === $languageName) {
                    unset($d[$k]);
                }
            }
        }
    }

    /**
     * Removes a scanner from the table
     *
     * @param language_name may be either a string or an array of strings, each of
     *    which will be removed from the lookup table.
     */
    public function removeScanner($languageName)
    {
        if (is_array($languageName)) {
            foreach ($languageName as $l) {
                unset($this->lookupTable[$l]);
                $this->unsetDescription($l);
            }
        } else {
            $this->unsetDescription($languageName);
            unset($this->lookupTable[$languageName]);
        }
    }

    /**
     * Sets the default scanner. This is used when none matches a lookup
     * @param scanner the LuminousScanner object
     */
    public function setDefaultScanner($scanner)
    {
        $this->defaultScanner = $scanner;
    }


    /**
     * Method which retrives the desired scanner array, and
     * recursively settles the include dependencies while doing so.
     * @param language_name the name under which the gramar was originally indexed
     * @param default if true: if the scanner doesn't exist, return the default
     *    scanner. If false, return false
     * @return the scanner-array stored for the given language name
     * @internal
     * @see LuminousScanners::GetScanner
     */
    private function getScannerArray($languageName, $default = true)
    {
        $g = null;
        if (array_key_exists($languageName, $this->lookupTable)) {
            $g =  $this->lookupTable[$languageName];
        } elseif ($this->defaultScanner !== null && $default === true) {
            $g = $this->lookupTable[$this->defaultScanner];
        }

        if ($g === null) {
            return false;
        }

        return $g;
    }

    /**
     * Returns a scanner for a language
     * @param language_name the name under which the gramar was originally indexed
     * @param default if true: if the scanner doesn't exist, return the default
     *    scanner. If false, return false
     * @return The scanner, the default scanner, or null.
     */
    public function getScanner($languageName, $default = true, $instance = true)
    {
        $g = $this->getScannerArray($languageName, $default);

        if ($g !== false) {
            return $instance ? new $g['scanner'] : $g['scanner'];
        }
        return null;
    }

    public function getDescription($languageName)
    {
        $g = $this->getScannerArray($languageName, true);
        if ($g !== false) {
            return $g['description'];
        }
        return null;
    }

    /**
     * Returns a list of known aliases for scanners.
     * @return a list, the list is such that each item is itself a list whose
     *    elements are aliases of the same scanner. eg:
     * [
     *    ['c', 'cpp'],
     *    ['java'],
     *    ['py', 'python']
     * ]
     * etc.
     */
    public function listScanners()
    {
        $l = $this->descriptions;
        return $l;
    }
}

/** @endcond */
