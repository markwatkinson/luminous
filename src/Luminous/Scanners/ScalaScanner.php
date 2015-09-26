<?php

namespace Luminous\Scanners;

use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;
use Luminous\Scanners\Keywords\JavaKeywords;

/**
 * Scala
 *
 * Direct port of old luminous language file.
 *
 * TODO: The XML literals may contain embedded scala code. This is bad
 * because we ignore that currently, and we may, in rare circumstances,
 * incorrectly pop a tag when in fact it's inside a scala expression
 *
 * Some comments reference section numbers of the scala spec:
 * http://www.scala-lang.org/sites/default/files/linuxsoft_archives/docu/files/ScalaReference.pdf
 */

class ScalaScanner extends SimpleScanner
{
    /**
     * Multiline comments nest
     */
    public function commentOverride()
    {
        $this->nestableToken('COMMENT', '%/\\*%', '%\\*/%');
    }

    /**
     * Scala has XML literals.
     */
    public function xmlOverride($matches)
    {
        // this might just be an inequality, so we first need to disambiguate
        // that

        // 1.5 - the disambiguation is pretty simple, an XML tag must
        // follow either whitespace, (, or {, and the '<' must be followed
        // by '[!?_a-zA-Z]
        // I'm not sure if a comment is a special case, or if it's treated as
        // whitespace...
        $xml = false;
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            $tok = $this->tokens[$i];
            $name = $tok[0];
            // ... but we're going treat it as a no-op and skip over it
            if ($name === 'COMMENT') {
                continue;
            }
            $lastChar = $tok[1][strlen($tok[1]) - 1];
            if (!(ctype_space($lastChar) || $lastChar === '(' || $lastChar === '{')) {
                break;
            }
            if (!$this->check('/<[!?a-zA-Z0-9_]/')) {
                break;
            }
            $xml = true;
        }
        if (!$xml) {
            $this->record($matches[0], 'OPERATOR');
            $this->posShift(strlen($matches[0]));
            return;
        }
        $subscanner = new XmlScanner();
        $subscanner->string($this->string());
        $subscanner->pos($this->pos());
        $subscanner->xmlLiteral = true;
        $subscanner->init();
        $subscanner->main();
        $tagged = $subscanner->tagged();
        $this->record($tagged, 'XML', true);
        $this->pos($subscanner->pos());
    }

    public function init()
    {
        $this->addPattern('COMMENT', TokenPresets::$C_COMMENT_SL);
        $this->addPattern('COMMENT_ML', '%/\\*%');
        $this->overrides['COMMENT_ML'] = array($this, 'commentOverride');

        // 1.3.1 integer literals, 1.3.2 floatingPointLiteral
        // Do the float first so it takes precedence, our scanner does not follow
        // the max-munch rule
        $digit = '\d';
        $exp = '(?:[eE][+-]?\d+)';
        $suffix = '[FfDd]';
        $this->addPattern('NUMERIC', "/(?: \d+\\.\d* | \\.\d+) $exp? $suffix? /x");
        $this->addPattern('NUMERIC', "/\d+($exp $suffix? |$exp?$suffix)/x");
        $this->addPattern('NUMERIC', '/(?:0x[a-fA-F0-9]+|\d+)[lL]?/');

        // 1.3.4 character literals
        // we can't really parse the unicode and work out what's printable,
        // so we'll just allow any unicode sequence
        $this->addPattern(
            'CHARACTER',
            "/'
                (
                    (?:\\\\ (?:u[a-f0-9]{1,4}|\d+|.))
                    | .
                )
            '/sx"
        );
        // 1.3.5 - 1.3.6
        // strings are kind of pythonic, triple quoting makes them multiline
        $this->addPattern(
            'STRING',
            '/"""
            (?: [^"\\\\]+ | \\\\. | ""[^"] | "[^"])*
            (?:"""|$)/sx'
        );
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR_SL);

        $this->addPattern('lt', '/</');
        $this->overrides['lt'] = array($this, 'xmlOverride');
        $this->addPattern('OPERATOR', '/[¬!%^&*-=+~;:|>\\/?\\\\]+/');

        $this->addPattern('IDENT', '/[a-z_]\w*/i');

        // 1.3.3 boolean literals
        $this->addIdentifierMapping('VALUE', array('true', 'false', 'null', 'None'));

        // from old luminous file
        $this->addIdentifierMapping('KEYWORD', array(
            'abstract',
            'case',
            'catch',
            'class',
            'def',
            'do',
            'else',
            'extends',
            'final',
            'finally',
            'for',
            'forSome',
            'if',
            'implicit',
            'import',
            'lazy',
            'match',
            'new',
            'object',
            'override',
            'package',
            'private',
            'protected',
            'return',
            'sealed',
            'super',
            'this',
            'throw',
            'trait',
            'try',
            'type',
            'val',
            'var',
            'while',
            'with',
            'yield'
        ));
        $this->addIdentifierMapping('TYPE', array(
            'boolean',
            'byte',
            'char',
            'dobule',
            'float',
            'int',
            'long',
            'string',
            'short',
            'unit',
            'Boolean',
            'Byte',
            'Char',
            'Double',
            'Float',
            'Int',
            'Long',
            'String',
            'Short',
            'Unit'
        ));
        // from Kate's syntax file
        $this->addIdentifierMapping('TYPE', array(
            'ActorProxy',
            'ActorTask',
            'ActorThread',
            'AllRef',
            'Any',
            'AnyRef',
            'Application',
            'AppliedType',
            'Array',
            'ArrayBuffer',
            'Attribute',
            'BoxedArray',
            'BoxedBooleanArray',
            'BoxedByteArray',
            'BoxedCharArray',
            'Buffer',
            'BufferedIterator',
            'Char',
            'Console',
            'Enumeration',
            'Fluid',
            'Function',
            'IScheduler',
            'ImmutableMapAdaptor',
            'ImmutableSetAdaptor',
            'Int',
            'Iterable',
            'List',
            'ListBuffer',
            'None',
            'Option',
            'Ordered',
            'Pair',
            'PartialFunction',
            'Pid',
            'Predef',
            'PriorityQueue',
            'PriorityQueueProxy',
            'Reaction',
            'Ref',
            'Responder',
            'RichInt',
            'RichString',
            'Rule',
            'RuleTransformer',
            'Script',
            'Seq',
            'SerialVersionUID',
            'Some',
            'Stream',
            'Symbol',
            'TcpService',
            'TcpServiceWorker',
            'Triple',
            'Unit',
            'Value',
            'WorkerThread',
            'serializable',
            'transient',
            'volatile'
        ));

        $this->addIdentifierMapping('TYPE', JavaKeywords::TYPES);
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0;
        // func def, a lot like python
        if (preg_match('/\\bdef\s+\w+\s*\(/', $src)) {
            $p += 0.05;
        }
        // val x = y
        if (preg_match('/\\bval\s+\w+\s*=/', $src)) {
            $p += 0.1;
        }
        // argument types
        if (preg_match('/\\(\s*\w+\s*:\s*(String|Int|Array)/', $src)) {
            $p += 0.05;
        }
        // tripled quoted strings, like python
        if (preg_match('/\'{3}|"{3}/', $src)) {
            $p += 0.05;
        }
        return $p;
    }
}
