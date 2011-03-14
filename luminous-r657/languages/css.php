<?php
class LuminousGrammarCSS extends LuminousGrammar
{
  public $state_transitions = array(
    'GLOBAL' => array('TAG', 'COMMENT', 'STRING', 'BLOCK', 'VARIABLE'),
    'COMMENT' => null,
    'STRING' => null,
    'BLOCK' => array('COMMENT', 'STRING', 'VALUE', 'TYPE'),
    'KEYWORD' => null,
    'VALUE' => array('NUMERIC', 'IMPORTANT', 'STRING', 'COMMENT'),
    'TYPE' => null,
    'NUMERIC' => null,
  );
  
  public $state_type_mappings = array(
    'IMPORTANT' => 'KEYWORD',
    'TAG' => 'KEYWORD',
    'BLOCK' => null,
  );
    
  function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('css');
    $this->SetInfoVersion('r657');        
    
    $this->delimited_types = array(
      
      new LuminousDelimiterRule(0, 'BLOCK', LUMINOUS_STOP_AT_END, '{', '}'),
      
      new LuminousDelimiterRule(0, 'TYPE', LUMINOUS_REGEX|LUMINOUS_COMPLETE|LUMINOUS_STOP_AT_END,
         '/[a-z\-@]+\s*(?=:)/is'),
       new LuminousDelimiterRule(0, 'VALUE', LUMINOUS_REGEX|LUMINOUS_STOP_AT_END,
        '/(?<=:)/', '/(?<!:)(?=[;\\}])/'),
        
      
      //  this is the tag name part of the rule.
      new LuminousDelimiterRule(0, 'TAG', LUMINOUS_REGEX|LUMINOUS_COMPLETE|LUMINOUS_STOP_AT_END,
      '/(?<=\s|^)[@\-]?[a-z0-9_\-]+/i'
      ),
      
      new LuminousDelimiterRule(1, 'VARIABLE', LUMINOUS_COMPLETE|LUMINOUS_REGEX|LUMINOUS_STOP_AT_END,
        '/(?<=[:\.#])[\-a-z0-9_]+/i'),
      
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '%/\*(?:.*?)\*/%s', null, 'luminous_type_callback_comment'),
        
      new LuminousDelimiterRule(2, 'STRING', LUMINOUS_COMPLETE|LUMINOUS_REGEX|LUMINOUS_STOP_AT_END,
        '%([\'"]).*?(?:$|(?<!\\\\)(?:\\\\\\\\)*\\1)%m'),
        
//       new LuminousDelimiterRule(0, 'STRING', 0,
//         '"', '"'),
//       new LuminousDelimiterRule(0, 'STRING', 0,
//         "'", "'"),
        
      new LuminousDelimiterRule(1, 'NUMERIC', LUMINOUS_COMPLETE|LUMINOUS_REGEX|LUMINOUS_STOP_AT_END,
      '/(
        \#[a-fA-F0-9]{3,6}
        |
        (?<!\w)\d+(\.\d+)?(em|px|ex|ch|mm|cm|in|pt|%)?
      )/x'
      ),
      
      
      new LuminousDelimiterRule(1, 'IMPORTANT', LUMINOUS_COMPLETE|LUMINOUS_STOP_AT_END,
        '!important'),
        
        
    );
      
    
      
//     $this->simple_types = array(  
//       new LuminousSimpleRule(3, 'KEYWORD', LUMINOUS_REGEX,
//       '/@import/'),
//       new LuminousSimpleRule(4, 'VARIABLE', LUMINOUS_REGEX,
//         '/(?<=\s)!important/'),
//       new LuminousSimpleRule(1, 'NUMERIC', LUMINOUS_REGEX,
//       '/(
//         \#[a-fA-F0-9]{3,6}
//         |
//         (?<!\w)\d+(\.\d+)?(em|px|ex|ch|mm|cm|in|pt|%)?
//         )/x'),
//       new LuminousSimpleRule(3, 'FUNCTION', LUMINOUS_REGEX,
//         '/\b(url|rgba?)(?=\()/'),     
//         );
// 
// 
// 
//       
//       new LuminousSimpleRuleList(4, "OPERATOR", 0, 
//         array(':', '#', '.', '{', '}'))
//       );
      
      
  }
}


class LuminousGrammarCSSEmbedded extends LuminousGrammarCSS
{

  function __construct()
  {
    $this->ignore_outside = array(
      new LuminousBoundaryRule(LUMINOUS_REGEX|LUMINOUS_EXCLUDE, 
      "/(&lt;style.*?&gt;)/si", "/(&lt;\/style&gt;)/si")
      );
    $this->child_grammar = new LuminousGrammarHTML();
   
    parent::__construct();
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');          
  }
}