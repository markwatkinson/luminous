<?php


/*
 * HTML is the 'root' scanner, we just override a couple of config settings
 * here, to prevent it from looking for CSS or JS.
 */
class LuminousXMLScanner extends LuminousHTMLScanner {
  public $scripts = false;
  public $embedded_server = false;
}
