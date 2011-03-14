<?php

/**
 * A class encapsulating the process of searching for a substring efficiently
 * by either regular expressions or simple strings
 * it handles caching of results.
 * 
 * One instance should be used only incrementally along a string.
 * i.e. do not call it with index = 5 then index = 1.
 * 
 */
class LuminousStringSearch
{
  
  public $case_sensitive = false;
  public $string = null;
  
  /** we cache this if the language is not case sensitive, because otherwise 
    * we are forced to use stripos. This leads to apparently exponential 
    * complexity (w.r.t. input length), presumably because stripos has to call
    * strtolower every time a rule is evaluated (so the complexity is 
    * proportional to the time strtolower takes, multiplied by the number of 
    * rule matches, i.e. O(n*n)), when, in reality, it only  needs to be 
    * calculated once.
    * http://code.google.com/p/luminous/issues/detail?id=9
    */
  public $lower_case = null;
  
  private $strpos_cache = array();
  private $preg_cache = array();
  
  
  public 
  function __construct($string, $case_sensitive=true)
  {
    $this->string = $string;
    $this->case_sensitive = $case_sensitive;
    if (!$case_sensitive)
      $this->lower_case = strtolower($string);
  }
  
  /**
   * returns the index or false
   */
  public function
  StrSearch($search, $index, $upper_bound = null)
  {
    $r = false;
    if (isset($this->strpos_cache[$search]))
    {
      $r = $this->strpos_cache[$search];
      
      if ($r === false)
        return false;
      
      if ($r >= $index)
        return $r;
    }
    
    $str = $this->string;
    if (!$this->case_sensitive)
    {
      $str = $this->lower_case;
      $search = strtolower($search);
    }
    
    $r = strpos($str, $search, $index);
    
    $this->strpos_cache[$search] = $r;
    
    return $r;
  }
  
  
  
  /**
   * returns the index or false
   */  
  public function 
  PregSearch($search, $index, &$matching_string, &$matches=false)
  {    
    $match = null;
    if (!$this->case_sensitive)
      $search .= 'i';
    
    $r = false;
    if (isset($this->preg_cache[$search]))
    {
      $a = $this->preg_cache[$search];
      $r = $a[0];
      
      if ($r === false)
        return false;
      
      $matching_string = $a[1];
      $matches = $a[2];
      if ($r >= $index)
        return $r;
    }
  
    $m = array();
    
    if (!preg_match($search, $this->string, $m, PREG_OFFSET_CAPTURE, 
      $index))
    {
      $this->preg_cache[$search] = array(false);
      return false;
    }
    
    $r = $m[0][1];
    $match = $m[0][0];
    $m_ = false;
    if (isset($m) && $matches !== false)
    {
      $m_ = array();
      foreach($m as $i=>$v)
        $m_[$i] = $v[0];
    }
    $this->preg_cache[$search] = array($r, $match, $m_);
    
    $matching_string = $match;  
    $matches = $m_;
    return $r;
  }  
  
  
}