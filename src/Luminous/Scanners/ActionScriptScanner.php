<?php

namespace Luminous\Scanners;

// as far as I know, actionscript and javascript are both derivatives of
// ECMA script, and therefore we can subclass JavaScript's scanner and just
// and override the identifier names.
// but we also override init so as to prevent any embedding

class ActionScriptScanner extends EcmaScriptScanner
{
    public function init()
    {
        $this->embeddedServer = false;
        $this->embeddedScript = false;
        parent::init();

        // add preprocessor support
        $this->addPattern('PREPROCESSOR', '/\^\s*#.*/m');

        // clear the identifier map for JS and insert our own.
        // $this->identMap = array();
        $this->addIdentifierMapping('', array());

        $this->addIdentifierMapping('FUNCTION', array(
            'add',
            'chr',
            'clearInterval',
            'escape',
            'eval',
            'evaluate',
            'fscommand',
            'getProperty',
            'getTimer',
            'getVersion',
            'globalStyleFormat',
            'gotoAndPlay',
            'gotoAndStop',
            'ifFrameLoaded',
            'instanceOf',
            'isFinite',
            'isNaN',
            'loadMovie',
            'loadMovieNum',
            'loadVariables',
            'mbchr',
            'mblength',
            'mbord',
            'mbsubstring',
            'nextFrame',
            'nextScene',
            'onClipEvent',
            'ord',
            'parseFloat',
            'parseInt',
            'play',
            'prevFrame',
            'prevScene',
            'print',
            'printAsBitMap',
            'printNum',
            'printNum',
            'random',
            'scroll',
            'setInterval',
            'setProperty',
            'stop',
            'stopDrag',
            'substring',
            'super',
            'targetPath',
            'tellTarget',
            'toString',
            'toggleHighQuality',
            'trace',
            'unescape'
        ));

        $this->addIdentifierMapping('TYPE', array(
            'Accessibility',
            'Array',
            'Arguments',
            'Boolean',
            'Button',
            'ByteArray',
            'Camera',
            'Color',
            'Date',
            'Event',
            'FScrollPane',
            'FStyleFormat',
            'Function',
            'int',
            'Key',
            'LoadVars',
            'LocalConnection',
            'Math',
            'Microphone',
            'Mouse',
            'Movieclip',
            'Number',
            'Object',
            'Selection',
            'Sound',
            'Sprite',
            'String',
            'System',
            'TextField',
            'TextFormat',
            'Timer',
            'TimerEvent',
            'uint',
            'var',
            'void',
            'XML'
        ));

        $this->addIdentifierMapping('KEYWORD', array(
            'as',
            'break',
            'case',
            'catch',
            'class',
            'const',
            'continue',
            'default',
            'delete',
            'do',
            'else',
            'extends',
            'false',
            'finally',
            'for',
            'function',
            'if',
            'implements',
            'import',
            'in',
            'instanceof',
            'interface',
            'internal',
            'is',
            'native',
            'new',
            'null',
            'package',
            'private',
            'protected',
            'public',
            'return',
            'super',
            'switch',
            'static',
            'this',
            'throw',
            'to',
            'true',
            'try',
            'typeof',
            'use',
            'void',
            'while',
            'with'
        ));
    }

    public static function guessLanguage($src, $info)
    {
        // actionscript looks a lot like a cross between Java and, umm, well,
        // Java.
        // It has a semi-unique way of declaring types for arguments and
        // returns and so forth as argname:type, or function name(args):ret-type
        $p = 0.0;

        if (preg_match('/\\bfunction\s+\w+\s*\\([^\\)]+\\):(String|int|Number|void)/', $src)) {
            $p += 0.15;
        }
        if (preg_match('/\\bvar\s+\w+:(String|int|Number)/', $src)) {
            $p += 0.15;
        }
        return $p;
    }
}
