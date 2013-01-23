<?php

/*
Copyright 2010 Mark Watkinson. All rights reserved.

Redistribution and use in source and binary forms, with or without 
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, 
this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice, 
this list of conditions and the following disclaimer in the documentation 
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY Mark Watkinson ``AS IS'' AND ANY EXPRESS OR 
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF 
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO 
EVENT SHALL Mark Watkinson OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT 
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, 
OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, 
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are 
those of the authors and should not be interpreted as representing official 
policies, either expressed or implied, of Mark Watkinson.
*/




/**
 * \brief A simple markup parser (the parser and the markup is simple)
 * \package MarkupLite
 * \author Mark Watkinson
 * 
 * 
 * The highlight and linkifier callbacks can be used to provide syntax 
 * highlighting to {{{ }}} blocks, and to look up link identifiers. 
 * highlight_cb should be defined as 
 *      str function($code, $language) 
 * and should return the formatted/highlighted code block.
 * 
 * linkifier_cb should be defined as:
 *   mixed function($identifier)
 * and should return either a URL to the linked content, or boolean false if 
 * the identifier is meaningless.
 * 
 * Call Format() to format a string of text after setting your own handlers
 * and callbacks.
 * 
 */


class MarkupLite
{  
  private $hidden_cache = array();  
  private $handlers = array();
  
  public $highlight_cb = null;
  public $linkifier_cb = null;
 
  private $headings = array();
  
  function MarkupLite()
  {
    $this->AddHandler('prepare', array($this, 'PreparePost'), 0);
    $this->AddHandler('code', array($this, 'ParseCodeBlock'), 3);   
    $this->AddHandler('alignment', array($this, 'ParseAlignment'), 5);
    $this->AddHandler('tables', array($this, 'ParseTables'), 5);
    $this->AddHandler('text', array($this, 'ParseText'), 5);
    $this->AddHandler('dividors', array($this, 'ParseDividors'), 5);
    $this->AddHandler('lists', array($this, 'ParseLists'), 5);
    $this->AddHandler('links', array($this, 'ParseLinks'), 5);
    
    $this->AddHandler('headers', array($this, 'ParseHeaders'), 5);
    
    $this->AddHandler('toc', array($this, 'ParseTableOfContents'), 7);
    
    $this->AddHandler('whitespace', array($this, 'ParseWhitespace'), 9);
    $this->AddHandler('cleanup', array($this, 'Unhide'), 10);
  }
  
  /** 
   * Adds a handler
   * 'func' is a function which receives the string as its only argument.
   * It should return the string (with modifications) afterwards
   * 
   * Priority is an integer 0-10 which allows the caller to define a simple
   * hierarchy of what order the handlers should be executed in
   */ 
  function AddHandler($name, $func, $priority)
  {
    $handler = array(
      'name'=>$name,
      'func'=>$func,
      'priority'=>$priority
      );
    $this->handlers[] = $handler;
  }
  
  function RemoveHandler($name)
  {
    foreach($this->handlers as $x=>$h)
    {
      if ($h['name'] == $name)
        unset($this->handlers[$x]);
    }
  }
  
  function Hide($str)
  {
    
    $md5 = md5($str);
    $this->hidden_cache[$md5] = $str;
    return "<$md5>";    
  }
  
