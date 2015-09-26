<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\Scanner;

/*
 * Django scanner
 *
 * TODO: Django does not respect {% comment  %} ... {% endcomment %}
 */
class DjangoScanner extends Scanner
{
    // warning: some copying and pasting with the rails scanner here

    // HTML scanner has to be persistent.
    private $htmlScanner;

    public function init()
    {
        $this->htmlScanner = new HtmlScanner();
        $this->htmlScanner->string($this->string());
        $this->htmlScanner->embeddedServer = true;
        $this->htmlScanner->serverTags = '/\{[{%#]/';
        $this->htmlScanner->init();
    }

    public function scanHtml()
    {
        $this->htmlScanner->pos($this->pos());
        $this->htmlScanner->main();
        $this->record($this->htmlScanner->tagged(), null, true);
        $this->pos($this->htmlScanner->pos());
    }

    public function scanPython($short = false)
    {
        $pythonScanner = new PythonScanner($this->string());
        $pythonScanner->django = true;
        $pythonScanner->init();
        $pythonScanner->pos($this->pos());
        $pythonScanner->main();
        $this->record($pythonScanner->tagged(), $short ? 'INTERPOLATION' : null, true);
        $this->pos($pythonScanner->pos());
    }

    public function main()
    {
        while (!$this->eos()) {
            $p = $this->pos();
            // django's tags are {{ }} and {% %}
            // there's also a {#  #} comment tag but we can probably handle that here
            // more easily
            // same for {% comment %} ... {% endcomment %}
            if ($this->scan('/\{([{%])/')) {
                $match = $this->match();
                $m1 = $this->matchGroup(1);
                // {% comment %} ... {% endcomment %}
                if ($this->scan('/\s*comment\s*%\}/')) {
                    $match .= $this->match();
                    $endPattern = '/\{%\s*endcomment\s*%\}/';
                    if ($this->scanUntil($endPattern) !== null) {
                        $match .= $this->match();
                        $match .= $this->scan($endPattern);
                    } else {
                        $match .= $this->rest();
                        $this->terminate();
                    }
                    $this->record($match, 'COMMENT');
                } else {
                    // {{ ... }} or {% ... %}
                    $this->record($match, 'DELIMITER');
                    $this->scanPython($m1 === '{');
                    if ($this->scan('/[}%]\}/')) {
                        $this->record($this->match(), 'DELIMITER');
                    }
                }
            } elseif ($this->scan('/\{\# (?: [^\#]++ | \#(?! \} ) )*+ (?: \#\} | $)/x')) {
                // {# ... #}
                $this->record($this->match(), 'COMMENT');
            } else {
                $this->scanHtml();
            }
            assert($p < $this->pos());
        }
    }

    public static function guessLanguage($src, $info)
    {
        if (($html = HtmlScanner::guessLanguage($src, $info)) >= 0.2) {
            if (strpos($src, '{{') !== false || strpos($src, '{%') !== false) {
                return $html + 0.01;
            }
        }
        return 0.0;
    }
}
