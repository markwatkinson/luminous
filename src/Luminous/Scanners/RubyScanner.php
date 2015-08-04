<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\Filters;
use Luminous\Core\Scanners\Scanner;

/*
 * Ruby's grammar is basically insane. We're not going to aim to correctly
 * highlight all legal Ruby code because we'll be here all year and we'll still
 * get it wrong, but we're going to have a go at getting the standard stuff
 * right as well as:
 *   heredocs
 *   balanced AND NESTED string/regex delimiters
 *   interpolation
 *
 * disclaimer: I don't actually know Ruby.
 *
 * Problem is that Ruby *appears* to have to disambiguate loads of stuff at
 * runtime, which is frankly a little optimistic for a syntax highlighter.
 * Ruby allows you to omit calling parantheses, so it's not practical (and
 * impossible if the code snippet is incomplete) to figure out operator/operand
 * position. e.g.
 *      x = y %r/z/x
 * is x = y mod r div z div x, unless y is a function, in which case it's:
 *      x = y( /z/x )     where /z/x is a regex
 */

class RubyScanner extends Scanner
{
    // set to true if this is a nested scanner which needs to exit if it
    // encounters a } while nothing else is on the stack, i.e. it is being
    // used to process an interpolated block
    public $interpolation = false;
    protected $curleyBraces = 0; // poor man's curly brace stack.

    public $rails = false;

    // operators depend somewhat on whether or not rails is active, else we
    // don't want to consume a '%' if it comes right before a '>', we want
    // to leave that for the rails close-tag detection
    private $operatorRegex = null;
    private $stringRegex = null;
    private $commentRegex = null;

    // gaaah
    private $numeric =
        '/
            (?:
                #control codes
                (?:\?(?:\\\[[:alpha:]]-)*[[:alpha:]])
                |
                #hex
                (?:0[xX](?>[0-9A-Fa-f]+)[lL]*)
                |
                # binary
                (?:0[bB][0-1]+)
                |
                #octal
                (?:0[oO0][0-7]+)
                |
                # regular number
                (?:
                    (?>[0-9]+)
                    (?:
                        # fraction
                        (?:
                            (?:\.?(?>[0-9]+)?
                                (?:(?:[eE][\+\-]?)?(?>[0-9]+))?
                            )
                        )
                    )?
                )
                |
                (
                  # or only after the point, float x = .1;
                  \.(?>[0-9]+)(?:(?:[eE][\+\-]?)?(?>[0-9]+))?
                )
            )
            (?:_+\d+)*
        /x';

    /**
     * queue of heredoc declarations which will need to be handled as soon as EOL is reached
     * each element is a tuple: (delimiter(str), identable?, interpolatable?)
     */
    private $heredocs = array();