  function GenerateTableOfContents($matches)
  {
    
    if (empty($this->headings))
      return "";
    
    $i = 0;
    $base_depth = 0;
    // urrgh
    if (isset($matches[1]) && strlen(trim($matches[1])))
    {
      $i = false;
      $heading = trim($matches[1]);
      for ($j=0; $j<count($this->headings); $j++)
      {
        if ($this->headings[$j]['title'] == $heading)
        {
          $i=$j+1;
          break;
        }
      }
      if ($i === false) {
        if (preg_match('/^\d+/', $matches[1])) {
          $base_depth = (int)$matches[1];
          for ($j=0; $j<count($this->headings); $j++)
            if ($this->headings[$j]['depth'] === $base_depth)
            {
              $i=$j;
              break;
            }
        }
        if ($i === false) return '';
      }
      else {
        $base_depth = $this->headings[$i]['depth'];
      }
    }
    
    $toc = "<div class='toc' style='margin-left:1em'>";
    $toc .= '<span class="header">Contents:</span>';
    for (; $i<count($this->headings); $i++)
    {    
      $h = $this->headings[$i];
      if ($h['depth'] < $base_depth) {
        break;
      }
      $indent = ($h['depth'] - $base_depth) ;//-1*0.5;
      $line = "<div class='toc_line' style='padding-left:{$indent}em;'>";
      $line .= "<a href='#{$h['id']}'>{$h['title']}</a></div>";
      $toc .= $line;
    }
    $toc .= "</div>";
    return $toc;
  }
  function ParseTableOfContents($str)
  {
   
    $str = preg_replace_callback("/
      \\\contents
      (?:
        [ \t]+
        (.*)
      )?/x", 
      array($this, 'GenerateTableOfContents'), $str);
    return $str;
  }
  
  function ParseAlignment($str)
  {
    $str = preg_replace("/^#\+{2}\n*(.*?)\n*-{2}#/sm", 
      "<div style='text-align:right'>$1</div>", 
      $str);
    $str = preg_replace("/^#\+\n*(.*?)\n*-#/sm", 
      "<div style='text-align:center'>$1</div>", 
      $str);
    return $str;
  }
  
  
  function ParseTablesCb($matches)
  {
    $rows = explode("\n", $matches[0]);
    $table = "<table>";
    foreach($rows as $r)
    {
      $r = trim($r);
      if ($r == "")
        continue;
      $cols = explode("||", $r);
      array_pop($cols);
      unset($cols[0]);      
      $row = "";
      foreach ($cols as $c)
      {
        $title = "";
        if (isset($c[0]) && $c[0] == "=")
        {
          $title = " class='title'";
          $c = substr($c, 1);
        }
        $c = trim($c);        
        $row .= "<td$title> $c </td>";
      }
      $table .= "<tr>$row</tr>";
    }
    $table .= "</table>";
    return $table;    
  }
  
  function ParseTables($str)
  {
    $str = preg_replace_callback("/(^\s*(\|\|.*\|\|)([ \t]*\n|$))+/m", 
      array($this, 'ParseTablesCb'), $str);
    
    return $str;
  }
  
  
  function ParseCodeBlockCb($matches)
  {
    $flags = trim($matches[1]);
    $code = trim($matches[2]);

    if ($flags == "raw" || $flags == "")
      return $this->Hide($code);
    elseif(preg_match("/lang(?:uage)?=(.*)/", $flags, $m))
    {
      if ($this->highlight_cb !== null)
        
        return $this->Hide(
          "<div class=code_example_highlighted>" .
                             call_user_func($this->highlight_cb, $code, $m[1])
                             . "</div>"
                             );
      return $this->Hide("<div class=code_example>" . 
                         htmlentities($code) . "</div>");  
    }
    elseif ($flags == "verbatim")
      return $this->Hide("<div class=code_example>" . 
                         htmlentities($code) . "</div>");
  }
  
  function ParseInlineCode($matches)
  {
    return $this->Hide("<span class='inline-code'>" . htmlentities($matches[1]) 
      . "</span>");
  }
  
  function ParseCodeBlock($str)
  {
    $str = preg_replace_callback("/\{\{\{(.*?$)(.*?)\}\}\}/ms", 
      array($this, 'ParseCodeBlockCb'), $str);
    $str = preg_replace_callback("/`(.*?)`/", array($this, 'ParseInlineCode'),
      $str);

    return $str;
  }
  
  function Unhide($str)
  {
    foreach($this->hidden_cache as $checksum=>$content)
    {
      $str = str_replace("<$checksum>", $content, $str);
      unset($this->hidden_cache[$checksum]);
    }
    return $str;
  }
  
