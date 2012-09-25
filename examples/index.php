<?php
require dirname(__FILE__) . '/helper.inc';

$files = array(
  'Standard example' => 'example.php',
  'AJAX interface' => 'ajax.php',
  'Full page output' => 'fullpage.php',
  'Inline code' => 'inline.php',
  'Theme switcher' => 'themeswitcher.php',
  'Setting options' => 'options.php',
);

if(isset($_GET['file']) && in_array($_GET['file'], $files)) {
    $source = luminous::highlight('php', 
    file_get_contents(dirname(__FILE__) . '/' . $_GET['file']));
    luminous::set('theme', 'github');
    $head = luminous::head_html();
    echo <<<EOF
<!DOCTYPE html>
<html>
  <head>
    <title></title>
    <style> body { font-size: smaller; margin: 0;} </style>
    $head
  </head>
  <body>
    $source
  </body>
</html>
EOF;
    exit(0);
}?><!DOCTYPE html>
<html>
    <head>
        <title>Luminous examples</title>
        <script src="http://code.jquery.com/jquery-1.8.2.min.js"></script>
        
        <script>
            $(document).ready(function() {
                var $menuLinks = $('.menu a');
                $menuLinks.click(function() {
                    var indexSelector = ':eq(' + $(this).data('index') + ')';
                    $('.menu > li').add('.examples > li').addClass('inactive');
                    $('.menu > li' + indexSelector)
                        .add('.examples > li' + indexSelector)
                        .removeClass('inactive');

                    $('.examples > li.inactive').slideUp();
                    $('.examples > li:not(.inactive)').slideDown();
                    return false;
                });
                $('.tabs a').click(function() {
                    var shouldShowSource = $(this).hasClass('show-source'),
                        $container = $(this).parents('li:eq(0)'),
                        hideSelect = shouldShowSource? '.source' : '.example',
                        showSelect = shouldShowSource? '.example' : '.source';
                    $container.find(showSelect).slideUp();
                    $container.find(hideSelect).slideDown();
                    $container.find('a').addClass('inactive');
                    $(this).removeClass('inactive');
                    return false;
                });
                
                
                // figure out the iframe height
                $(window).resize(function() {
                    var windowHeight = $(window).height(),
                        $el = $('.examples iframe:visible'),
                        offset = $el.offset().top;
                    $('.examples iframe').css('height', Math.max(250, windowHeight - offset - 20) + 'px');
                    
                });
                
                $menuLinks.eq(0).trigger('click');
                $('.tabs a.show-output').trigger('click');
                $(window).trigger('resize');
            });
        </script>
        <style>
          body {
              font-family: sans-serif;
          }
          iframe {
              border: 1px solid #bbb;
              width: 100%;
          }
          ul {
              margin: 0;
              padding: 0;
              list-style: none;
          }
          ul.menu {
          }
          ul.menu li {
            float: left;
            padding-right: 1em;
            
          }
          ul.menu:after {
            display: block;
            content: " ";
            clear: both;
          }
          li { 
              margin: 0;
              padding: 0;
          }
          li .tabs a, .menu a {
              font-weight: bold;
          }
          li .tabs a.inactive, .menu .inactive a {
              font-weight: normal;
          }
          .tabs {
              text-align: right;
          }
          
          .examples li { 
              height: 100%;
          }
         
        </style>
    </head>
    
    <body>
    
        <h1>Luminous Examples</h1>
        <p>Usage and calling examples for Luminous</p>
        <ul class='menu'>
            <?php $i=0; foreach ($files as $description=>$filename): ?>
                <li class='inactive'>
                    <a href='#' data-index="<?= $i ?>"><?= $description ?></a>
                </li>
            <?php $i++; endforeach; ?>
        </ul>
        
        <ul class='examples'>
            <?php $i=0; foreach ($files as $description=>$filename): ?>
              <li class='inactive'>
                  <div class='tabs'>
                      <a href='#' class='show-output'>Example</a>
                      <a href='#' class='show-source inactive'>Source</a>
                  </div>
                  <ul class='inner'>
                    <li class='example'><iframe src='<?=$filename?>'></iframe></li>
                    <li class='source inactive'><iframe src='?file=<?=$filename?>'></iframe></li>
                  </ul>
                </li>
            <?php $i++; endforeach; ?>
        </ul>
    </body>
</html>