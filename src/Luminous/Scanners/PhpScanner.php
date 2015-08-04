<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\Scanner;

/*
 * This is a controller class which handles alternating between PHP and some
 * other language (currently HTML only, TODO allow plain text as well)
 * PHP and the other language are handled by subscanners
 */
class PhpScanner extends Scanner
{
    /// the 'non-php' scanner
    protected $subscanner;
    /// the real php scanner
    protected $phpScanner;

    /// If it's a snippet, we assume we're starting in PHP mode.
    public $snippet = false;

    public function __construct($src = null)
    {
        $this->subscanner = new HtmlScanner($src);
        $this->subscanner->embeddedServer = true;
        $this->subscanner->init();

        $this->phpScanner = new PhpSubScanner($src);
        $this->phpScanner->init();
        parent::__construct($src);
    }

    public function string($s = null)
    {
        if ($s !== null) {
            $this->subscanner->string($s);
            $this->phpScanner->string($s);
        }
        return parent::string($s);
    }

    protected function scanPhp($delimiter)
    {
        if ($delimiter !== null) {
            $this->record($delimiter, 'DELIMITER');
        }
        $this->phpScanner->pos($this->pos());
        $this->phpScanner->main();
        $this->record($this->phpScanner->tagged(), ($delimiter === '<?=') ? 'INTERPOLATION' : null, true);

        $this->pos($this->phpScanner->pos());
        assert($this->eos() || $this->check('/\\?>/'));
        if ($this->scan('/\\?>/')) {
            $this->record($this->match(), 'DELIMITER');
        }
    }

    protected function scanChild()
    {
        $this->subscanner->pos($this->pos());
        $this->subscanner->main();
        $this->pos($this->subscanner->pos());
        assert($this->eos() || $this->check('/<\\?/'));
        $this->record($this->subscanner->tagged(), null, true);
    }

    public function main()
    {
        while (!$this->eos()) {
            $p = $this->pos();
            if ($this->snippet) {
                $this->scanPhp(null);
            } elseif ($this->scan('/<\\?(?:php|=)?/')) {
                $this->scanPhp($this->match());
            } else {
                $this->scanChild();
            }
            assert($this->pos() > $p);
        }
    }

    public static function guessLanguage($src, $info)
    {
        // cache p because this function is hit by the snippet scanner as well
        static $p = 0.0;
        static $src_ = null;
        if ($src_ === $src) {
            return $p;
        }
        // look for delimiter tags
        if (strpos($src, '<?php') !== false) {
            $p += 0.5;
        } elseif (preg_match('/<\\?(?!xml)/', $src)) {
            $p += 0.20;
        }
        // check for $this, self:: parent::
        if (preg_match('/\\$this\\b|((?i: self|parent)::)/x', $src)) {
            $p += 0.15;
        }
        // check for PHP's OO notation: $somevar->something
        if (preg_match('/\\$[a-z_]\w*+->[a-z_]/i', $src)) {
            $p += 0.05;
        }
        // check for some common functions:
        if (preg_match('/\\b(echo|require(_once)?|include(_once)?|preg_\w)/i', $src)) {
            $p += 0.05;
        }
        $src_ = $src;
        return $p;
    }
}
