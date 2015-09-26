<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\MatlabKeywords;

/*
 * Matlab's pretty simple. Hoorah
 */
class MatlabScanner extends SimpleScanner
{
    // Comments can nest. This beats a PCRE recursive regex, because they are
    // pretty flimsy and crash/stack overflow easily
    public function commentOverride($matches)
    {
        $this->nestableToken('COMMENT', '/%\\{/', '/%\\}/');
    }

    public function init()
    {
        // these can nest so we override this
        $this->addPattern('COMMENT_ML', '/%\\{/');
        $this->addPattern('COMMENT', '/%.*/');
        $this->addPattern('IDENT', '/[a-z_]\w*/i');
        // stray single quotes are a unary operator when they're attached to
        // an identifier or return value, or something. so we're going to
        // use a lookbehind to exclude those
        $this->addPattern('STRING', "/(?<![\w\)\]\}']) ' (?: [^']+ | '')* ($|')/x");
        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('OPERATOR', "@[¬!%^&*\-+=~;:|<>,./?]+|'@");

        $this->overrides = array('COMMENT_ML' => array($this, 'commentOverride'));

        $this->addIdentifierMapping('KEYWORD', MatlabKeywords::KEYWORDS);
        $this->addIdentifierMapping('VALUE', MatlabKeywords::VALUES);
        // http://www.mathworks.com/support/functions/alpha_list.html?sec=8
        $this->addIdentifierMapping('FUNCTION', MatlabKeywords::FUNCTIONS);
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0;
        // matlab comments are quite distinctive
        if (preg_match('/%\\{.*%\\}/s', $src)) {
            $p += 0.25;
        }
        return $p;
    }
}
