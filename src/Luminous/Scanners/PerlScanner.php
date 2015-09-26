<?php

namespace Luminous\Scanners;

use Luminous\Core\Utils;
use Luminous\Core\TokenPresets;
use Luminous\Core\Scanners\SimpleScanner;

/*
 * Like ruby, I think it's impossible to fully tokenize Perl without
 * executing some of the code to disambiguate some symbols. As such, we're
 * going to settle for 'probably right' rather than 'definitely right'.
 *
 * TODO: I think this is mostly complete but it needs interpolation
 * highlighting in strings and heredoc, and a regex highlighting filter,
 * probably a stream filter
 */

class PerlScanner extends SimpleScanner
{
    // keeps track of heredocs we need to handle
    private $heredoc = null;

    // helper function:
    // consumes a string until the given delimiter (which may be balanced).
    // will handle nested balanced delimiters.
    // this is used as the general case for perl quote-operators like:
    //    q/somestring/      q"somestring", q@somestring@, q[some[]string]
    // it can be called twice for s/someregex/somereplacement/
    // expects the initial opening delim to already have been consumed
    public function consumeString($delimiter, $type)
    {
        $close = Utils::balanceDelimiter($delimiter);

        $balanced = $close !== $delimiter;
        $patterns = array('/(?<!\\\\)((?:\\\\\\\\)*)(' . preg_quote($close, '/') . ')/');

        if ($balanced) {
            $patterns[] = '/(?<!\\\\)((?:\\\\\\\\)*)(' . preg_quote($delimiter, '/') . ')/';
        }

        $stack = 1; // we're already inside the string
        $start = $this->pos();
        $closeDelimiterMatch = null;
        while ($stack) {
            $next = $this->getNext($patterns);
            if ($next[0] === -1) {
                $this->terminate();
                $finish = $this->pos();
                break;
            } elseif ($balanced && $next[1][2] === $delimiter) {
                $stack++;
                $finish = $next[0] + strlen($next[1][0]);
            } elseif ($next[1][2] === $close) {
                $stack--;
                if (!$stack) {
                    $closeDelimiterMatch = $next[1][2];
                }
                $finish = $next[0] + strlen($next[1][1]);
            } else {
                assert(0);
            }
            $this->pos($next[0] + strlen($next[1][0]));
        }
        $substr = substr($this->string(), $start, $finish - $start);
        // special case for qw, the string is not a 'STRING', it is actually
        // a whitespace separated list of strings. So we need to split it and
        // record them separately
        if ($type === 'SPLIT_STRING') {
            foreach (preg_split('/(\s+)/', $substr, -1, PREG_SPLIT_DELIM_CAPTURE) as $token) {
                if (preg_match('/^\s/', $token)) {
                    $this->record($token, null);
                } else {
                    $this->record($token, 'STRING');
                }
            }
        } else {
            $this->record($substr, $type);
        }
        if ($closeDelimiterMatch !== null) {
            $this->record($closeDelimiterMatch, 'DELIMITER');
        }
    }