  function PreparePost($str)
  {
    $str = trim($str);
    $str = str_replace("\r\n", "\n", $str);
    $str = str_replace("\r", "\n", $str);
    $str = preg_replace("/^[ \t]+$/m", "", $str);
    
    return $str;
  }

  function ParseHeadersCb($matches)
  {
    $x = strlen($matches[1]); 
    $title = trim($matches[2]);
    
    $id = "";
    $class = '';
    if (isset($matches[3]))
      $class = trim($matches[3]);
    $id = trim($matches[4]);
    if (!strlen($id))
      $id = 'id_' . md5($title);
    $this->headings[] = array('title'=>$title, 'id'=>$id, 'depth'=>$x);
    $c = '';
    if ($class) 
      $c = "class='$class'";
    return "<h$x id='$id' $c><span>$title</span></h$x>";
  }

  function ParseHeaders($str)
  {
    return preg_replace_callback("/^([=]+)(.*?)(?:\|(.*?))?\\1(.*)/m", 
      array($this, 'ParseHeadersCb'), $str);
  }

  function ParseWhitespace($str)
  {
    
    $str = preg_replace("/(?<=^|\n)([ ]+)(.*)/", '<blockquote>$2</blockquote>',  $str);
    $block_elements = "((h[0-9]+)|table|blockquote|([ou]l))";
    $str = preg_replace("/\n*(<(\/?$block_elements)>)\n*/", "$1", $str);
    $str = preg_replace("/\n\n+/", '<p>',  $str);
    $str = preg_replace("/\n/", '<br>',  $str);  
    
    return $str;
  }
  
  function ParseText($str)
  {
    $chars = "[a-zA-Z0-9]";
    
    $str = preg_replace("/(?<!$chars)_(.*?)_(?!$chars)/", '<em>$1</em>', $str);
    $str = preg_replace("/(?<!$chars)\*(.*?)\*(?!$chars)/", '<strong>$1</strong>', $str);
    $str = preg_replace("/\^(.*?)\^/", '<sup>$1</sup>', $str);
    $str = preg_replace("/,,(.*?),,/", '<sub>$1</sub>', $str);
    $str = preg_replace("/~~(.*?)~~/", '<s>$1</s>', $str);
    return $str;
  }
  
  function ParseDividors($str)
  {
    return preg_replace("/^[ \t]*----[ \t\-]*$/m", "<hr>", $str);    
  }
  
  
  function ParseListLines_($lines)
  {
    $list_elements = array(0=>array());
    $level = -1;
    $indent = -1;
    
    
    
    foreach($lines as $line)
    {
      $ltrimmed = ltrim($line);
      $this_indent = strlen($line) - strlen($ltrimmed);
      
      if ($this_indent > $indent)
        $level++;
      elseif($this_indent < $indent)
        $level--;
      
      array_push($list_elements[$level], $ltrimmed);
    }
    
    print_r($list_elements);
    
    return "";
    
  }
  
  
  
  function ParseListLines($lines)
  {    
    $stack = array();
    $indent = -1;
    $list = "";
    $held_points = 0;
    
    $indents = array();
    
    
    
    foreach($lines as $line)
    {
      $ltrimmed = ltrim($line);
      $this_indent = strlen($line) - strlen($ltrimmed);

      if ($this_indent > $indent)
      {
        $stack[] = array($indent, $ltrimmed[0]);
        $list .= ($ltrimmed[0] == '*')? "<ul>" : "<ol>";
        array_push($indents, $this_indent);
      }
      
      if ($this_indent < $indent)
      {
        while (1)
        {
          if (empty($indents))
            break;
          if ($indents[count($indents)-1] == $this_indent)
            break;
          $i = array_pop($indents);
          $s = array_pop($stack);
          $list .= '</li>';       
          $list .= ($s[1] == '*')? "</ul>" : "</ol>";
        }
      }
      
      $indent = $this_indent;        
      $point = isset($ltrimmed[1])? substr($ltrimmed, 1) : "";
      
      while ($held_points--)
        $list .= '</li>';
      
      $list .= "<li>$point";
      $held_points++;
    }
    
    $stack_ = array_reverse($stack);
    foreach($stack_ as $s)
      $list .= '</li>' . (($s[1] == '*')? "</ul>" : "</ol>");
    
    return $list;
    
  }
  
