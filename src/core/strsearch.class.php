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
  private $string;
  private $cache = array();
  
  public function __construct($str) {
    $this->string = $str;
  }
  /**
   * returns the index or false
   */  
  public function 
  match($search, $index, &$matches)
  {    
    $r = false;
    if (isset($this->cache[$search])) {
      $a = $this->cache[$search];
      if ($a === false) return false;
      
      $r = $a[0];
      $matches = $a[1];
      assert($matches !== null);
      
      if ($r >= $index)
        return $r;
    }
   
    if (!preg_match($search, $this->string, $matches_, PREG_OFFSET_CAPTURE, 
      $index))  {
      $this->cache[$search] = false;
      return false;
    }
    
    $r = $matches_[0][1];

    foreach($matches_ as $i=>&$v)
      $v = $v[0];

    $this->cache[$search] = array($r, $matches_);
    
    $matches = $matches_;
    return $r;
  }
}