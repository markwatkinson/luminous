<?php 
// Theme switcher example
require_once('helper.inc');
luminous::set('include-javascript', true);
// This isn't an injection or XSS vulnerability. You don't have to worry
// about sanitising this, Luminous won't use it if it's not a valid theme file.
if (!empty($_GET))
  luminous::set('theme', $_GET['theme_switcher']);
?>
<!DOCTYPE HTML>
<html>
  <head>
    <title>Theme Switcher Example</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php echo luminous::head_html(); ?>
    
    <script type='text/javascript'>
    
    $(document).ready(function() {
      // theme switcher change handler
      $('#theme_switcher').change(function(){
        // get the /path/to/style/ via the current theme's href attribute
        var current_url = $('#luminous-theme').attr('href');
        var base_url = current_url.replace(/[^/]*$/, '');
        // now replace the href with the new theme's path
        $('#luminous-theme').attr('href',
          base_url + $(this).val()
        );
        return false;
      });
    });
   
    </script>
  </head>
  
  <body>
  <p>  
  <form action='themeswitcher.php'>
  Change Theme: <select name='theme_switcher' id='theme_switcher'>
  <?php 
  // Build the theme switcher by getting a list of legal themes from Luminous.
  // The luminous_get_html_head() function by default outputs the theme
  // in LUMINOUS_THEME. This can be overridden by the first argument, but as we
  // didn't, that's what we need to check against to determine the default
  // theme for the selector. However, it might not have the .css suffix.
  $default_theme = luminous::setting('theme');
  if (!preg_match('/\.css$/', $default_theme))
    $default_theme .= '.css';
  foreach(luminous::themes() as $theme): ?>
    <option id='<?=$theme?>'
      <?=($theme == $default_theme)? ' selected' : ''?>>
      <?= $theme ?>
    </option>
  <?php endforeach ?>
  </select>
  <noscript><input type='submit' value='Switch'></noscript>
  </form>
  
  <p>  
  <?php echo luminous::highlight_file('php', __FILE__, $use_cache); ?>
  </body>
</html>
