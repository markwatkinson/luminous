<?php
  require_once("sql.class.php");
  require_once("markupwrapper.php");
  
  date_default_timezone_set('Europe/London');

  $tabs = $SQL->GetTabs();
  $TAB_VIEW = true;
?><!DOCTYPE html>

<html>

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Asgaard.co.uk</title>
<link rel="alternate" title="RSS" href="rss.php" type="application/rss+xml">

<link rel='stylesheet' type="text/css" href="/style/tabs.css">
<link rel="stylesheet" type="text/css" href="/style/markup.css">

<link rel="stylesheet" type="text/css" href="/style/style.css">
<link rel="stylesheet" type="text/css" href="/style/index.css">


<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>

<script type="text/javascript" src="/js/tabify_jquery.js"></script>

<style type='text/css'>
.tab {height:100%;}
.tab_title{border-top-left-radius:0.5em; border-bottom-left-radius:0.5em;}
.tab_content
{
  background-color:white;
  border: 1px solid #B5CFFF;
  padding: 1em;    
  border-radius:1em;
}
.tab_bar
{
  text-align:left;
  margin-top:1em;
}
.tab_bar > div
{
  padding-right:0.25em;
}
</style>
<script type='text/javascript'>
$(document).ready(function(){
  
  $('.tab_clicker').each(function(){ $(this).replaceWith($(this).html());});
  
  var tabs = $('.tab_content');
  
  tabs.tabify($('.tab_bar'), true);
  
  // if the view width is too low we'll get a float drop occurring, in which
  // case we revert to a horizontal menu
  var t = setInterval(function(){
    var x = $('.tab_bar')[0].offsetTop;
    var y = $('.tab_content')[0].offsetTop;
    if (x < y)
    {
      $('.tab_title').css('display', 'inline').css('border-radius', 
          '0px').css('float','none').css('border-bottom-width', 
          '0px').css('margin-right', '0.25em');
      $('.tab_content').css('float', 'none').css('border-radius', '0px').css('margin-left', '0px').css('width', 'auto');
      $('.tab_bar').css('float', 'none').css('border-radius', '0px').css('width', '100%');
      clearInterval(t);
    }
  }, 500);
            
  
  
});
</script>

<!--[if IE 6]>
<style type="text/css">
  .background { position:absolute; z-index:-100; }
  .page_container {position:absolute; z-index:1}
</style>
<![endif]-->

</head>
<body>

<div class=header></div>

<noscript>
  <p class='content' style='margin-top:0em;padding-top:0px'>
  If you really don't want to enable JavaScript, you may prefer the basic page <a href=index_basic.php>here</a>, which you might find more readable.
  </p>
</noscript>
  
<div class="page_container" id='p_container'>
  
  <div class='tab_container' id='tab_container' style='width:90%; position:relative;'>
        
    <div class='tab_bar' style='float:left;position:relative;z-index:1'>

    <?
    foreach($tabs as $t)
    {
      $title = "<a href='#{$t->element_id}' class='tab_clicker'>{$t->tab_title}</a>";
      $img = trim($t->image);
      if ($img !== false && strlen($img) && @file_exists($_SERVER['DOCUMENT_ROOT'].$img))
        $title = "<img src='$img' alt='' title=''> $title";
      echo "<div>$title</div>";
    }
    ?>
    </div>
    <div class='tab_content' style='float:left;position:relative;width:75%; min-height:200px; margin-left:-1px;'>
    <? 
        foreach($tabs as $t)
        {
          echo "<div id='{$t->element_id}' class='content'>";
          echo markup($t->content);
          echo "</div>";
        }
      ?>
   </div>
 </div>

</div>



</body>
</html>

