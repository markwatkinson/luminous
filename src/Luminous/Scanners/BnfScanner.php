<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\StatefulScanner;

/*
 * BNF has a lot of different variants and matching them all is pretty much
 * impossible.
 *
 * We're going to match the standard BNF and extended BNF and hopefully a
 * few very similar dialects
 */

class BnfScanner extends StatefulScanner
{
    public function userDefExt($matches)
    {
        if ($matches[1] !== '') {
            $this->record($matches[1], null);
        }
        $this->recordToken($matches[2], 'USER_FUNCTION');
        $this->userDefs[$matches[2]] = 'VALUE';
        $this->posShift(strlen($matches[1]) + strlen($matches[2]));
    }

    private function setStrict()
    {
        // no transition table necessary, I think
        $this->addPattern('COMMENT', '/<![^>]*>/');
        $this->addPattern('KEYWORD', '/(?<=^<)[^>]+(?=>)/m');
        $this->addPattern('KEYWORD', '/(?<=^\\{)[^\\}]+(?=\\})/m');
        $this->addPattern('VALUE', '/(?<=\\{)[^\\}]+(?=\\})/');
        $this->addPattern('VALUE', '/[\\-\w]+/');
    }

    private function setExtended()
    {
        $this->addPattern('COMMENT', '/\\(\\* .*? \\*\\)/sx');
        $this->addPattern('OPTION', '/\\[/', '/\\]/');
        $this->addPattern('REPETITION', '/\\{/', '/\\}/');
        $this->addPattern('GROUP', '/\\(/', '/\\)/');
        $this->addPattern('SPECIAL', '/\\?/', '/\\?/');

        $ident = '(?:[\w\\-]+)';
        $this->addPattern('RULE', "/(^[ \t]*)($ident)(\s*(?![[:alnum:]\s]))/mi");
        $this->overrides['RULE'] = array($this, 'userDefExt');
        $this->addPattern('IDENT', "/$ident/");

        // technically I don't know if we really need to worry about a transition
        // table, but here we are anyway
        $all = array('COMMENT', 'OPTION', 'REPETITION', 'GROUP', 'SPECIAL', 'STRING', 'IDENT', 'OPERATOR');
        $almostAll = array_filter($all, function ($x) {
            return $x !== "SPECIAL";
        });
        $this->transitions = array(
            'initial' => array_merge(array('RULE'), $all),
            'OPTION' => $all,
            'REPETITION' => $all,
            'GROUP' => $all,
            'SPECIAL' => $almostAll
        );

        $this->ruleTagMap = array(
            'OPTION' => null,
            'REPETITION' => null,
            'GROUP' => null,
            'SPECIAL' => null
        );
    }

    public function init()
    {
        // the original BNF uses <angle brackets> to delimit its
        // production rule names
        if (preg_match('/<\w+>/', $this->string())) {
            $this->setStrict();
        } else {
            $this->setExtended();
        }
        $this->addPattern('STRING', TokenPresets::$SINGLE_STR_SL);
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR_SL);
        $this->addPattern('OPERATOR', '/[*\\-=+;:\\|,]+/');
        // assume a few chars at bol indicate a commented line
        $this->addPattern('COMMENT', '/^[!%-;].*/m');

        $this->removeFilter('constant');
        $this->removeFilter('comment-to-doc');
    }

    public static function guessLanguage($src, $info)
    {
        // being honest, BNF is going to be so rare that if we ever return
        // anything other than 0, it's more likely that we're obscuring the
        // correct scanner than correctly identifying BNF.
        return 0;
    }
}
