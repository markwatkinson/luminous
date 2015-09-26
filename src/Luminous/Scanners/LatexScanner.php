<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\StatefulScanner;

/*
 * LaTeX scanner,
 * brief explanation: we're using the stateful scanner to handle marginally
 * different rulesets in math blocks.
 * We could add in an awful lot of detail, everything is pretty generic right
 * now, we don't look for any specific names or anything, but it'll suffice
 * for basic highlighting.
 */
class LatexScanner extends StatefulScanner
{
    public function init()
    {
        // math states
        $this->addPattern('displaymath', '/\\$\\$/', '/\\$\\$/');
        // literal '\[' and '\]'
        $this->addPattern('displaymath', '/\\\\\\[/', '/\\\\\\]/');
        $this->addPattern('mathmode', '/\\$/', '/\\$/');

        // terminals
        $this->addPattern('COMMENT', '/%.*/');
        $this->addPattern('NUMERIC', '/\d+(\.\d+)?\w*/');
        $this->addPattern('MATH_FUNCTION', '/\\\\(?:[a-z_]\w*|[^\]])/i');
        $this->addPattern('MATHOP', '/[\\*^\\-=+]+/');

        $this->addPattern('FUNCTION', '/\\\\(?:[a-z_]\w*|.)/i');
        $this->addPattern('IDENT', '/[a-z_]\w*/i');

        $this->addPattern('OPERATOR', '/[\[\]\{\}]+/');

        $mathTransition = array('NUMERIC', 'MATH_FUNCTION', 'MATHOP');

        $this->transitions = array(
            'initial' => array('COMMENT', 'OPERATOR', 'displaymath', 'mathmode', 'FUNCTION', 'IDENT'),
            // omitting initial state defn. makes it transition to everything
            'displaymath' => $mathTransition,
            'mathmode' => $mathTransition,
        );

        $this->ruleTagMap = array(
            'displaymath' => 'INTERPOLATION',
            'mathmode' => 'INTERPOLATION',
            'MATHOP' => 'OPERATOR',
            'MATH_FUNCTION' => 'VALUE', // arbitrary way to distinguish it from non
            // math mode functions
        );
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        foreach (array('documentclass', 'usepackage', 'title', 'maketitle', 'end') as $cmd) {
            if (strpos($src, '\\' . $cmd) !== false) {
                $p += 0.1;
            }
        }
        // count the number of backslashes
        $bslashes = substr_count($src, '\\');
        if ($bslashes > $info['num_lines']) {
            $p += 0.1;
        }
        if (substr_count($src, '%') > $info['num_lines']/10) {
            $p += 0.02;
        }
        return $p;
    }
}