    // Helper function: guesses whether or not a slash is a regex delimiter
    // by looking behind in the token stream.
    public function isDelimiter()
    {
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            $t = $this->tokens[$i];
            if ($t[0] === null || $t[0] === 'COMMENT') {
                continue;
            } elseif ($t[0] === 'OPENER' || $t[0] === 'OPERATOR') {
                return true;
            } elseif ($t[0] === 'IDENT') {
                switch ($t[1]) {
                    // named operators
                    case 'lt':
                    case 'gt':
                    case 'le':
                    case 'ge':
                    case 'eq':
                    case 'ne':
                    case 'cmp':
                    case 'and':
                    case 'or':
                    case 'xor':
                    // other keywords/functions
                    case 'if':
                    case 'elsif':
                    case 'while':
                    case 'unless':
                    case 'split':
                    case 'print':
                        return true;
                }
            }
            return false;
        }
        return true;
    }

    // override function for slashes, to disambiguate regexen from division
    // operators.
    public function slashOverride($matches)
    {
        $this->pos($this->pos() + strlen($matches[0]));
        // this can catch '//', which I THINK is an operator but I could be wrong.
        if (strlen($matches[0]) === 2 || !$this->isDelimiter()) {
            $this->record($matches[0], 'OPERATOR');
        } else {
            $this->record($matches[0], 'DELIMITER');
            $this->consumeString($matches[0], 'REGEX');
            if ($this->scan('/[cgimosx]+/')) {
                $this->record($this->match(), 'KEYWORD');
            }
        }
    }

    // override function for 'quote-like operators'
    // e.g.  m"hello", m'hello', m/hello/, m(hello), m(he()l()o())
    public function strOverride($matches)
    {
        $this->pos($this->pos() + strlen($matches[0]));

        $this->record($matches[0], 'DELIMITER');

        $f = $matches[1];

        $type = 'STRING';
        if ($f === 'm' || $f === 'qr' || $f === 's' || $f === 'tr' || $f === 'y') {
            $type = 'REGEX';
        } elseif ($f === 'qw') {
            $type = 'SPLIT_STRING';
        }

        $this->consumeString($matches[3], $type);
        if ($f === 's' || $f === 'tr' || $f === 'y') {
            // s/tr/y take two strings, e.g. s/something/somethingelse/, so we
            // have to consume the next delimiter (if it exists) and consume the
            // string, again.

            // if delims were balanced, there's a new delimiter right here, e.g.
            // s[something][somethingelse]
            $this->skipWhitespace();
            $balanced = Utils::balanceDelimiter($matches[3]) !== $matches[3];
            if ($balanced) {
                $delim2 = $this->scan('/[^a-zA-Z0-9]/');
                if ($delim2 !== null) {
                    $this->record($delim2, 'DELIMITER');
                    $this->consumeString($delim2, 'STRING');
                }
            } else {
                // if they weren't balanced then the delimiter is the same, and has
                // already been consumed as the end-delim to the first pattern
                $this->consumeString($matches[3], 'STRING');
            }
        }
        if ($type === 'REGEX' && $this->scan('/[cgimosxpe]+/')) {
            $this->record($this->match(), 'KEYWORD');
        }
    }

    // this override handles the heredoc declaration, and makes a note of it
    // it adds a new token (a newline) which is overridden to invoke the real
    // heredoc handling. This is because in Perl, heredocs declarations need not
    // be the end of the line so we can't necessarily start heredocing straight
    // away.
    public function heredocOverride($matches)
    {
        list($group, $op, $quote1, $delim, $quote2) = $matches;
        $this->record($op, 'OPERATOR');
        // Now, if $quote1 is '\', then $quote2 is empty. If quote2 is empty
        // but quote1 is not '\', this is not a heredoc.
        if ($quote1 === '\\' && $quote2 === '') {
            $this->record($quote1 . $delim, 'DELIMITER');
        } elseif ($quote2 === '' && $quote1 !== '') {
            // this is the error case
            // shift to the end of the op and break
            $this->posShift(strlen($op));
            return;
        } else {
            $this->record($quote1 . $delim . $quote2, 'DELIMITER');
        }
        $this->posShift(strlen($group));
        // TODO. the quotes (matches[2] and matches[4]) are ignored for now, but
        // they mean something w.r.t interpolation.

        $this->heredoc = $delim;
        $this->addPattern('HEREDOC_NL', "/\n/");
        $this->overrides['HEREDOC_NL'] = array($this, 'heredocRealOverride');
    }

    // this override handles the actual heredoc text
    public function heredocRealOverride($matches)
    {
        $this->record($matches[0], null);
        $this->posShift(strlen($matches[0]));
        // don't need this anymore
        $this->removePattern('HEREDOC_NL');
        assert($this->heredoc !== null);
        $delim = preg_quote($this->heredoc);
        $substr = $this->scanUntil('/^' . $delim . '\\b/m');
        if ($substr !== null) {
            $this->record($substr, 'HEREDOC');
            $delim_ = $this->scan('/' . $delim . '/');
            assert($delim !== null);
            $this->record($delim_, 'DELIMITER');
        } else {
            $this->record($this->rest(), 'HEREDOC');
            $this->terminate();
        }
    }

    // halts highlighting on  __DATA__ and __END__
    public function termOverride($matches)
    {
        $this->record($matches[0], 'DELIMITER');
        $this->pos($this->pos() + strlen($matches[0]));
        $this->record($this->rest(), null);
        $this->terminate();
    }

    // pod cuts might be very long and trigger the backtrack limit, so
    // we do it the old fashioned way
    public function podCutOverride($matches)
    {
        $line = $this->scan('/^=.*/m');
        assert($line !== null);
        $term = '/^=cut$|\\z/m';
        $substr = $this->scanUntil($term);
        assert($substr !== null);
        $end = $this->scan($term);
        assert($end !== null);
        $this->record($line . $substr . $end, 'DOCCOMMENT');
    }

    public function init()
    {
        $this->addPattern('COMMENT', '/#.*/');

        // pod/cut documentation
        $this->addPattern('podcut', '/^=[a-zA-Z_]/m');
        $this->overrides['podcut'] = array($this, 'podCutOverride');

        // variables
        $this->addPattern('VARIABLE', '/[\\$%@][a-z_]\w*/i');
        // special variables http://www.kichwa.com/quik_ref/spec_variables.html
        $this->addPattern('VARIABLE', '/\\$[\|%=\-~^\d&`\'+_\.\/\\\\,"#\\$\\?\\*O\\[\\];!@]/');

        // `backticks` (shell cmd)
        $this->addPattern('CMD', '/`(?: [^`\\\\]++ | \\\\ . )*+ (?:`|$)/x');
        // straight strings
        $this->addPattern('STRING', TokenPresets::$DOUBLE_STR);
        $this->addPattern('STRING', TokenPresets::$SINGLE_STR);
        // terminators
        $this->addPattern('TERM', '/__(?:DATA|END)__/');
        // heredoc (overriden)
        $this->addPattern('HEREDOC', '/(<<)([\'"`\\\\]?)([a-zA-Z_]\w*)(\\2?)/');
        // operators, slash is a special case and is overridden
        $this->addPattern('OPERATOR', '/[!%^&*\-=+;:|,\\.?<>~\\\\]+/');
        $this->addPattern('SLASH', '%//?%');
        // we care about 'openers' for regex-vs-division disambiguatation
        $this->addPattern('OPENER', '%[\[\{\(]+%x');

        $this->addPattern('NUMERIC', TokenPresets::$NUM_HEX);
        $this->addPattern('NUMERIC', TokenPresets::$NUM_REAL);

        // quote-like operators. we override these.
        // I got these out of the old luminous tree, I don't know how accurate
        // or complete they are.
        // According to psh, delimiters can be escaped?
        $this->addPattern('DELIMETERS', '/(q[rqxw]?|m|s|tr|y)([\s]*)(\\\\?[^a-zA-Z0-9\s])/');
        $this->addPattern('IDENT', '/[a-zA-Z_]\w*/');

        $this->overrides['DELIMETERS'] = array($this, 'strOverride');
        $this->overrides['SLASH'] = array($this, 'slashOverride');
        $this->overrides['HEREDOC'] = array($this, 'heredocOverride');
        $this->overrides['TERM'] = array($this, 'termOverride');

        // map cmd to a 'function' and get rid of openers
        $this->ruleTagMap = array(
            'CMD' => 'FUNCTION',
            'OPENER' => null,
        );

        // this sort of borks with the strange regex delimiters
        $this->removeFilter('pcre');

        /************************************************************************/
        // data definition follows.

        // https://www.physiol.ox.ac.uk/Computing/Online_Documentation/Perl-5.8.6/index-functions-by-cat.html
        $this->addIdentifierMapping('KEYWORD', array(
            'bless',
            'caller',
            'continue',
            'dbmclose',
            'dbmopen',
            'defined',
            'delete',
            'die',
            'do',
            'dump',
            'else',
            'elsif',
            'eval',
            'exit',
            'for',
            'foreach',
            'goto',
            'import',
            'if',
            'last',
            'local',
            'my',
            'next',
            'no',
            'our',
            'package',
            'prototype',
            'redo',
            'ref',
            'reset',
            'return',
            'require',
            'scalar',
            'sub',
            'tie',
            'tied',
            'undef',
            'utie',
            'unless',
            'use',
            'wantarray',
            'while'
        ));
        $this->addIdentifierMapping('OPERATOR', array('lt', 'gt', 'le', 'ge', 'eq', 'ne', 'cmp', 'and', 'or', 'xor'));

        $this->addIdentifierMapping('FUNCTION', array(
            'chomp',
            'chop',
            'chr',
            'crypt',
            'hex',
            'index',
            'lc',
            'lcfirst',
            'length',
            'oct',
            'ord',
            'pack',
            'reverse',
            'rindex',
            'sprintf',
            'substr',
            'uc',
            'ucfirst',
            'pos',
            'quotemeta',
            'split',
            'study',
            'abs',
            'atan2',
            'cos',
            'exp',
            'hex',
            'int',
            'log',
            'oct',
            'rand',
            'sin',
            'sqrt',
            'srand',
            'pop',
            'push',
            'shift',
            'splice',
            'unshift',
            'grep',
            'join',
            'map',
            'reverse',
            'sort',
            'unpack',
            'delete',
            'each',
            'exists',
            'keys',
            'values',
            'binmode',
            'close',
            'closedir',
            'dbmclose',
            'dbmopen',
            'die',
            'eof',
            'fileno',
            'flock',
            'format',
            'getc',
            'print',
            'printf',
            'read',
            'readdir',
            'readline',
            'rewinddir',
            'seek',
            'seekdir',
            'select',
            'syscall',
            'sysread',
            'sysseek',
            'syswrite',
            'tell',
            'telldir',
            'truncate',
            'warn',
            'write',
            'pack',
            'read',
            'syscall',
            'sysread',
            'sysseek',
            'syswrite',
            'unpack',
            'vec',
            'chdir',
            'chmod',
            'chown',
            'chroot',
            'fcntl',
            'glob',
            'ioctl',
            'link',
            'lstat',
            'mkdir',
            'open',
            'opendir',
            'readlink',
            'rename',
            'rmdir',
            'stat',
            'symlink',
            'sysopen',
            'umask',
            'unlink',
            'utime',
            'alarm',
            'exec',
            'fork',
            'getpgrp',
            'getppid',
            'getpriority',
            'kill',
            'pipe',
            'qx/STRING/',
            'readpipe',
            'setpgrp',
            'setpriority',
            'sleep',
            'system',
            'times',
            'wait',
            'waitpid',
            'accept',
            'bind',
            'connect',
            'getpeername',
            'getsockname',
            'getsockopt',
            'listen',
            'recv',
            'send',
            'setsockopt',
            'shutdown',
            'socket',
            'socketpair',
            'msgctl',
            'msgget',
            'msgrcv',
            'msgsnd',
            'semctl',
            'semget',
            'semop',
            'shmctl',
            'shmget',
            'shmread',
            'shmwrite',
            'endgrent',
            'endhostent',
            'endnetent',
            'endpwent',
            'getgrent',
            'getgrgid',
            'getgrnam',
            'getlogin',
            'getpwent',
            'getpwnam',
            'getpwuid',
            'setgrent',
            'setpwent',
            'endprotoent',
            'endservent',
            'gethostbyaddr',
            'gethostbyname',
            'gethostent',
            'getnetbyaddr',
            'getnetbyname',
            'getnetent',
            'getprotobyname',
            'getprotobynumber',
            'getprotoent',
            'getservbyname',
            'getservbyport',
            'getservent',
            'sethostent',
            'setnetent',
            'setprotoent',
            'setservent',
            'gmtime',
            'localtime',
            'time',
            'times'
        ));
    }

    public static function guessLanguage($src, $info)
    {
        // check the shebang
        if (preg_match('/^#!.*\\bperl\\b/', $src)) {
            return 1.0;
        }
        $p = 0;
        if (preg_match('/\\$[a-zA-Z_]+/', $src)) {
            $p += 0.02;
        }
        if (preg_match('/@[a-zA-Z_]+/', $src)) {
            $p += 0.02;
        }
        if (preg_match('/%[a-zA-Z_]+/', $src)) {
            $p += 0.02;
        }
        if (preg_match('/\\bsub\s+\w+\s*\\{/', $src)) {
            $p += 0.1;
        }
        if (preg_match('/\\bmy\s+[$@%]/', $src)) {
            $p += 0.05;
        }
        // $x =~ s/
        if (preg_match('/\\$[a-zA-Z_]\w*\s+=~\s+s\W/', $src)) {
            $p += 0.15;
        }
        return $p;
    }
}