    public function init()
    {
        $this->commentRegex = $this->rails ? "/ \# (?: [^\n%]*+ | %(?!>))* /x" : '/#.*/';
        // http://www.zenspider.com/Languages/Ruby/QuickRef.html#23
        $this->operatorRegex =
            '/
                \?   | ;
                | ::? | \*[=\*]? | \/=? | -=? | %=? | ^=? | &&? | \|\|? | \.{2,3}
                | \^=?
                | < (?:=>|<|=)? | >=?
                | =[>~] | ={1,3}
                | \+=? | ![=~]?
            /x';
        // $this->operatorRegex = '/(?: [~!^&*\-+=:;|<>\/?';
        // if ($this->rails) {
        //     $this->operatorRegex .= ']+|%(?!>))+';
        // } else {
        //     $this->operatorRegex .= '%]+)';
        // }
        // $this->operatorRegex .= '/x';

        $this->addIdentifierMapping('KEYWORD', array(
            'BEGIN',
            'END',
            'alias',
            'begin',
            'break',
            'case',
            'class',
            'def',
            'defined?',
            'do',
            'else',
            'elsif',
            'end',
            'ensure',
            'for',
            'if',
            'module',
            'next',
            'redo',
            'rescue',
            'retry',
            'return',
            'self',
            'super',
            'then',
            'undef',
            'unless',
            'until',
            'when',
            'while',
            'yield',
            'false',
            'nil',
            'self',
            'true',
            '__FILE__',
            '__LINE__',
            'TRUE',
            'FALSE',
            'NIL',
            'STDIN',
            'STDERR',
            'ENV',
            'ARGF',
            'ARGV',
            'DATA',
            'RUBY_VERSION',
            'RUBY_RELEASE_DATE',
            'RUBY_PLATFORM',
            'and',
            'in',
            'not',
            'or',
            'public',
            'private',
            'protected'
        ));

        // http://www.tutorialspoint.com/ruby/ruby_builtin_functions.htm
        // don't know how reliable that is... doesn't look incredibly inspiring
        $this->addIdentifierMapping('FUNCTION', array(
            'abord',
            'Array',
            'at_exit',
            'autoload',
            'binding',
            'block_given?',
            'callcc',
            'caller',
            'catch',
            'chomp',
            'chomp!',
            'chop',
            'chop!',
            'eval',
            'exec',
            'exit',
            'exit!',
            'fail',
            'Float',
            'fork',
            'format',
            'gets',
            'global_variables',
            'gsub',
            'gsub!',
            'Integer',
            'lambda',
            'proc',
            'load',
            'local_variables',
            'loop',
            'open',
            'p',
            'print',
            'printf',
            'proc',
            'puts',
            'raise',
            'fail',
            'rand',
            'readlines',
            'require',
            'scan',
            'select',
            'set_trace_func',
            'sleep',
            'split',
            'sprintf',
            'srand',
            'String',
            'syscall',
            'system',
            'sub',
            ',sub!',
            'test',
            'throw',
            'trace_var',
            'trap',
            'untrace_var',
            'abs',
            'ceil',
            'coerce',
            'divmod',
            'floor',
            'integer?',
            'modulo',
            'nonzero?',
            'remainder',
            'round',
            'truncate',
            'zero?',
            'chr',
            'size',
            'step',
            'times',
            'to_f',
            'to_int',
            'to_i',
            'finite?',
            'infinite?',
            'nan?',
            'atan2',
            'cos',
            'exp',
            'frexp',
            'ldexp',
            'log',
            'log10',
            'sin',
            'sqrt',
            'tan'
        ));

        // this can break a bit with Ruby's whacky syntax
        $this->removeFilter('pcre');
        // don't want this.
        $this->removeFilter('comment-to-doc');

        $this->addFilter('REGEX', function ($tok) {
            return Filters::pcre($tok, (isset($tok[1][0]) && $tok[1][0] === "/"));
        });
    }

