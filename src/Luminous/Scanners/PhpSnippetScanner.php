<?php

namespace Luminous\Scanners;

class PhpSnippetScanner extends PhpScanner
{
    public $snippet = true;

    public static function guessLanguage($src, $info)
    {
        $p = parent::guessLanguage($src, $info);
        if ($p > 0.0) {
            // look for the close/open tags, if there is no open tag, or if
            // there is a close tag before an open tag, then we guess we're
            // in a snippet
            // if we are in a snippet we need to come out ahead of php, and
            // if we're not then we need to be behind it.
            $openTag = strpos($src, '<?');
            $closeTag = strpos($src, '?>');
            if ($openTag === false || ($closeTag !== false && $closeTag < $openTag)) {
                $p += 0.01;
            } else {
                $p -= 0.01;
            }
        }
        return $p;
    }
}
