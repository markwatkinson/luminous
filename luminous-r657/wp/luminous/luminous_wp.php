<?php


/*
  Plugin Name: Luminous
  Plugin URI: http://code.google.com/p/luminous/
  Description: A syntax highlighting plugin using the Luminous engine.
  Author: Mark Watkinson
  Version: 1.0
  Author URI: http://www.asgaard.co.uk
*/



add_action('init', 'wp_luminous_init');
add_action('wp_head', 'wp_luminous_head');

// this does the actual parsing, and then sticks in another shortcode with just 
// an md5 referencing the output... this occurs before any Wordpress formatting
add_filter('the_content', 'wp_luminous', 9);

// this is then executed later, after Wordpress adds in all its formatting, 
// and replaces the shortcode/md5 with the output.
add_shortcode('sourcecode', 'wp_luminous_shortcode2');



add_filter('the_excerpt', 'wp_luminous_excerpt');

add_action('admin_menu', 'luminous_create_menu');

$luminous_path = "";
$luminous_options = array("path"=>"luminous", 
  "floating_buttons"=>false,
  "widget_height"=>500,
  "theme"=>"luminous_light.css");

add_option("luminous", $luminous_options);


function wp_luminous_init()
{
  global $luminous_path, $luminous_options;
  
  if (count($_POST) && isset($_POST['luminous_hidden']))
    luminous_save_settings();
  
  $luminous_options = get_option('luminous');
  
  $luminous_path = $luminous_options["path"];
  @include($_SERVER['DOCUMENT_ROOT']  . "/$luminous_path/src/luminous.php");
  
  $LUMINOUS_WIDGET_HEIGHT = $luminous_options['widget_height'];
  $LUMINOUS_WRAP_WIDTH = 80;
  
  wp_enqueue_script("jquery");
  
  
}

function wp_luminous_head()
{
  global $luminous_path, $luminous_options;
  $css = $luminous_path . "style/luminous.css";
  $theme = $luminous_path . "style/" . $luminous_options['theme'];
  $js = $luminous_path . "client/luminous.js";
  echo "<link rel='stylesheet' type='text/css' href='$css'></link>\n";
  echo "<link rel='stylesheet' type='text/css' href='$theme'></link>\n";
  echo "<script type='text/javascript' src='$js'></script>\n";
  echo "<script type='text/javascript'>\nluminous_fixed_bar = ";
  if ($luminous_options["floating_buttons"])
    echo "false";
  else echo "true";
  echo ";\n</script>";
}


function wp_luminous_foot()
{
}

function wp_luminous_cb($langauge, $source, $options)
{
  global $LUMINOUS_WIDGET_HEIGHT;
  global $LUMINOUS_WRAP_WIDTH;
  global $LUMINOUS_ESCAPE_INPUT;
  $source = trim(($source));
  
  $h = $LUMINOUS_WIDGET_HEIGHT;
  $wrap = $LUMINOUS_WRAP_WIDTH;
  $esc = $LUMINOUS_ESCAPE_INPUT;
  
  
  if (isset($options["height"]))
    $LUMINOUS_WIDGET_HEIGHT = $options["height"];
  
  if (isset($options["wrap"]))  
    $LUMINOUS_WRAP_WIDTH = (int)$options["wrap"];  
  if (isset($options['escaped']))
    $LUMINOUS_ESCAPE_INPUT = false;
  
  $src = luminous($langauge, $source, false);
  
  $LUMINOUS_WIDGET_HEIGHT = $h;
  $LUMINOUS_WRAP_WIDTH = $wrap;  
  $LUMINOUS_ESCAPE_INPUT = $esc;
  $div = "<div style='border:1px solid black; padding:0px; margin:0px; display:inline-block; min-width:85%; max-width:100%'>$src</div>";
  return $div; 
}

function wp_luminous_shortcode_dummy($attrs, $content=null)
{
  return "";
}
function wp_luminous_excerpt($content)
{
  global $shortcode_tags;
  // Backup current registered shortcodes and clear them all out
  $orig_shortcode_tags = $shortcode_tags;
  $shortcode_tags = array();
  add_shortcode('sourcecode', 'wp_luminous_shortcode_dummy');
  $content = do_shortcode( $content );
  
  // Put the original shortcodes back
  $shortcode_tags = $orig_shortcode_tags;
  return $content;
}

