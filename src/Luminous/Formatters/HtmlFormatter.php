<?php
/** @cond ALL */

namespace Luminous\Formatters;

use Luminous\Utils\HtmlTemplates;

class HtmlFormatter extends Formatter
{
    // overridden by inline formatter
    protected $inline = false;
    public $height = 0;

    /**
     * strict HTML standards: the target attribute won't be used in links
     * @since  0.5.7
     */
    public $strictStandards = false;

    private function heightCss()
    {
        $height = trim('' . $this->height);
        $css = '';
        if (!empty($height) && (int)$height > 0) {
            // look for units, use px is there are none
            if (!preg_match('/\D$/', $height)) {
                $height .= 'px';
            }
            $css = "max-height: {$height};";
        } else {
            $css = '';
        }
        return $css;
    }

    private static function templateCb($matches)
    {
    }

    // strips out unnecessary whitespace from a template
    private static function template($t, $vars = array())
    {
        $t = preg_replace_callback('/\s+|<[^>]++>/', array('self', 'templateCb'), $t);
        array_unshift($vars, $t);
        $code = call_user_func_array('sprintf', $vars);
        return $code;
    }

    private function linesNumberless($src)
    {
        $lines = array();
        $linesOriginal = explode("\n", $src);
        foreach ($linesOriginal as $line) {
            $l = $line;
            $num = $this->wrapLine($l, $this->wrapLength);
            // strip the newline if we're going to join it. Seems the easiest way to
            // fix http://code.google.com/p/luminous/issues/detail?id=10
            $l = substr($l, 0, -1);
            $lines[] = $l;
        }
        $lines = implode("\n", $lines);
        return $lines;
    }

    private function formatNumberless($src)
    {
        return HtmlTemplates::format(
            HtmlTemplates::NUMBERLESS_TEMPLATE,
            array(
                'height_css' => $this->heightCss(),
                'code' => $this->linesNumberless($src)
            )
        );
    }

    public function format($src)
    {
        $lineNumbers = false;

        if ($this->link) {
            $src = $this->linkify($src);
        }

        $codeBlock = null;
        if ($this->lineNumbers) {
            $codeBlock = $this->formatNumbered($src);
        } else {
            $codeBlock = $this->formatNumberless($src);
        }

        // convert </ABC> to </span>
        $codeBlock = preg_replace('/(?<=<\/)[A-Z_0-9]+(?=>)/S', 'span', $codeBlock);
        // convert <ABC> to <span class=ABC>
        $cb = function ($matches) {
            $m1 = strtolower($matches[1]);
            return "<span class=" . $m1 . ">";
        };
        $codeBlock = preg_replace_callback('/<([A-Z_0-9]+)>/', $cb, $codeBlock);

        $formatData = array(
            'language' => ($this->language === null) ? '' : htmlentities($this->language),
            'subelement' => $codeBlock,
            'height_css' => $this->heightCss()
        );
        return HtmlTemplates::format(
            $this->inline ? HtmlTemplates::INLINE_TEMPLATE : HtmlTemplates::CONTAINER_TEMPLATE,
            $formatData
        );
    }

    /**
     * Detects and links URLs - callback
     */
    protected function linkifyCb($matches)
    {
        $uri = (isset($matches[1]) && strlen(trim($matches[1]))) ? $matches[0] : "http://" . $matches[0];

        // we dont want to link if it would cause malformed HTML
        $openTags = array();
        $closeTags = array();
        preg_match_all("/<(?!\/)([^\s>]*).*?>/", $matches[0], $openTags, PREG_SET_ORDER);
        preg_match_all("/<\/([^\s>]*).*?>/", $matches[0], $closeTags, PREG_SET_ORDER);

        if (count($openTags) != count($closeTags)) {
            return $matches[0];
        }
        if (isset($openTags[0]) && trim($openTags[0][1]) !== trim($closeTags[0][1])) {
            return $matches[0];
        }

        $uri = strip_tags($uri);

        $target = ($this->strictStandards) ? '' : ' target="_blank"';
        return "<a href='{$uri}' class='link'{$target}>{$matches[0]}</a>";
    }

    /**
     * Detects and links URLs
     */
    protected function linkify($src)
    {
        if (stripos($src, "http") === false && stripos($src, "www") === false) {
            return $src;
        }

        $chars = "0-9a-zA-Z\$\-_\.+!\*,%";
        $src_ = $src;
        // everyone stand back, I know regular expressions
        $src = preg_replace_callback(
            "@(?<![\w])
            (?:(https?://(?:www[0-9]*\.)?) | (?:www\d*\.)   )

            # domain and tld
            (?:[$chars]+)+\.[$chars]{2,}
            # we don't include tags at the EOL because these are likely to be
            # line-enclosing tags.
            (?:[/$chars/?=\#;]+|&amp;|<[^>]+>(?!$))*
            @xm",
            array($this, 'linkifyCb'),
            $src
        );
        // this can hit a backtracking limit, in which case it nulls our string
        // FIXME: see if we can make the above regex more resiliant wrt
        // backtracking
        if (preg_last_error() !== PREG_NO_ERROR) {
            $src = $src_;
        }
        return $src;
    }

    private function formatNumbered($src)
    {
        $lines = '<span>' . str_replace("\n", "\n</span><span>", $src, $numReplacements) . "\n</span>";
        $numLines = $numReplacements + 1;

        $lineNumbers =
            '<span>'
            . implode('</span><span>', range($this->startLine, $this->startLine + $numLines - 1, 1))
            . '</span>';

        $formatData = array(
            // max number of digits in the line - this is used by the CSS
            'line_number_digits' => strlen((string)($this->startLine) + $numLines),
            'start_line' => $this->startLine,
            'height_css' => $this->heightCss(),
            'highlight_lines' => implode(',', $this->highlightLines),
            'code' => $lines,
            'line_numbers' => $lineNumbers
        );

        return HtmlTemplates::format(
            HtmlTemplates::NUMBERED_TEMPLATE,
            $formatData
        );
    }
}

/** @endcond */
