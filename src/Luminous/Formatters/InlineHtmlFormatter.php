<?php

/// @cond ALL

namespace Luminous\Formaters;

class InlineHtmlFormatter extends HtmlFormatter
{
    public function format($src)
    {
        $this->lineNumbers = false;
        $this->height = 0;
        $this->inline = true;
        return parent::format($src);
    }
}

/// @endcond
