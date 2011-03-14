<?php
/** Normal Diff */
class LuminousGrammarDiff extends LuminousGrammar
{

  
  function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('diff');
    $this->SetInfoVersion('r657');    
  } 
    
    
  private function SetNormal()
  {
    $this->simple_types = array(
      new LuminousSimpleRule(1, 'DIFF_RANGE', LUMINOUS_REGEX, 
      '/^[0-9]+(?:,[0-9]+)?[acd]?[0-9]+(,[0-9]+)?$/mi'),
      new LuminousSimpleRule(0, 'DIFF_OLD', LUMINOUS_REGEX,
      '/^&lt;.*$/mi'),
      new LuminousSimpleRule(0, 'DIFF_NEW', LUMINOUS_REGEX,
      '/^\&gt;.*$/mi')
    );
  }
  
  private function SetUnified()
  {
     $this->simple_types = array(
      new LuminousSimpleRule(1, 'DIFF_HEADER_NEW', LUMINOUS_REGEX,
        '/^\+{3}.*$/m'),        
      new LuminousSimpleRule(1, 'DIFF_HEADER_OLD', LUMINOUS_REGEX,
       '/^\-{3}.*$/m'),        
      new LuminousSimpleRule(1, 'DIFF_RANGE', LUMINOUS_REGEX, 
        '/^@@.*@@$/m'),        
      new LuminousSimpleRule(0, 'DIFF_OLD', LUMINOUS_REGEX,
        '/^\-(?=[^\-]).*$/m'),        
      new LuminousSimpleRule(0, 'DIFF_NEW', LUMINOUS_REGEX,
       '/^\+(?=[^\+]).*$/m')
    );    
  }
  
  private function SetContext()
  {
    $this->simple_types = array(     
      new LuminousSimpleRule(1, 'DIFF_RANGE', LUMINOUS_REGEX, 
      "/^[\-\*]{3}[ \t]\d+.*$/m"),        
      new LuminousSimpleRule(0, 'DIFF_OLD', LUMINOUS_REGEX,
      '/^!.*$/m'),        
      new LuminousSimpleRule(0, 'DIFF_NEW', LUMINOUS_REGEX,
      '/^\+.*$/m'),
      new LuminousSimpleRule(1, 'DIFF_HEADER_NEW', LUMINOUS_REGEX,
      '/^\*{3}.*$/m'),        
      new LuminousSimpleRule(1, 'DIFF_HEADER_OLD', LUMINOUS_REGEX,
      '/^\-{3}.*$/m'),         
    );
  }
  
  public function SetRuleset(&$str)
  {
    $normal = preg_match_all('/^&[gl]t;/m', $str, $m);
    $unified = preg_match_all('/^[+-]/m', $str, $m);
    $context = preg_match_all('/^[!+]/m', $str, $m);
    
    $max = max($normal, $unified);
    $max = max($max, $context);
    
    if ($max === $unified)
      $this->SetUnified();
    elseif($max === $context)
      $this->SetContext();
    else
      $this->SetNormal();
    
  }
    
}
