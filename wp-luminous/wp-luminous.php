<?php
/*
Plugin Name: WP Luminous
Plugin URI: https://github.com/daveismyname/luminous/wp-luminous
Description: Wordpress plugin for Accurate and powerful syntax highlighting library 
Author: David Carr
Version: 1.0
Author URI: http://www.daveismyname.com
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*  Copyright 2013  David Carr  (email : dave@daveismyname.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WP_luminous {

    const plugin_name = 'WP Luminous';
    const plugin_slug = 'wp_luminous';
    const class_slug = 'WP_luminous';
    const register_slug = 'wp_luminous_options';

    public $options;

    public function __construct(){
        $this->register_settings_and_fields();
        $this->options = get_option('wp_luminous');
    }

    public function add_menu_page(){
        add_options_page(self::plugin_name, self::plugin_name, 'administrator', __FILE__, array('wp_luminous', 'display_options_page'));
    }

    public function display_options_page(){
    ?>

        <div class='wrap'>

            <?php screen_icon();?>
            <h2><?php echo self::plugin_name;?></h2>

            <form action="options.php" method="post" enctype="multipart/form-data">
            <?php settings_fields(self::plugin_slug);?>
            <?php do_settings_sections(__FILE__);?>
            
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes');?>"/>
            </p>
            </form>


        </div>
    <?php
    }

    public function register_settings_and_fields(){
        register_setting(self::plugin_slug, self::plugin_slug); //third param option call back function
        add_settings_section('main_section', 'Options', array($this,'main_section_cb') ,__FILE__); //id, title, cb, page
        add_settings_field('theme','Theme', array($this,'theme_options'), __FILE__, 'main_section');
    }

    public function main_section_cb(){}

    public function theme_options(){
            require_once 'luminous/luminous.php';
 
            echo "<select name='wp_luminous[theme]'>";
            foreach(luminous::themes() as $csstheme){
                $selected = ($this->options[theme] === $csstheme) ? 'selected="selected"' : '';
                echo "<option value='$csstheme' $selected>".ucwords($csstheme)."</option>";
            }
            echo "</select>";               
    }

}

add_action('admin_menu', 'WP_Luminous_Options');
add_action('admin_init', 'WP_Luminous_Init');

function WP_Luminous_Options(){
    WP_luminous::add_menu_page();
}

function WP_Luminous_Init(){
    new WP_luminous();
}





require_once 'luminous/luminous.php';

function wp_luminous_style(){
    $options = get_option('wp_luminous');
    $theme = $options['theme'];

    wp_enqueue_style( 'wp-luminous-css', WP_PLUGIN_URL . "/wp-luminous/luminous/style/luminous.css" );
    if($theme !=''){ wp_enqueue_style( 'luminous_light.css', WP_PLUGIN_URL . "/wp-luminous/luminous/style/$theme" ); }
}

function wp_luminous_before_filter($content){
    $languages = array(
        'as','actionscript','ada','adb','ads','bnf','bash','sh','cs','csharp','c#','c','cpp','h','hpp','cxx','hxx','css',
        'diff','patch','prettydiff','prettypatch','diffpretty','patchpretty','django','djt','ecma','ecmascript','erlang','erl','hrl',
        'go','groovy','html','htm','haskell','hs','json','java','js','javascript','lolcode','lolc','lol','latex','tex',
         'm','matlab','php','php_snippet','perl','pl','pm','plain', 'text', 'txt','python', 'py','ruby', 'rb','rails', 
         'rhtml','ror','scss','sql','mysql','scala','scl','vim','vimscript','vb','bas','xml'
    );
    foreach ($languages as $language){
        $content = preg_replace_callback(
                        '~\<pre lang="'.$language.'">(.*?)\</pre>~is',
                        "wp_luminous_matches",
                        $content); 
    }

    return $content;
}

function wp_luminous_matches($matches){
    return luminous::highlight('php', $matches[1]);
}

add_action('wp_print_styles', 'wp_luminous_style');
add_filter('the_content', 'wp_luminous_before_filter', 99);


