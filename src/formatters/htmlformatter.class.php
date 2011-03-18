<?php

class LuminousFormatterHTML extends LuminousFormatter
{   
  public $height = 0;
  /** 
   * strict HTML standards: the target attribute won't be used in links
   * \since  0.5.7
   */
  public $strict_standards = false;
  
  protected $numberless_template = '<div class="code"><pre class="code">%s</pre></div>';
  
  protected $numbered_template = '<table class="code_container"><tr><td class="line_number_bar"><pre class="line_numbers">%s</pre></td><td class="code"><pre class="code">%s</pre></td></tr></table>';

  protected $template = '<div class="luminous"><div class="code_container" %s>%s</div></div>';

  private function Format_NoLineNumbers($src)
  {
    $lines = array();
    $lines_a = explode("\n", $src);
    $num = count($lines_a);
    foreach($lines_a as $index=>$line)
    {
      $l = $line;
      $num = $this->WrapLine($l, $this->wrap_length);
      // strip the newline if we're going to join it. Seems the easiest way to 
      // fix issue 10. http://code.google.com/p/luminous/issues/detail?id=10
      $l = substr($l, 0, -1);
      $lines []= $l;
    }
    $lines = implode("\n", $lines);
    return sprintf($this->numberless_template, $lines);    
  } 
  
  
  public 
  function Format($src)
  {
    $line_numbers = false;
    
    
    if ($this->tab_width - 1 > 0)
    {
      $tab_rep = "";
      for($i=0; $i<$this->tab_width; $i++)
        $tab_rep .= " ";
      $src = str_replace("\t", $tab_rep, $src);
    }
    if ($this->link)
      $src = $this->Linkify($src);
    
    $lines = "";
    if ($this->line_numbers)
      $lines = $this->Format_LineNumbers($src);
    else
      $lines = $this->Format_NoLineNumbers($src);
    
    
    $lines = preg_replace('/(?<=<\/)[A-Z_0-9]+(?=>)/', 'span', $lines);
    
    $cb = create_function('$matches', 
                          '$m1 = strtolower($matches[1]);
                          return "<span class=\'" . $m1 . "\'>";
                          ');
    
    $lines = preg_replace_callback('/<([A-Z_0-9]+)>/', $cb, 
                                   $lines);
    $markup =  $lines ; 
    $h = "" . $this->height;
    $h = trim($h);
    $css = "";
    
    if (strlen($h) && (int)$h > 0)
    {
      $units = !ctype_digit($h[strlen($h)-1]);
      $css = " style=\"max-height:";
      $css .= $h;
      if (!$units)
        $css .= "px";
      $css .= ";\" ";
    }
    else 
      $css = ' style="overflow:visible !important"';
    
    return sprintf($this->template, $css, $markup);
  }
  
  
  protected function Linkify_cb($matches)
  {
    $uri = (isset($matches[1]) && strlen(trim($matches[1])))? $matches[0] : 
    "http://" . $matches[0];
    
    // we dont want to link if it would cause malformed HTML
    $open_tags = array();
    $close_tags = array();
    preg_match_all("/<(?!\/)([^\s>]*).*?>/", $matches[0], $open_tags,
      PREG_SET_ORDER);
    preg_match_all("/<\/([^\s>]*).*?>/", $matches[0], $close_tags,
      PREG_SET_ORDER);
    
    if (count($open_tags) != count($close_tags))
      return $matches[0];
    if (isset($open_tags[0]) 
      && trim($open_tags[0][1]) !== trim($close_tags[0][1])
    )
      return $matches[0];
    
    $uri = strip_tags($uri);
    
    $target = ($this->strict_standards)? '' : 'target="_blank"';
    return "<a href=\"$uri\" class=\"link\" $target>" 
      . "$matches[0]</a>";
  }
  
  protected function Linkify($src)
  {    
    if (stripos($src, "http") === false && stripos($src, "www") === false)
        return $src;
    
    $chars = "0-9a-zA-Z\$\-_\.+!\*,%";
    // everyone stand back, I know regular expressions
    $src = preg_replace_callback(
      "@(?<![\w])
      (?:(https?://(?:www[0-9]*\.)?) | (?:www\d*\.)   )
      
      # domain and tld
      (?:[$chars]+)+\.[$chars]{2,}
      # we don't include tags at the EOL because these are likely to be 
      # line-enclosing tags.
      (?:[/$chars/?=\#;]+|&amp;|<[^>]+>(?!$))*
      @xm",
      array($this, 'Linkify_cb'), $src);
      
    return $src;
  }
  
  // this is what we're using now for line numbering.
  private 
  function Format_LineNumbers($src)
  {    
    
    $lineno = 1;
    $linenos='';
    $lines = '';
    
    $lines_a = explode("\n", $src);
    $lines_untagged = explode("\n", strip_tags($lines));
    
    $id = rand();    
    
    // this seems to run a bit faster if we keep the literals out of
    // the loop.
    
    $class = "line_number";
    $class_emph = " line_number_emphasised";
    
    $line_no_tag0 = '<a id="lineno_' . $id . '_';
    $line_no_tag1 = '" class="' . $class;
    $line_no_tag2 = '"><span class="line_number">&nbsp;';
    $line_no_tag3 = "&nbsp;\n</span></a>";
    
    $wrap_line = "<span class='line_number'>&nbsp;|_\n</span>";
    
    $line_tag0 = '<span id="line_' . $id . '_';
    $line_tag1 = '" class="line';
    $class_alt = ' line_alt';
    $line_tag2 = '">';
    $line_tag3 = '</span>';
    
    $line_delta = 3;
    foreach($lines_a as $line)
    {      
      
      $linenos .= $line_no_tag0 . $lineno . $line_no_tag1;
      if ($lineno % 5 === 0)
        $linenos .= $class_emph;
      $linenos .= $line_no_tag2 . $lineno . $line_no_tag3;
      
      
      $num = $this->WrapLine($line, $this->wrap_length);  
      
      for ($i=1; $i<$num; $i++)
        $linenos .= $wrap_line;
      
      
      $lines .= $line_tag0 . $lineno . $line_tag1;
      if ($lineno % 2 === 0)
        $lines .= $class_alt;
      $lines .= $line_tag2 . $line . $line_tag3;
      
      ++$lineno;
      
    }
    return sprintf($this->numbered_template, $linenos, $lines);
  }      
}


class LuminousFormatterHTMLInline extends LuminousFormatterHTML
{   
  protected $template =  '<div class="luminous luminous_inline"><div class="code_container" %s>%s</div></div>';
    
  public function Format($src) 
  {
    $this->line_numbers = false;
    $this->height = 0;
    return parent::Format($src);
  }
  
}