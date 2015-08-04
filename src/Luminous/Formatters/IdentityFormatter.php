<?php

/** @cond ALL */

namespace Luminous\Formatters;

/**
 * Identity formatter. Returns what it's given. Implemented for consistency.
 */
class IdentityFormatter extends Formatter
{
    public function format($str)
    {
        return $str;
    }
}

/** @endcond */