  function ParseListsCb($matches)
  {
    $lines = explode("\n", $matches[0]);
    foreach($lines as $i=>$l)
    {
      if (trim($l) === "")
        unset($lines[$i]);
    }
    $lite_list = $this->ParseListLines($lines);
    return $lite_list;
    
  }
  
  
  function ParseLists($str)
  {
    $str = preg_replace_callback("/(^[ ]+[\*#].*?(\n|$))+/m",
      array($this, 'ParseListsCb'), $str);
    return $str;
  }
  
  function ParseLinksCb($matches)
  {
    $link = "";
    $text = null;
    $x = preg_split("/\s+/", $matches[1]);
    $uri = $x[0];
    $prelinker_return = false;
    if ($this->linkifier_cb !== null)
      $prelinker_return = call_user_func($this->linkifier_cb, $uri);
    if ($prelinker_return !== false)
    {
      if (count($x) === 1 && isset($prelinker_return['name']))
        $x[1] = $prelinker_return['name'];
      $uri = $prelinker_return['uri'];
    }
    
    if (preg_match("/^www\d*\./", $uri))
      $uri = "http://$uri";
    
    $a = "<a href='$uri'%target>%text%ext</a>";
    $texts = array();
    for ($i=1; $i<count($x); $i++)
    {
      $t = $x[$i];      
      if (preg_match("/^.*\.(gif|jpe?g|png|bmp)\s*$/i", $t))
        $texts []= "<img src='$t' class='linked_img'>";
      else
        $texts []= $t;
      
    }
    $text = implode(" ", $texts);
    
    if (trim($text) === "")
      $text = $uri;
    
    $target = "";    
    $ext = "";
    
    if ($prelinker_return === false && preg_match("%^.*://%", $uri))
    {
      $target = " target=_blank";
      if (strpos($uri, '!') === 0) {
        $uri = substr($uri, 1);
      } else {
        $ext = preg_replace("%.*://(www\d*\.)?%", "", $uri);
        $ext = preg_replace("%/.*$%", "", $ext);
        $ext = " [$ext]";
      }
    }
    $a = "<a href='$uri'$target>$text</a>$ext";
    return $this->Hide($a);
  }
  
  function ParseLinksCb2($matches)
  {
    $link = array(0, $matches[0] . " " . $matches[0]);
    return $this->ParseLinksCb($link);    
  }
  
  
  function PrelinkerCb($matches)
  {
    $matches[1] = str_replace(' ', '-', $matches[1]);
    return "[{$matches[1]} " . str_replace('-', ' ', $matches[1]) . ']';
  }
  
  function Prelinker($str)
  {
    $str = preg_replace_callback("/ \[\[ (.*?) \]\]/x",
      array($this, 'PrelinkerCb'), $str);
    return $str;
  }
  
  function ParseLinksImages($matches)
  {
    return $this->Hide("<img src='{$matches[0]}' title='' alt=''>");
  }
  function ParseLinks($str)
  {
    $str = $this->Prelinker($str);
    $str = preg_replace_callback("/\[(.*?)\]/m", 
      array($this, 'ParseLinksCb'), $str);
      
    $str = preg_replace_callback("/(?<=\s)[^\s!]\S*?\.(gif|jpe?g|png|bmp)\b/i",
      array($this, 'ParseLinksImages'), $str);
      
    $str = preg_replace_callback("@(http://|www\d*\.)\S*@",
      array($this, 'ParseLinksCb2'), $str);    

    return $str;
  }

  function Format($str)
  {
    
    usort($this->handlers, create_function('$a,$b', '
    return $a["priority"] - $b["priority"];
    '));
        
    foreach($this->handlers as $handler)
    {
      $func = $handler['func'];
      $str = call_user_func_array($func, array($str, &$this));
    }
    return $str;
  }
}


