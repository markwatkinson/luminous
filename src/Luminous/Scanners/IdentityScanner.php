<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\Scanner;

// the identity scanner. Does what you expect.
// Implemented for consistency.

class IdentityScanner extends Scanner
{
    public function main()
    {
        $this->record($this->string(), null);
    }
}
