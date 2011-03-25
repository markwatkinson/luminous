<?PHP

/*
 * HAI
 * I HAS PERSONAL INTEREST IN LOLCODE THATS WHY ITS HERE KTHX.
 * BTW PHP IS MOSTLY CASE INSENSITIVE
 */
CLASS LUMINOUSLOLCODESCANNER EXTENDS LUMINOUSSIMPLESCANNER {
  
  FUNCTION STR_FILTER($TOKEN) {
    $TOKEN = LUMINOUSUTILS::ESCAPE_TOKEN($TOKEN);
    $STR = &$TOKEN[1];
    $STR = PREG_REPLACE('/:
    (?:
      (?:[\)o":]|&gt;)
      |\([a-fA-F0-9]*\)
      |\[[A-Z ]*\]
      |\{\w*\}
    )/x', '<VARIABLE>$0</VARIABLE>', $STR);
    
    RETURN $TOKEN;
  }

  FUNCTION INIT() {
    $this->ADD_FILTER('STRING', array($this, 'STR_FILTER'));
    
    $this->ADD_PATTERN('COMMENT', '/(?s:OBTW.*?TLDR)|BTW.*/');
    $this->ADD_PATTERN('STRING', '/" (?: [^":]+ | :.)* "/x');
    $this->ADD_PATTERN('STRING', "/' (?: [^':]+ | :.)* '/x");
    $this->ADD_PATTERN('OPERATOR', 
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
      /x');
    $this->ADD_PATTERN('NUMERIC', LUMINOUSTOKENPRESETS::$NUM_REAL);
    $this->ADD_PATTERN('IDENT', '/[a-zA-Z_]\w*\\??/');
    
    $this->ADD_IDENTIFIER_MAPPING('VALUE', array('FAIL',  'WIN'));
    $this->ADD_IDENTIFIER_MAPPING('TYPE', array('NOOB', 'NUMBAR', 'NUMBR', 
      'TROOF', 'YARN'));
    $this->ADD_IDENTIFIER_MAPPING('KEYWORD', array('A', 'CAN', 
    'DUZ', 'HAI', 
      'KTHX', 'KTHXBYE', 'HAS', 'HOW', 'I', 'IM', 'IN', 'IS', 'IZ',
      'ITS', 'ITZ', 
      'IF', 'FOUND', 'GTFO', 'MAEK', 'MEBBE', 'NO', 'NOW', 'O', 'OIC', 
      'OMG', 'OMGWTF', 'RLY?', 'R', 'SAY', 'SO', 'TIL', 'YA', 'YR', 'U',
      'WAI', 'WILE', 'WTF?'));
    $this->ADD_IDENTIFIER_MAPPING('FUNCTION', array('GIMMEH', 'VISIBLE', 
      'UPPIN', 'NERFIN'));
      
  }
}