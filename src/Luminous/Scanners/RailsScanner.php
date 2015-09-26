<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\Scanner;

/*
 * Rails. Basically a wrapper around Ruby and HTML.
 */

class RailsScanner extends Scanner
{
    // HTML scanner has to be persistent. Ruby doesn't.
    private $htmlScanner;

    public function init()
    {
        $this->htmlScanner = new HtmlScanner();
        $this->htmlScanner->string($this->string());
        $this->htmlScanner->embeddedServer = true;
        $this->htmlScanner->serverTags = '/<%/';
        $this->htmlScanner->init();
    }

    public function scanHtml()
    {
        $this->htmlScanner->pos($this->pos());
        $this->htmlScanner->main();
        $this->record($this->htmlScanner->tagged(), null, true);
        $this->pos($this->htmlScanner->pos());
    }

    public function scanRuby($short = false)
    {
        $rubyScanner = new RubyScanner($this->string());
        $rubyScanner->rails = true;
        $rubyScanner->init();
        $rubyScanner->pos($this->pos());
        $rubyScanner->main();
        $this->record($rubyScanner->tagged(), $short ? 'INTERPOLATION' : null, true);
        $this->pos($rubyScanner->pos());
    }

    public function main()
    {
        while (!$this->eos()) {
            $p = $this->pos();
            if ($this->scan('/<%#?([\-=]?)/')) {
                $this->record($this->match(), 'DELIMITER');
                $this->scanRuby($this->matchGroup(1) === '=');
                if ($this->scan('/-?%>/')) {
                    $this->record($this->match(), 'DELIMITER');
                }
            } else {
                $this->scanHtml();
            }
            assert($p < $this->pos());
        }
    }

    public static function guessLanguage($src, $info)
    {
        $p = RubyScanner::guessLanguage($src, $info);
        if ($p > 0) {
            if (preg_match('/<%.*%>/', $src)) {
                $p += 0.02;
            } else {
                $p = 0.0;
            }
            $p = min($p, 1);
        }
        return $p;
    }
}
