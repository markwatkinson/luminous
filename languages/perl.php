<?php

/*
 * Like ruby, I think it's impossible to fully tokenize Perl without
 * executing some of the code to disambiguate some symbols. As such, we're
 * going to settle for 'probably right' rather than 'definitely right'.
 * 
 * TODO: I think this is mostly complete but it needs interpolation
 * highlighting in strings and heredoc, and a regex highlighting filter,
 * probably a stream filter
 */

class LuminousPerlScanner extends LuminousSimpleScanner {


  // helper function:
  // consumes a string until the given delimiter (which may be balanced).
  // will handle nested balanced delimiters.
  // this is used as the general case for perl quote-operators like:
  //    q/somestring/      q"somestring", q@somestring@, q[some[]string]
  // it can be called twice for s/someregex/somereplacement/
  // expects the initial opening delim to already have been consumed
  function consume_string($delimiter, $type) {
    $close = LuminousUtils::balance_delimiter($delimiter);

    $balanced = $close !== $delimiter;
    $patterns = array( '/(?<!\\\\)((?:\\\\\\\\)*)('
      . preg_quote($close, '/') . ')/');
    
    if ($balanced) {
      $patterns[] = '/(?<!\\\\)((?:\\\\\\\\)*)('
      . preg_quote($delimiter, '/') . ')/';
    }

    $stack = 1; // we're already inside the string
    $start = $this->pos();
    while(1) {
      $next = $this->get_next($patterns);
      if ($next[0] === -1) {
        $this->record(substr($this->string(), $start), $type);
        $this->terminate();
        break;
      }
      elseif($balanced && $next[1][2] === $delimiter) $stack++;
      elseif($next[1][2] === $close)  $stack--; 
      else assert(0);
      $this->pos($next[0] + strlen($next[1][0]));
      
      if ($stack <= 0) {
        $this->record(substr($this->string(),
          $start, $next[0] + strlen($next[1][1]) - $start),
          $type);
        $this->record($next[1][2], 'DELIMITER');
        break;
      }
    }
  }


