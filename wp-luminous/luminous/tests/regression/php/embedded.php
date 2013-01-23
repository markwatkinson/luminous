<html>
  <head>
  <title><?= $title ?></title>
  <sometag><? echo $nothing; // comment ?></sometag>
  <script type='javascript'>
  document.getElementById('content').innerHTML += <?php echo $some_content; ?>
  /*  COMMENT  <?php
    echo 'this is a comment in the javascript but a string in the php'
    ?>   STILL A COMMENT  <? xyz() ?> */
  var str = "this is<?php echo ' a '; ?> string";
  var str = 'this is<?php echo ' a '; ?> string';
  // this is <?php echo 'a?>' ?> comment <?php echo ''; ?> ... still

  var xml = <tag1 x='y'>
    <tag2> "hello </tag2>
    <tag<?php ?> x=y/>
    <tag> <?php ?> </tag>
    </tag1>;

  // abcdef <?php     ?> 09876543 </script>
  <script src=blahblah></script>
  <style type='text/css'>
  /*   comment <?php echo $blah; ?>      still a comment <?php /* */ ?> */
  a:visited {
    font-weight: bold;
    background-image: url("<?echo $_SERVER['PHP_SELF']; ?>/image.png");
  }
  a[name=<?php echo 'something'?>] {
    font-weight: bold;
    background-image: url("<?echo $_SERVER['PHP_SELF']; ?>/image2.png");
  }  
  </style>

  <body>
  <div id="c<?php echo 'onten'; ?>t" blah> blah <?php '' ?> </div>
  <div id="c<?php echo 'o'; ?>nt<?php echo 'en'?> t" blah> blah <?php '' ?> </div>
  <div id='c<?php echo 'onten'; ?>t' blah> blah <?php '' ?> </div>
  <div id='c<?php echo 'o'; ?>nt<?php echo 'en'?> t' blah> blah <?php '' ?> </div>
  
  <!-- <?php    echo 'hello';  ?> //-->

  <! <?php    echo 'hello';  ?> -->

  <div><![CDATA[ cdata
  <?php echo ''; ?>
  <?php echo ''; ?>
  <?php echo ']]>'; ?>
  cdata
  ]]></div>
