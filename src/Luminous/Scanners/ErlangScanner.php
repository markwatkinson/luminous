<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

/*
 * Erlang.
 *
 * Various comments refer to section numbers in the official spec, which can
 * be found at http://www.erlang.org/download/erl_spec47.ps.gz
 */
class ErlangScanner extends SimpleScanner
{
    // applies interpolation highlighting, can't find a proper
    // reference for this though
    public static function strFilter($token)
    {
        if (strpos($token[1], '~') == false) {
            return $token;
        }
        $token = Utils::escapeToken($token);
        $token[1] = preg_replace('/~(?:\d+|.)/', '<INTERPOLATION>$0</INTERPOLATION>', $token[1]);
        return $token;
    }

    // helper function: generates a regex which matches only numeric strings
    // in the given base
    public static function buildBasedIntRegex($base)
    {
        assert(2 <= $base && $base <= 16);
        $regex = '/(?i:[0-';
        if ($base <= 10) {
            $regex .= (string)$base-1;
        } else {
            $regex .= '9a-' . strtolower(dechex($base-1));
        }
        $regex .= '])+/';
        return $regex;
    }

    // 3.11 integers are pretty strange, you are allowed to specify base
    // 2 ><= b <= 16 arbitrarily.
    public function basedInt($matches)
    {
        $base = $matches[1];
        $match = $matches[0];
        $this->posShift(strlen($matches[0]));
        $number = null;
        if ($base >= 2 && $base <= 16) {
            $number = $this->scan($this->buildBasedIntRegex((int)$base));
        }
        if ($number !== null) {
            $match .= $number;
        }
        $this->record($match, 'NUMERIC');
        // now we're going to greedily consume any trailing numbers
        // This handles the case e.g. 2#001122,
        // we don't want the '22' to get caught as a separate literal, we want to
        // make sure it's NOT highlighted as a literal
        // so we consume it here.
        if ($this->scan('/\d+/') !== null) {
            $this->record($this->match(), null);
        }
    }

    public static function ooStreamFilter($tokens)
    {
        $c = count($tokens) - 1;
        for ($i = 0; $i < $c; $i++) {
            if ($tokens[$i][1] === ':') {
                if ($i > 0) {
                    $behind = &$tokens[$i - 1][0];
                    if ($behind === 'IDENT') {
                        $behind = 'OBJ';
                    }
                }
                if ($i < $c - 1) {
                    $ahead = &$tokens[$i+1][0];
                    if ($ahead === 'IDENT') {
                        $ahead = 'OO';
                    }
                    $i++;
                }
            }
        }
        return $tokens;
    }

    public function init()
    {
        $this->removeStreamFilter('oo-syntax');
        $this->removeFilter('comment-to-doc');
        $this->addStreamFilter('oo-syntax', array($this, 'ooStreamFilter'));
        $this->addFilter('interpolation', 'STRING', array($this, 'strFilter'));

        // 3.6 - technically should include the newline, but doesn't really matter
        $this->addPattern('COMMENT', '/%.*/');
        // stuff like -module, -author
        $this->addPattern('KEYWORD', '/^-(?:[a-z_]\w*)\\b/m');

        // 3.11 integer with radix
        $this->addPattern('BASED_INT', '/[+\\-]?(\d+)#/');
        $this->overrides['BASED_INT'] = array($this, 'basedInt');
        // float
        $this->addPattern('NUMERIC', '/[+\\-]?\d+\.\d+([eE][+\\-]?\d+)?/');
        // int
        $this->addPattern('NUMERIC', '/[+\\-]?\d+/');

        // 3.7 defines some 'separators', included are . : | || ; , ? -> and #
        // we'll capture these separately to operators
        // and map it to a keyword, for lack of anything better
        $this->addPattern('SEPARATOR', '/\\|\\||->|[\\.:\\|;,?#]/');
        $this->ruleTagMap['SEPARATOR'] = 'KEYWORD';

        // 3.9
        $this->addPattern('OPERATOR', '%==|/=|=:=|=<|>=|\\+\\+|--|<-|[+\\-*=!<>/]%');
        // 3.9 named ops
        $this->addIdentifierMapping('OPERATOR', array(
            'div',
            'rem',
            'or',
            'xor',
            'bor',
            'bxor',
            'bsl',
            'bsr',
            'and',
            'band',
            'not',
            'bnot'
        ));

        // char literals occur after a '$'
        $this->addPattern('CHARACTER', '/\\$(?:(?:\\\\(?:\\^\w+|\d+|.))|.)/');
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);

        // this looks like a string, but is in fact an 'atom'
        // we'll call it a value,
        $this->addPattern('VALUE', TokenPresets::$SINGLE_STR);
        $this->addPattern('IDENT', '/[a-z][@\w]*/');
        $this->addPattern('VARIABLE', '/[A-Z][@\w]*/');

        // 3.8
        $this->addIdentifierMapping('KEYWORD', array(
            'after',
            'begin',
            'case',
            'catch',
            'cond',
            'end',
            'fun',
            'if',
            'let',
            'of',
            'query',
            'receive',
            'when',
            // reserved, but undefined:
            'all_true',
            'some_true'
        ));
        $this->addIdentifierMapping('VALUE', array('true', 'false'));

        // from the BIF section
        $this->addIdentifierMapping('FUNCTION', array(
            'atom',
            'binary',
            'constant',
            'float',
            'integer',
            'function',
            'list',
            'number',
            'pid',
            'port',
            'reference',
            'tuple',
            'atom_to_list',
            'list_to_atom',
            'abs',
            'float',
            'float_to_list',
            'integer_to_list',
            'list_to_float',
            'list_to_integer',
            'round',
            'trunc',
            'binary_to_list',
            'binary_to_term',
            'concat_binary',
            'list_to_binary',
            'size',
            'split_binary',
            'term_to_binary',
            'element',
            'list_to_tuple',
            'seteleemnt',
            'size',
            'tuple_to_list',
            'hd',
            'length',
            't1',
            'check_process-code',
            'delete_module',
            'load_module',
            'preloaded',
            'purge_module',
            'module_loaded',
            'apply',
            'exit',
            'group_leader',
            'link',
            'list_to_pid',
            'pid_to_list',
            'process_flag',
            'process_info',
            'processes',
            'self',
            'spawn',
            'spawn_link',
            'unlink',
            'erase',
            'get',
            'get_keys',
            'put',
            'disconnect_node',
            'get_cookie',
            'halt',
            'is_alive',
            'monitor_node',
            'node',
            'nodes',
            'processes',
            'set_cookie',
            'set_node',
            'statistics',
            'register',
            'registered',
            'unregister',
            'whereis',
            'open_port',
            'port_close',
            'port_info',
            'ports',
            'date',
            'hash',
            'make_ref',
            'now',
            'throw',
            'time',
            'acos',
            'asin',
            'atan',
            'atan2',
            'cos',
            'cosh',
            'exp',
            'log',
            'log10',
            'pi',
            'pow',
            'sin',
            'sinh',
            'tan',
            'tanh'
        ));
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        foreach (array('module', 'author', 'export', 'include') as $s) {
            if (strpos($src, '-' . $s) !== false) {
                $p += 0.02;
            }
        }
        if (strpos($src, ' ++ ') !== false) {
            $p += 0.01;
        }
        if (preg_match('/[a-zA-Z_]\w*#[a-zA-Z_]+/', $src)) {
            $p += 0.05;
        }

        // doc comment
        if (preg_match('/^%%/m', $src)) {
            $p += 0.05;
        }
        return $p;
    }
}
