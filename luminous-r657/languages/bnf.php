<?php
class LuminousGrammarBNF extends LuminousGrammar
{
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
                         'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('bnf');
    $this->SetInfoVersion('r657');
    $this->operators = array();
  }
  
  private function SetCommonRules()
  {
    $this->delimited_types[] = 
        new LuminousDelimiterRule(0, 'STRING', 
          LUMINOUS_COMPLETE|LUMINOUS_REGEX, 
          '/(["\']).*?(?<!\\\)(?:\\\\\\\\)*\\1/'
        );
      
  }
  
  private function SetStrictRuleset()
  {
    $this->delimited_types = array_merge($this->delimited_types,
    array(
      new LuminousDelimiterRule(0, 'COMMENT', 0, '&lt;!', '&gt;'),
      new LuminousDelimiterRule(0, 'KEYWORD', LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
        '/(?<=&lt;)(?!&gt;).+?(?=&gt;)/s')
      )
    );
    $this->operators = array('::=', '\|');
  }
  
  private function SetExtendedRuleset()
  {

    $this->delimited_types = array_merge($this->delimited_types,
      array(
        new LuminousDelimiterRule(0, 'COMMENT', 0, '(*', '*)'),
        new LuminousDelimiterRule(0, 'OPTION', 0, '[', ']'),
        new LuminousDelimiterRule(0, 'REPITITION', 0, '{', '}'),
        new LuminousDelimiterRule(0, 'GROUP', 0, '(', ')'),
        new LuminousDelimiterRule(0, 'SPECIAL', 0, '?', '?'),
        new LuminousDelimiterRule(0, 'RULE', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
          "/(?<![&\w])(?>[\w\-]+)(?>(?:(?>[ \t]+)(?>[\w\-]+))*)/"),
        new LuminousDelimiterRule(0, 'ENTITY', 
          LUMINOUS_COMPLETE|LUMINOUS_REGEX,
          '/&(?>[^;]){1,10};/'),
        )
      );
    $this->operators = array_merge($this->operators, array(',', ';', '=', 
      '\*', '\[', '\]','\{', '\}', '\(', '\)', '\?'));
    
    
    $g = array('COMMENT', 'STRING', 'OPTION', 'REPITITION', 'GROUP', 'SPECIAL', 
      'OPERATOR', 'RULE', 'ENTITY');
    
    $this->state_transitions = array(
      'GLOBAL' => '*',
      'COMMENT' => null,
      'KEYWORD' => null,
      'STRING' => null,
      
      'OPTION' => $g,
      'REPITITION' => $g,
      'GROUP' => $g,
      'SPECIAL' => '!',
      );
    $this->state_type_mappings = array(
      'REPITITION' => 'VALUE',
      'GROUP' => 'VALUE',
      'OPTION' => 'VALUE',
      'SPECIAL' => 'VALUE',
      'RULE' => 'KEYWORD'
      );
  }
  
  
  public function SetRuleset(&$str)
  {
    $this->SetCommonRules();
    
    $s = explode("\n", $str);
    $set = false;
    foreach($s as $line)
    {
      if (preg_match("/^[\s]*&lt;[a-z0-9\-_]+&gt;[\s]*\:\:\=/i", $line))
      {
        $this->SetStrictRuleset();
        $set = true;
      }
      elseif(preg_match("/^[\s]*[a-z_\-0-9 \t]+=/i", $line))
      {
        $this->SetExtendedRuleset();
        $set = true;
      }
      
      if ($set)
        break;
    }
    
    if (!$set)
      $this->SetExtendedRuleset();
    
    $this->SetSimpleTypeRules();
    
    
  }
}
