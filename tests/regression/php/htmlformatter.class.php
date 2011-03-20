<?php

class LuminousFormatterHTML extends LuminousFormatter
{   
  public $height = 0;
  
  private function Format_NoLineNumbers($src)
  {
    $lines = '';
    $lines_a = explode("\n", $src);
    $lines_untagged = explode("\n", strip_tags($lines));
    
    foreach($lines_a as $index=>&$line)
    {
      $l = $line;
      $num = $this->WrapLine($l, $this->wrap_length);
      $lines .= $l;
    }
    
    return "<div class='code'><pre class='code'>$lines</pre></div>";
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
    
    return "<div class=\"luminous\">" 
           . "<div class=\"code_container\" $css>"
             . "$markup</div></div>";
  }
  
  protected function Linkify($src)
  {    
    if (stripos($src, "http://") === false && stripos($src, "www.") === false)
        return $src;
    
    $cb = create_function('$matches', ' 
      $uri = (isset($matches[1]) && strlen(trim($matches[1])))? $matches[0] : 
      "http://" . $matches[0];
      
      // we dont want to link if it would cause malformed HTML
      $open_tags = array();
      $close_tags = array();
      preg_match_all("/<(?!\/)([^\s>]*).*?>/", $matches[0], $open_tags, PREG_SET_ORDER);
      preg_match_all("/<\/([^\s>]*).*?>/", $matches[0], $close_tags, PREG_SET_ORDER);
      
      if (count($open_tags) != count($close_tags))
        return $matches[0];
      if (isset($open_tags[0]) && trim($open_tags[0][1]) !== trim($close_tags[0][1]))
        return $matches[0];
      
      $uri = strip_tags($uri);
      
      return "<a href=\"$uri\" class=\"link\" target=\"_blank\">$matches[0]</a>";
      ');
    
    $chars = "0-9a-zA-Z\$\-_\.+!\*,%";
    // everyone stand back, I know regular expressions
    $src = preg_replace_callback(
      "@(?<![\w])
      (?:(https?://(?:www[0-9]*\.)?) | (?:www\d*\.)   )
      
      # domain and tld
      (?:[$chars]+)+\.[$chars]{2,}
      # we don't include tags at the EOL because these are likely to be 
      # line-enclosing tags.
      # same for quotes.
      (?:[/$chars/?=\#;]+|&amp;|<[^>]+>(?!$)|'(?!\s))*
      @xm",
      $cb, $src);
      
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
    
    return "<table class='code_container'><tr><td class='line_number_bar'>" 
           . "<pre class='line_numbers' style=''>$linenos</pre></td>"
             . "\n<td class='code'><pre class='code'>$lines</pre>"
               . "</td></tr></table>";
  }      
}