  // Helper function: guesses whether or not a slash is a regex delimiter
  // by looking behind in the token stream.
  function is_delimiter() {
    for($i = count($this->tokens) - 1; $i >= 0; $i--) {
      $t = $this->tokens[$i];
      if ($t[0] === null || $t[0] === 'COMMENT') continue;
      elseif ($t[0] === 'OPENER' || $t[0] === 'OPERATOR') return true;
      elseif ($t[0] === 'IDENT') {
        switch($t[1]) {
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
  function slash_override($matches) {
    $this->pos( $this->pos() + strlen($matches[0]) );
    // this can catch '//', which I THINK is an operator but I could be wrong.
    if (strlen($matches[0]) === 2 || !$this->is_delimiter()) {
      $this->record($matches[0], 'OPERATOR');
    } else {
      $this->record($matches[0], 'DELIMITER');
      $this->consume_string($matches[0], 'REGEX');
      if ($this->scan('/[cgimosx]+/')) {
        $this->record($this->match(), 'KEYWORD');
      }      
    }
  }


  // override function for 'quote-like operators'
  // e.g.  m"hello", m'hello', m/hello/, m(hello), m(he()l()o())
  function str_override($matches) {

    $this->pos( $this->pos() + strlen($matches[0]) );

    $this->record($matches[0], 'DELIMITER');
    
    $f = $matches[1];
    
    $type = ($f === 'm' || $f === 'qr' || $f === 's' || $f === 'tr'
      || $f === 'y')? 'REGEX' : 'STRING';

    $this->consume_string($matches[3], $type);
    if ($f === 's' || $f === 'tr' || $f === 'y') {
      // s/tr/y take two strings, e.g. s/something/somethingelse/, so we
      // have to consume the next delimiter (if it exists) and consume the
      // string, again.
  
      // if delims were balanced, there's a new delimiter right here, e.g.
      // s[something][somethingelse]
      $this->skip_whitespace();
      $balanced = LuminousUtils::balance_delimiter($matches[3]) !== $matches[3];
      if ($balanced) {
        $delim2 = $this->scan('/[^a-zA-Z0-9]/');
        if ($delim2 !== null) {
          $this->record($delim2, 'DELIMITER');
          $this->consume_string($delim2, $type);
        }
      }
      // if they weren't balanced then the delimiter is the same, and has
      // already been consumed as the end-delim to the first pattern
      else {
        $this->consume_string($matches[3], $type);
      }
    }
    if ($type === 'REGEX' && $this->scan('/[cgimosx]+/')) {
      $this->record($this->match(), 'KEYWORD');
    }
  }

  // figures out heredocs
  function heredoc_override($matches) {
    $this->record($matches[1], 'OPERATOR');
    $this->record($matches[2] . $matches[3] . $matches[4], 'DELIMITER');
    $this->pos( $this->pos() + strlen($matches[0] ) );
    // TODO. the quotes (matches[2] and matches[4]) are ignored for now, but
    // they mean something w.r.t interpolation.
    $delim = preg_quote($matches[3], '/');
    $substr = $this->scan_until('/^' . $delim . '\\b/m');
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
  function term_override($matches) {
    $this->record($matches[0], 'DELIMITER');
    $this->pos( $this->pos() + strlen($matches[0]) );
    $this->record($this->rest(), null);
    $this->terminate();
  }
  
  
  function init() {
    
    $this->add_pattern('COMMENT', '/#.*/');
    
    // pod/cut documentation
    $this->add_pattern('DOCCOMMENT', 
    '/^=(?:pod|head\d*|over|item|back|begin|end|for|encoding)\\b .*? (^=cut$|\\z)/mxs');

    // variables
    $this->add_pattern('VARIABLE', '/[\\$%@][a-z_]\w*/i');
    // special variables http://www.kichwa.com/quik_ref/spec_variables.html
    $this->add_pattern('VARIABLE', '/\\$[\|%=\-~^\d&`\'+_\.\/\\\\,"#\\$\\?\\*O\\[\\];!@]/');

    // `backticks` (shell cmd)
    $this->add_pattern('CMD', '/`(?: [^`\\\\]+ | \\\\ . )(`|$)/x');
    // straight strings
    $this->add_pattern('STRING', LuminousTokenPresets::$DOUBLE_STR);
    $this->add_pattern('STRING', LuminousTokenPresets::$SINGLE_STR);
    // terminators
    $this->add_pattern('TERM', '/__(?:DATA|END)__/');
    // heredoc (overriden)
    $this->add_pattern('HEREDOC', '/(<<)([\'"`]?)([a-zA-Z_]\w*)(\\2)/');
    // operators, slash is a special case and is overridden
    $this->add_pattern('OPERATOR', '/[!%^&*\-=+;:|,\\.?<>~\\\\]+/');
    $this->add_pattern('SLASH', '%//?%');
    // we care about 'openers' for regex-vs-division disambiguatation
    $this->add_pattern('OPENER', '%[\[\{\(]+%x');
    
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_HEX);
    $this->add_pattern('NUMERIC', LuminousTokenPresets::$NUM_REAL);

    // quote-like operators. we override these.
    // I got these out of the old luminous tree, I don't know how accurate
    // or complete they are. 
    $this->add_pattern('DELIMETERS', '/(q[rqxw]?|m|s|tr|y)([\s]*)([^a-zA-Z0-9])/');
    $this->add_pattern('IDENT', '/[a-zA-Z_]\w*/');
    
    
    $this->overrides['DELIMETERS'] = array($this, 'str_override');
    $this->overrides['SLASH'] = array($this, 'slash_override');
    $this->overrides['HEREDOC'] = array($this, 'heredoc_override');
    $this->overrides['TERM'] = array($this, 'term_override');
    
    // map cmd to a 'function' and get rid of openers
    $this->rule_tag_map = array(
      'CMD' => 'FUNCTION',
      'OPENER' => null,
      );

    // this sort of borks with the strange regex delimiters
    $this->remove_filter('pcre');


    /************************************************************************/
    // data definition follows.

    // https://www.physiol.ox.ac.uk/Computing/Online_Documentation/Perl-5.8.6/index-functions-by-cat.html
    $this->add_identifier_mapping('KEYWORD', array( 'bless', 
      'caller', 'continue', 'dbmclose', 'dbmopen',
      'defined',
      'delete', 'die', 'do', 'dump', 'else', 'elsif',
      'eval', 'exit', 'for', 'foreach', 'goto', 'import', 'if', 'last', 'local',
      'my',
      'next', 'no',
      'our', 'package', 'prototype', 'redo', 'ref', 'reset',
      'return', 'require', 'scalar', 'sub', 'tie', 'tied',
      'undef',
      'utie', 
      'unless', 'use', 'wantarray', 'while'));
    $this->add_identifier_mapping('OPERATOR',  array('lt', 'gt', 'le',
      'ge', 'eq', 'ne', 'cmp', 'and', 'or', 'xor'));

    $this->add_identifier_mapping('FUNCTION', array(
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
'times'));


    
  }


}