function wp_luminous($content)
{
  global $shortcode_tags;
  
  if (!function_exists("luminous"))
    return $content;
  
  
  
  // Backup current registered shortcodes and clear them all out
  $orig_shortcode_tags = $shortcode_tags;
  $shortcode_tags = array();
  
  add_shortcode('sourcecode', 'wp_luminous_shortcode');
  
  $content = do_shortcode( $content );
  
  // Put the original shortcodes back
  $shortcode_tags = $orig_shortcode_tags;
  return $content;
}

$luminous_table = array();

function wp_luminous_shortcode($atts, $content = null ) 
{
  global $luminous_table;
  extract( shortcode_atts( array(
    'language' => 'plain',
    'wrap' => '',
    'height' => '',
    'escaped' => 'false'
  ), $atts ) );
  
  $options = array();
  if ($wrap != '')
    $options['wrap'] = $wrap;
  
  if ($height != '')
    $options['height'] = $height;
  if ($escaped == "true")
    $options['escaped'] = true;
  
  
  $src = wp_luminous_cb($language, $content, $options);
  $md5 = md5($src);
  $luminous_table[$md5] = $src;
  return "[sourcecode md5=$md5]";
}

function wp_luminous_shortcode2($atts, $content=null)
{
  global $luminous_table;
  extract( shortcode_atts( array(
    'md5' => ''
  ), $atts ) );
  
  if ($md5 != "" && isset($luminous_table[$md5]))
    return $luminous_table[$md5];
  else return "[Lumious-wordpress hit an error. If you were the writer of this entry, please report this to the Luminous project as a Luminous bug.]";
}



// create custom plugin settings menu
function luminous_create_menu() 
{
  add_submenu_page('plugins.php', 'Luminous Settings', 'Luminous settings', 
    'administrator', 'luminous-handle', 'luminous_settings_page'); 
}





function luminous_settings_page() {
  global $luminous_options;
      ?>
      <div class="wrap">
      <h2>Luminous</h2>
      <?php
      if(!function_exists("luminous"))
      {
        echo "Warning! Your path is set incorrectly";
      }?>
      
      <form method="post" action="<?php
        $uri = htmlentities($_SERVER['PHP_SELF']);         
        $uri .= "?";
        foreach($_GET as $k=>$v)
          $uri .= "$k=$v&";
        $uri = trim($uri, "&");
        echo $uri;
        ?>">
      <table class="form-table">
      <tr valign="top">
      <th scope="row">Path</th>
      <td><input type="text" name="path" value="<?php echo $GLOBALS['luminous_path']; ?>" /></td>
      </tr>
      
      <?php
      if(function_exists("luminous"))
      {
        echo <<<EOF
        <tr valign="top">
        <tr valign="top">
        <th scope="row">Theme</th>
        <td><select name="luminous_theme">
EOF;
        foreach (glob($_SERVER['DOCUMENT_ROOT']  . $GLOBALS['luminous_path'] . "/style/*.css") as $filename) 
        {
          $fn = preg_replace("%.*?([^/]*\.css$)%s", '$1', $filename);
          if ($fn === "luminous.css" || $fn == "luminous_print.css")
            continue;
          echo "<option name='$fn' ";
          if ($fn == $luminous_options['theme'])
            echo "selected";
          echo ">$fn</option>";
        }
        echo "</select>";
      }?>
        

      
      <tr valign="top">
      <th scope="row">Floating buttons</th>
      <td><input type="checkbox" name="floating_buttons" <?php 
      if($luminous_options['floating_buttons'] == true) echo "checked";?> /></td>
      </tr>      
      <tr valign="top">
      <th scope="row">Maximum widget height (pixels)</th>
      <td><input type="text" name="widget_height" value="<?php 
        echo $luminous_options['widget_height'];?>" /></td>
        </tr>      
      </table>
      <p class="submit">
      <input type='hidden' name='luminous_hidden'>
      <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
      </p>
      
      </form>
      </div>
      <?php } 


function luminous_save_settings()
{
  if (!current_user_can('manage_options'))
    return;
  
  $settings = get_option($luminous);
  $settings['path'] = $_POST['path'];
  $settings['floating_buttons'] = isset($_POST['floating_buttons']);
  $settings['widget_height'] = $_POST['widget_height'];
  $settings['theme'] = $_POST['luminous_theme'];
//   print_r($settings);
  update_option('luminous', $settings);
}