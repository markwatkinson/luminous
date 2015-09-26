<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\CKeywords;

// TODO: trigraph... does anyone use these?

class CppScanner extends SimpleScanner
{
    public function __construct($src = null)
    {
        parent::__construct($src);
        $this->addFilter('preprocessor', 'PREPROCESSOR', array($this, 'preprocessorFilter'));

        $this->addIdentifierMapping('FUNCTION', CKeywords::FUNCTIONS);
        $this->addIdentifierMapping('KEYWORD', CKeywords::KEYWORDS);
        $this->addIdentifierMapping('TYPE', CKeywords::TYPES);
    }

    public function init()
    {
        // http://www.lysator.liu.se/c/ANSI-C-grammar-l.html
        //     D                       [0-9]
        //     L                       [a-zA-Z_]
        //     H                       [a-fA-F0-9]
        //     E                       [Ee][+-]?{D}+
        //     FS                      (f|F|l|L)
        //     IS                      (u|U|l|L)*//
        //     {L}({L}|{D})*           ident
        //     0[xX]{H}+{IS}?          hex
        //     0{D}+{IS}?              octal
        //     {D}+{IS}?               int
        //     L?'(\\.|[^\\'])+'       char
        //     {D}+{E}{FS}?            real/float
        //     {D}*"."{D}+({E})?{FS}?  real/float
        //     {D}+"."{D}*({E})?{FS}?  real/float
        //     L?\"(\\.|[^\\"])*\"     string, but we should exclude nl

        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_ML);
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);
        $this->addPattern('STRING', "/L?\"(?: [^\\\\\"\n]+ | \\\\.)*(?:$|\")/xms");
        // if memory serves, a char looks like this:
        $this->addPattern('CHARACTER', "/L? ' (?: \\\\(?: x[A-F0-9]{1,2}| . ) | . ) (?: '|$)/ixm");

        $this->addPattern('OPERATOR', '@[!%^&*\-/+=~:?.|<>]+@');

        $this->addPattern('NUMERIC', '/0[xX][A-F0-9]+[uUlL]*/i');
        $this->addPattern(
            'NUMERIC',
            '/
                (?:
                    (?: \d* \.\d+   |   \d+\.\d*)
                    ([eE][+-]?\d+)?
                    ([fFlL]?)
                )
            /ix'
        );
        $this->addPattern(
            'NUMERIC',
            '/
                \d+([uUlL]+ | ([eE][+-]?\d+)?[fFlL]? | ) #empty string on the end
            /x'
        ); //inc octal

        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);
        $this->addPattern('PREPROCESSOR', '/^[ \t]*\#/m');
        $this->addPattern('IDENT', '/[a-zA-Z_]+\w*/');

        $this->overrides['PREPROCESSOR'] = array($this, 'preprocessorOverride');
    }

    public function preprocessorOverride()
    {
        $this->skipWhitespace();
        // #if 0s nest, according to Kate, which sounds reasonable
        $pattern = '/^\s*#\s*if\s+0\\b/m';
        if ($this->check($pattern)) {
            $this->nestableToken('COMMENT', '/^\s*#\s*if(?:n?def)?\\b/m', '/^\s*#\s*endif\\b/m');
        } else {
            // a preprocessor statement may have nested comments and strings. We
            // go the lazy route and just zap the whole thing with a regex and let a
            // filter figure out any nested highlighting
            $this->scan(
                "@ \#
                    (?: [^/\n\\\\]+
                        | /\* (?> [^\\*]+ | (?:\*(?!/))+ ) (?: $|\*/)    # nested ML comment
                        | //.*   # nested SL comment
                        | /
                        | \\\\(?s:.) # escape, and newline
                    )*
                @x"
            );
            $this->record($this->match(), 'PREPROCESSOR');
        }
    }

    public static function preprocessorFilterCb($matches)
    {
        if (!isset($matches[0]) || !isset($matches[0][0])) {
            return ''; // shouldn't ever happen
        }
        if ($matches[0][0] === '"') {
            return Utils::tagBlock('STRING', $matches[0]);
        } elseif ($matches[0][0] === '&') {
            return '&lt;' . Utils::tagBlock('STRING', $matches[1]) . '&gt;';
        } else {
            return Utils::tagBlock('COMMENT', $matches[0]);
        }
    }

    public static function preprocessorFilter($token)
    {
        $token = Utils::escapeToken($token);
        $token[1] = preg_replace_callback(
            "@
                (?:\" (?> [^\\\\\n\"]+ | \\\\. )* (?: \"|$) | (?: &lt; (.*?) &gt;))
                | // .*
                | /\* (?s:.*?) (\*/ | $)
            @x",
            array('Luminous\\Scanners\\CppScanner', 'preprocessorFilterCb'),
            $token[1]
        );
        return $token;
    }

    public static function guessLanguage($src, $info)
    {
        // Obviously, C tends to look an awful lot like pretty much every other
        // language. Its only real pseudo-distinct feature is the ugly
        // preprocessor and "char * ", so let's go with that

        $p = 0.0;
        if (preg_match('/^\s*+#\s*+(include\s++[<"]|ifdef|endif|define)\\b/m', $src)) {
            $p += 0.3;
        }
        if (preg_match('/\\bchar\s*\\*\s*\w+/', $src)) {
            $p += 0.05;
        }
        if (preg_match('/\\bmalloc\s*\\(/', $src)) {
            $p += 0.02;
        }
        // TODO we could guess at some C++ stuff too
        return $p;
    }
}
