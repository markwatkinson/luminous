<?PHP

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

/*
 * HAI
 * I HAS PERSONAL INTEREST IN LOLCODE THATS WHY ITS HERE KTHX.
 * BTW PHP IS MOSTLY CASE INSENSITIVE BUT PSR-1 REQUIRES CLASS NAMES TO BE IN
 * STUDLY CASE AND METHOD NAMES TO BE IN CAMEL CASE AND PSR-2 REQUIRES KEYWORDS
 * TO BE LOWER CASE
 */
class LolcodeScanner extends SimpleScanner
{
    public function funcdefOverride($MATCHES)
    {
        $this->RECORD($MATCHES[0], 'KEYWORD');
        $this->POSSHIFT(STRLEN($MATCHES[0]));
        $this->SKIPWHITESPACE();
        if ($this->SCAN('/[a-z_]\w*/i')) {
            $this->RECORD($this->MATCH(), 'USER_FUNCTION');
            $this->userDefs[$this->MATCH()] = 'FUNCTION';
        }
    }

    public function strFilter($TOKEN)
    {
        $TOKEN = Utils::ESCAPETOKEN($TOKEN);
        $STR = &$TOKEN[1];
        $STR = PREG_REPLACE(
            '/:
                (?:
                    (?:[\)o":]|&gt;)
                    |\([a-fA-F0-9]*\)
                    |\[[A-Z ]*\]
                    |\{\w*\}
                )
            /x',
            '<VARIABLE>$0</VARIABLE>',
            $STR
        );
        return $TOKEN;
    }

    public function init()
    {
        $this->ADDFILTER('STRING', array($this, 'STRFILTER'));
        $this->REMOVEFILTER('constant');

        $this->ADDPATTERN('COMMENT', '/(?s:OBTW.*?TLDR)|BTW.*/');
        $this->ADDPATTERN('STRING', '/" (?> [^":]+ | :.)* "/x');
        $this->ADDPATTERN('STRING', "/' (?> [^':]+ | :.)* '/x");
        $this->ADDPATTERN(
            'OPERATOR',
            '/
                \\b
                (?:
                    (?:ALL|ANY|BIGGR|BOTH|DIFF|EITHER|PRODUKT|QUOSHUNT
                        |MOD|SMALLR|SUM|WON)\s+OF\\b
                    |
                    BOTH\s+SAEM\\b
                    |
                    (?:BIGGR|SMALLR)\s+THAN\\b
                    |
                    (?:AN|NOT)\\b
                )
            /x'
        );
        $this->ADDPATTERN('FUNC_DEF', '/how\s+duz\s+i\\b/i');
        $this->overrides['FUNC_DEF'] = array($this, 'FUNCDEFOVERRIDE');
        $this->ADDPATTERN('NUMERIC', TokenPresets::$NUM_REAL);
        $this->ADDPATTERN('IDENT', '/[a-zA-Z_]\w*\\??/');

        $this->ADDIDENTIFIERMAPPING('VALUE', array('FAIL',  'WIN'));
        $this->ADDIDENTIFIERMAPPING('TYPE', array('NOOB', 'NUMBAR', 'NUMBR', 'TROOF', 'YARN'));
        $this->ADDIDENTIFIERMAPPING('KEYWORD', array(
            'A',
            'CAN',
            'DUZ',
            'HAI',
            'KTHX',
            'KTHXBYE',
            'HAS',
            'HOW',
            'I',
            'IM',
            'IN',
            'IS',
            'IZ',
            'ITS',
            'ITZ',
            'IF',
            'FOUND',
            'GTFO',
            'MAEK',
            'MEBBE',
            'NO',
            'NOW',
            'O',
            'OIC',
            'OMG',
            'OMGWTF',
            'RLY',
            'RLY?',
            'R',
            'SAY',
            'SO',
            'TIL',
            'YA',
            'YR',
            'U',
            'WAI',
            'WILE',
            'WTF?'
        ));
        $this->ADDIDENTIFIERMAPPING('FUNCTION', array('GIMMEH', 'VISIBLE', 'UPPIN', 'NERFIN'));
    }

    public static function guessLanguage($SRC, $INFO)
    {
        $P = 0.0;
        $STRS = array(
            'OMGWTF',
            'I CAN HAS',
            'GTFO',
            'HOW DUZ I',
            'IM IN YR',
            'IM IN UR',
            'I HAS A',
            'I HAZ A',
            ' UPPIN',
            'NERFIN',
            'TROOF',
            'NUMBAR',
            'NUMBR'
        );
        foreach ($STRS as $STR) {
            if (STRPOS($SRC, " $STR ") !== false) {
                $P += 0.1;
            }
        }
        return $P;
    }
}
