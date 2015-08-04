<?php

/// @cond ALL

namespace Luminous\Formatters;

class FullPageHtmlFormatter extends HtmlFormatter
{
    protected $themeCss = null;
    protected $css = null;

    public function setTheme($css)
    {
        $this->themeCss = $css;
    }

    protected function getLayout()
    {
        // this path info shouldn't really be here
        $path = luminous::root() . '/style/luminous.css';
        $this->css = file_get_contents($path);
    }

    public function format($src)
    {
        $this->height = 0;
        $this->getLayout();
        $fmted = parent::format($src);
        return <<<EOF
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title></title>
    <style type='text/css'>
    body {
      margin: 0;
    }
    /* luminous.css */
    {$this->css}
    /* End luminous.css */
    /* Theme CSS */
    {$this->theme_css}
    /* End theme CSS */
    </style>
  </head>
  <body>
    <!-- Begin luminous code //-->
    $fmted
    <!-- End Luminous code //-->
  </body>
</html>

EOF;
    }
}

/// @endcond