    protected function isRegex()
    {
        /*
         * Annoyingly I don't really know exactly what rules Ruby uses for
         * disambiguating regular expressions. There might be some incorrect
         * assumptions in here.
         */

        if ($this->check('%/=\s%')) {
            return false;
        }
        $followingSpace = (bool)$this->check("%/[ \t]%");
        $space = false;
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            $tok = $this->tokens[$i];
            if ($tok[0] === 'COMMENT') {
                continue;
            } elseif ($tok[0] === 'OPERATOR') {
                return true;
            } elseif ($tok[0] === 'STRING') {
                return true;
            } elseif ($tok[1] === '(' || $tok[1] === ',' || $tok[1] === '{' || $tok[1] === '[') {
                // this is definitely an operand
                return true;
            } elseif ($tok[0] === null) {
                $space = true;
                continue;
            } elseif ($tok[0] === 'NUMERIC') {
                // this is definitely an operator
                return false;
            } elseif ($tok[0] === 'IDENT' || $tok[0] === 'CONSTANT' || $tok[0] === 'VALUE' /* aka :symbols */) {
                // this could be an operator or operand
                // Kate's syntax engine seems to operate on the following basis:
                if ($space && $followingSpace) {
                    return false;
                }
                return $space;
            }
            return false;
        }
        return true; // no preceding tokens, presumably a code fragment.
    }

    protected function interpolate()
    {
        $interpolationScanner = new RubyScanner();
        $interpolationScanner->string($this->string());
        $interpolationScanner->pos($this->pos());
        $interpolationScanner->interpolation = true;
        $interpolationScanner->init();
        $interpolationScanner->main();
        $this->record($interpolationScanner->tagged(), 'INTERPOLATION', true);
        $this->pos($interpolationScanner->pos());
    }

    // handles the heredoc array. Call at eol/bol when the heredoc queue is
    // not empty
    protected function doHeredoc()
    {
        assert(!empty($this->heredocs));

        $start = $this->pos();

        for ($i = 0; $i < count($this->heredocs);) {
            $top = $this->heredocs[$i];
            list($ident, $identable, $interpolatable) = $top;
            $searches = array(sprintf('/^%s%s\\b/m', $identable ? "[ \t]*" : '', preg_quote($ident, '/')));
            if ($interpolatable) {
                $searches[] = '/\#\{/';
            }
            list($next, $matches) = $this->getNext($searches);
            if ($next === -1) {
                // no match for end delim, run to EOS
                $this->record(substr($this->string(), $start), 'HEREDOC');
                $this->terminate();
                break;
            }
            assert($matches !== null);
            if ($matches[0] === '#{') { // interpolation, break heredoc and do that.
                $this->pos($next);
                $this->record(substr($this->string(), $start, $this->pos() - $start), 'HEREDOC');
                $this->record($matches[0], 'DELIMITER');
                $this->posShift(strlen($matches[0]));
                $this->interpolate();
                if ($this->peek() === '}') {
                    $this->record($this->get(), 'DELIMITER');
                }
                $start = $this->pos();
            } else {
                //
                $this->pos($next);
                $this->record(substr($this->string(), $start, $this->pos() - $start), 'HEREDOC');
                $this->record($matches[0], 'DELIMITER');
                $this->pos($next + strlen($matches[0]));
                $start = $this->pos();
                $i++;
            }
            // subscanner might have consumed all the string, in which case there's
            // no point continuing
            if ($this->eos()) {
                break;
            }
        }
        // we may or may not have technically addressed all the heredocs in the
        // queue, but we do want to clear them out now
        $this->heredocs = array();
    }

    private function recordStringRange($from, $to, $type, $split)
    {
        if ($to === $from) {
            return;
        }
        $substr = substr($this->string(), $from, $to - $from);
        if ($split) {
            foreach (preg_split('/(\s+)/', $substr, - 1, PREG_SPLIT_DELIM_CAPTURE) as $s) {
                $type_ = preg_match('/^\s+$/', $s) ? null : $type;
                $this->record($s, $type_);
            }
        } else {
            $this->record($substr, $type);
        }
    }

    // handles string types (inc regexes), which may have nestable delimiters or
    // interpolation.
    // strdata is defined in the big ugly block in main()
    // TODO: proper docs
    protected function doString($strData)
    {
        list($type, $openDelimiter, $closeDelimiter, $pos, $interpolation, $fancyDelim, $split) = $strData;
        $balanced = $openDelimiter !== $closeDelimiter;
        $template = '/(?<!\\\\)((?:\\\\\\\\)*)(%s)/';
        $patterns = array();
        $patterns['term'] = sprintf($template, preg_quote($closeDelimiter, '/'));
        if ($balanced) {
            // for nesting balanced delims
            $patterns['nest'] = sprintf($template, preg_quote($openDelimiter, '/'));
        }
        if ($interpolation) {
            $patterns['interp'] = sprintf($template, preg_quote('#{', '/'));
        }
        $nestingLevel = 0;
        $break = false;
        while (!$break) {
            list($name, $index, $matches) = $this->getNextNamed($patterns);

            if ($name === null) {
                // special case, no matches, record the rest of the string and break
                // immediately
                $this->recordStringRange($pos, strlen($this->string()), $type, $split);
                $this->terminate();
                break;
            } elseif ($name === 'nest') {
                // nestable opener
                $nestingLevel++;
                $this->pos($index + strlen($matches[0]));
            } elseif ($name === 'term') {
                // terminator, may be nested
                if ($nestingLevel === 0) {
                    // wasn't nested, real terminator.
                    if ($fancyDelim) {
                        // matches[1] is either empty or a sequence of backslashes
                        $this->recordStringRange($pos, $index + strlen($matches[1]), $type, $split);
                        $this->record($matches[2], 'DELIMITER');
                    } else {
                        $this->recordStringRange($pos, $index + strlen($matches[0]), $type, $split);
                    }
                    $break = true;
                } else {
                    // pop a nesting level
                    $nestingLevel--;
                }
                $this->pos($index + strlen($matches[0]));
            } elseif ($name === 'interp') {
                // interpolation - temporarily break string highlighting, then
                // do interpolation, then resume.
                $this->recordStringRange($pos, $index + strlen($matches[1]), $type, $split);
                $this->record($matches[2], 'DELIMITER');
                $this->pos($index + strlen($matches[0]));

                $this->interpolate();
                if (($c = $this->peek()) === '}') {
                    $this->record($this->get(), 'DELIMITER');
                }
                $pos = $this->pos();
            } else {
                assert(0);
            }
            if ($break) {
                break;
            }
        }
        if ($type === 'REGEX' && $this->scan('/[iomx]+/')) {
            $this->record($this->match(), 'KEYWORD');
        }
    }

    public function main()
    {
        while (!$this->eos()) {
            if ($this->bol() && !empty($this->heredocs)) {
                $this->doHeredoc();
            }

            if ($this->interpolation) {
                $c = $this->peek();
                if ($c === '{') {
                    $this->curleyBraces++;
                } elseif ($c === '}') {
                    $this->curleyBraces--;
                    if ($this->curleyBraces <= 0) {
                        break;
                    }
                }
            }
            if ($this->rails && $this->check('/-?%>/')) {
                break;
            }

            $c = $this->peek();

            $variableRegex =
                '/\\$
                    (?:
                        (?:[!@`\'\+1~=\/\\\,;\._0\*\$\?:"&<>])
                        |
                        (?: -[0adFiIlpvw])
                        |
                        (?:DEBUG|FILENAME|LOAD_PATH|stderr|stdin|stdout|VERBOSE)
                    )
                /x';
            $stringRegex = '/[\'"`]|%( [qQrswWx](?![[:alnum:]]|$) | (?![[:alnum:]\s]|$))/xm';
            if ($c === '=' && $this->scan('/^=begin .*? (^=end|\\z)/msx')) {
                $this->record($this->match(), 'DOCCOMMENT');
            } elseif ($c === '#' && $this->scan($this->commentRegex)) {
                $this->record($this->match(), 'COMMENT');
            } elseif ($this->scan($this->numeric) !== null) {
                $this->record($this->match(), 'NUMERIC');
            } elseif ($c === '$' && $this->scan($variableRegex) || $this->scan('/(\\$|@@?)\w+/')) {
                $this->record($this->match(), 'VARIABLE');
            } elseif ($this->scan('/:\w+/')) {
                $this->record($this->match(), 'VALUE');
            } elseif ($c === '<' && $this->scan('/(<<(-?))([\'"`]?)([A-Z_]\w*)(\\3)/i')) {
                $m = $this->matchGroups();
                $this->record($m[0], 'DELIMITER');
                $hdoc = array($m[4], $m[2] === '-', $m[3] !== "'");
                $this->heredocs[] = $hdoc;
            } elseif (strspn($c, '"\'`%') === 1 && $this->scan($stringRegex) || ($c === '/' && $this->isRegex())) {
                // TODO: "% hello " is I think a valid string, using whitespace as
                // delimiters. We're going to disallow this for now because
                // we're not disambiguating between that and modulus
                $interpolation = false;
                $type = 'STRING';
                $delimiter;
                $pos;
                $fancyDelim = false;
                $split = false;

                if ($c === '/') {
                    $interpolation = true;
                    $type = 'REGEX';
                    $delimiter = $c;
                    $pos = $this->pos();
                    $this->get();
                } else {
                    $pos = $this->matchPos();
                    $delimiter = $this->match();
                    if ($delimiter === '"') {
                        $interpolation = true;
                    } elseif ($delimiter === "'") {
                    } elseif ($delimiter === '`') {
                        $type = 'FUNCTION';
                    } else {
                        $delimiter = $this->get();
                        $m1 = $this->matchGroup(1);
                        if ($m1 === 'Q' || $m1 === 'r' || $m1 === 'W' || $m1 === 'x') {
                            $interpolation = true;
                        }
                        if ($m1 === 'w' || $m1 === 'W') {
                            $split = true;
                        }
                        if ($m1 === 'x') {
                            $type = 'FUNCTION';
                        } elseif ($m1 === 'r') {
                            $type = 'REGEX';
                        }

                        $fancyDelim = true;
                        $this->record($this->match() . $delimiter, 'DELIMITER');
                        $pos = $this->pos();
                    }
                }
                $data = array(
                    $type,
                    $delimiter,
                    Utils::balanceDelimiter($delimiter),
                    $pos,
                    $interpolation,
                    $fancyDelim,
                    $split
                );
                $this->doString($data);
            } elseif ((ctype_alpha($c) || $c === '_') && ($m = $this->scan('/[_a-zA-Z]\w*[!?]?/')) !== null) {
                $this->record($m, ctype_upper($m[0]) ? 'CONSTANT' : 'IDENT');
                if ($m === '__END__') {
                    if (!$this->interpolation) {
                        $this->record($this->rest(), null);
                        $this->terminate();
                    }
                    break;
                }
            } elseif ($this->scan($this->operatorRegex)) {
                $this->record($this->match(), 'OPERATOR');
            } elseif ($this->scan("/[ \t]+/")) {
                $this->record($this->match(), null);
            } else {
                $this->record($this->get(), null);
            }
        }
        // In case not everything was popped
        if (isset($this->state[0])) {
            $this->record(
                substr($this->string(), $this->state[0][3], $this->pos() - $this->state[0][3]),
                $this->state[0][0]
            );
            $this->terminate();
        }
    }

    public static function guessLanguage($src, $info)
    {
        if (strpos($info['shebang'], 'ruby') !== false) {
            return 1.0;
        } elseif ($info['shebang']) {
            return 0;
        }
        $p = 0;
        if (strpos($src, 'nil')) {
            $p += 0.05;
        }
        if (strpos($src, '.nil?')) {
            $p += 0.02;
        }
        if (strpos($src, '.empty?')) {
            $p += 0.02;
        }
        // interpolation
        if (strpos($src, '#{$')) {
            $p += 0.02;
        }
        // @ and $ vars
        if (preg_match('/@[a-zA-Z_]/', $src) && preg_match('/\\$[a-zA-Z_]/', $src)) {
            $p += 0.02;
        }
        // symbols
        if (preg_match('/:[a-zA-Z_]/', $src)) {
            $p += 0.01;
        }
        // func def - no args
        if (preg_match("/^\s*+def\s++[a-zA-Z_]\w*+[ \t]*+[\n\r]/m", $src)) {
            $p += 0.1;
        }
        // {|x[,y[,z...]]| is a very ruby-like construct
        if (preg_match('/ \\{ \\| \s*+ [a-zA-Z_]\w*+ \s*+ (,\s*+[a-zA-Z_]\w*+\s*+)*+ \\|/x', $src)) {
            $p += 0.15;
        }
        // so is 'do |x|'
        if (preg_match("/\\bdo\s*+\\|[^\\|\r\n]++\\|/", $src)) {
            $p += 0.05;
        }

        // class defs with inheritance has quite distinct syntax
        // class x < y
        if (preg_match("/^ \s* class \s+ \w+ \s* < \s* \w+(::\w+)* [\t ]*+ [\r\n] /mx", $src)) {
            $p += 0.1;
        }

        $num_lines = $info['num_lines'];
        // let's say if 5% of lines are hash commented that's a good thing
        if (substr_count($src, '#') > $num_lines/20) {
            $p += 0.05;
        }
        // =~ /regex/
        if (preg_match('%=~\s++/%', $src)) {
            $p += 0.02;
        }

        if (preg_match('/unless\s+[^\?]++\?/', $src)) {
            $p += 0.05;
        }

        if (preg_match('/^(\s*+)def\s+.*^\1end\s/ms', $src)) {
            $p += 0.05;
        }

        if (preg_match('/\.to_\w+(?=\s|$)/', $src)) {
            $p += 0.01;
        }

        return $p;
    }
}
