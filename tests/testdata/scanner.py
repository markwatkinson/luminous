#!/usr/bin/env python

"""Copyright (c) 2011, Mark Watkinson
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of Scanner nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL MARK WATKINSON BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE."""


import re

class Scanner(object):  
  """A simple class to aid in lexical analysis of a string.
  Styled after, but not entirely the same as, Ruby's StringScanner class.
  
  Basic philosophy is simple: Scanner traverses a string left to right,
  consuming the string as it goes. The current position is the string pointer
  Scanner.pos. At each iteration, the caller uses the scanning methods to 
  determine what the current piece of string actually is.
  
  Scanning methods:
    With the exception of get and peek, all scanning methods take a pattern and
    (optionally) flags (e.g re.X). The patterns are assumed to be either 
    strings or compiled regular expression objects (i.e. the result of 
    re.compile, or equivalent). If a pattern is not a string but does not 
    implement match or search (whichever is being used), a ValueError is raised.
    String patterns are compiled and cached internally.
    
    The check, scan and skip methods all try to match *at* the current scan
    pointer. check_to, scan_to and skip_to all try to find a match somewhere
    beyond the scan pointer and jump *to* that position. check_until, scan_until,
    and skip_until are like *_to, but also consume the match (so the jump to
    the *end* of that position)
    
    Lookahead:
      check()
      check_to()
      check_until()
      peek()
      
    Consume:
      get()
      scan()
      scan_to()
      scan_until()      
      skip()
      skip_to()
      skip_until()
      skip_bytes()      (convenience wrapper)
      skip_lines()      (convenience wrapper)
      skip_whitespace() (convenience wrapper)
      
    Note that scan* and check* both return either a string, in the case of a
    match, or None, in the case of no match. If the match exists but is zero
    length, the empty string is returned. Be careful handling this as both 
    None and the empty string evaluate to False, but mean very different things.
    
    peek and get also return the empty string when the end of the stream is 
    reached.
    
      
  Most recent match data:
    
    matched() -- True/False - was the most recent match a success?
    
    The following methods all throw Exception if not matched()
    
    match() -- matched string
    match_len() -- matched string length
    match_pos() -- offset of match
    
    Wrappers around re.*
    match_info()  -- the re.MatchObject
    match_group()
    match_groups()
    match_groupdict()
    
    pre_match() -- string preceding the match
    post_match() -- string following the match
    
  Misc:
    pos -- get/set current scan pointer position
    
    bol() -- beginning of line? (DOS/Unix/Mac aware)
    eol() -- end of line? (DOS/Unix/Mac aware)
    eos() -- end of string?
    rest() -- remaining (unconsumed) string
    rest_len() -- length of remaining string
    unscan() -- revert to previous state
    
  Setup:
    string -- get/set current source string
    
    reset() -- reset the scanner ready to start again
    terminate() -- trigger premature finish  
  """
  
  def __init__(self, src=None):
    """Constructor 
    
    Arguments:
    src -- a string to scan. This can be set later by string()
    
    """
    
    self.__src = None
    self.__index = None    
    self.__src_len = 0
    
    # Cached regex objects.
    self.__regex_cache = {}
    
    # A list of dicts
    # Each dict has keys: 'index' (position of match), 'pos' (pos pointer at 
    # time the match was run), 'text' (text of the match), 'len' (length of the
    # text of the match), and 'matchinfo', as returned by re.search or re.match
    self.__match_history = None
    
    if src is not None:
      self.string = src
      
    # a generator object corresponding to __rest(), to be used by rest()
    self.__rest_gen = None
    
    self.reset()
    
    
  @property
  def pos(self):
    """The current string pointer position."""
    return self.__index
    
  @pos.setter
  def pos(self, new_pos):
    """Set the string pointer position.
    
    Arguments:
    new_pos -- The new offset into the string
    
    Throw Exception if new_pos is out of range
    
    """
    p_ = max(min(new_pos, self.__src_len), 0)
    if new_pos != p_: raise Exception('pos set to out of range value')
    self.__index = p_ 
    
  def eos(self):
    """Return True iff we are at the end of the string, else False."""
    return self.__index >= self.__src_len
    
  def reset(self):
    """Reset the scanner's state including string pointer and match history."""
    self.pos = 0
    self.__match_history = []
    self.__rest_gen = self.__rest()

    
  @property
  def string(self):
    """The source string"""
    return self.__src
    
  @string.setter 
  def string(self, s):
    """Set the source string"""
    if s is None:  raise Exception('Scanner.string called with None')   
    self.__src = s
    self.__src_len = len(s)
    self.reset()
    return self.__src
      
  def terminate(self):
    """Set the string pointer to the end of the input and clear the match 
    history."""
    self.reset()
    self.pos = self.__src_len
    
  def bol(self):
    """Return whether or not the scan pointer is immediately after a newline
    character (DOS/Unix/Mac aware), or at the start of the string. """
    return self.__index == 0 or self.__src[self.__index-1] == '\n' \
      or self.__src[self.__index-1] == '\r' and self.peek() != '\n' 
      
  def eol(self):
    """Return whether or not the scan pointer is immediately before a newline 
    character (DOS/Unix/Mac aware) or at the end of the string."""
    if self.eos(): return True
    p = self.peek(2)
    if p == '\r\n' or p.startswith('\r'): return True    
    if p.startswith('\n'):
      if self.__index == 0 or self.__src[self.__index-1] != '\r': return True
    return False
    
  def location(self):
    """ Return as a tuple: (linenumber, bytenumber) """
    
    if self.string is None: raise Exception('No string set')
    
    # maybe should cache the line endings
    substr = self.string[:self.__index]
    line_endings = '\r\n' if substr.count('\r\n') else \
      '\n' if substr.count('\n') else '\r'    
    lines = substr.count(line_endings) + 1
    char = self.__index - substr.rfind(line_endings) if lines else self.__index
    return (lines, char)
    
  def __match(self, strict=True):
    """ Return the most recent match data.
    Raise Exception if no matches are known.
    
    This method is used by most of the matched_*, and the exception should 
    be allowed to propagate back to the caller
    """
    if self.__match_history:
      return self.__match_history[-1]
    else: 
      if strict: raise Exception('No matches recorded')
      return None
   
  
  def matched(self):
    """Return True if the last match was successful, else False.
    Raise Exception if no match attempts have been recorded."""
    return self.__match()['matchinfo'] is not None
    
  def __matched_exception(self):
    """raise an exception if the most recent match failed"""
    if not self.matched():
      raise Exception('Cannot access match information: most recent match failed')
    
    
  def match(self):
    """Return the last matching string
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed
    """
    self.__matched_exception()
    return self.__match()['text']
    
  def match_len(self):
    """Return the length of the last matching string
    This is equivalent to len(scanner.match()).
    
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed    
    """
    self.__matched_exception()
    
    return self.__match()['len']
    
  def match_pos(self):
    """Return the offset into the string of the last match
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed    
    """
    self.__matched_exception()
    return self.__match()['index']
    

  def __match_info(self, strict=True):
    m = self.__match()['matchinfo']
    if m is None and strict:
      self.__matched_exception()
    return m
  
  def match_info(self):
    """Return the most recent match's MatchObject. This is what's returned by
    the re module. Use this if the other methods here don't expose what you 
    need.
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed
    
    """
    return self.__match_info(True)
    
  def match_groups(self, default=None):
    """Return the most recent's match's groups, this is a wrapper to 
    re.MatchObject.groups()
    
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed
    """    
    return self.__match_info().groups(default)
  
  def match_groupdict(self, default=None):
    """Return a dict containing group_name => match. This is a wrapper to
    re.MatchObject.groupdict() and as such it only works for named groups
    
    Raise Exception if no match attempts have been recorded.
    Raise Exception if most recent match failed
    """
    return self.__match_info().groupdict(default)
    

    
  def match_group(self, *args):
    """Return the contents of the given group in the most recent match.
    This is a wrapper to re.MatchObject.group()
    raise IndexError if the match exists but the group does not
    raise Exception if no match attempts have been recorded
    raise Exception if most recent match failed
    """
    m = self.__match_info()
    if not args: args = (0,) # should this be a tuple or list?
    # throws IndexError, allow it to propagate
    return m.group(*args)
    
    
  def pre_match(self):
    """Return the string preceding the last match or None. This is equivalent 
    to:  scanner.string[:scanner.match_pos()]
    
    raise Exception if no match attempts have been recorded
    """    
    return self.__src[:self.match_pos()]
      
  def post_match(self):
    """Return the string following the last match or None. This is equivalent 
    to:  scanner.string[scanner.match_pos() + scanner.match_len() : ]
    
    raise Exception if no match attempts have been recorded
    """
    return self.__src[ self.match_pos() + self.match_len() : ]
  
  def __rest(self):
    """ Return the rest of the string """
  
    # a generator in here simulates static variables such that we aren't 
    # recalculating the substring on every call, just when pos changes    
    s = None
    last = None
    while True:
      pos = self.__index
      if last != pos:
        s = self.string[pos:]
        last = pos
      yield s
      
  def rest(self):
    """Return the string from the current pointer onwards, i.e. the segment of 
    string which has not yet been consumed."""
        
    # use next(), not .next() for py3k compat
    return next(self.__rest_gen)
    
  def rest_len(self):
    """Return the length of string remaining. 
    This is equivalent to len(rest())"""
    return len(self.rest())
    
  def unscan(self):
    """Revert the scanner's state to that of the previous match. Only one 
    previous state is remembered
    Throw Exception if there is no previous known state to restore"""
    if not self.__match_history:
      raise Exception('Cannot unscan, already at earliest point in history')
    m = self.__match_history.pop()
    self.__index = m['pos']
    
    
  def __check(self, pattern, flags, consume=False, log=True, 
    search_func='match', consume_match=True):
    """ Perform a match and return the matching substring or None 
    
    Arguments:
    pattern -- the regex pattern to look for (as string or compiled)
    flags -- the regex flags to use in the match, as defined in the re module
    consume -- whether or not to consume the matching string
    log -- whether or not to write to the __match_history
    search_func -- Either 'match' or 'search'. The former looks for matches 
    immediately at the beginning of the string pointer, the latter will look
    for matches anywhere after the string pointer.
    consume_match -- If consume is True, this sets that the full text of the 
      match should be consumed as well as what preceded it up until that match
    """
    
    if self.__src is None:
      raise Exception('Scanner called with no string set')
    
    regex = None
    compiled_ourselves = False
    # if the pattern is a string we need to compile it
    # if it's not a string we assume it's a compiled regular expression
    if isinstance(pattern, str):
      # cache the regex
      compiled_ourselves = True
      key = (pattern, flags)
      regex = None
      if key in self.__regex_cache:
        regex = self.__regex_cache[key]
      else:    
        regex = re.compile(pattern, flags)
        self.__regex_cache[key] = regex
    else:
      regex = pattern
      
    try:
      func = getattr(regex, search_func)
    except AttributeError:
      assert not compiled_ourselves
      raise ValueError("Object passed as 'pattern' to scan/check/skip does not implement a {0} method".format(search_func))
    
    m = func(self.__src, self.__index)
    
    substr = None
    substr_len = None
    match_pos = None
    
    if m:
      match_pos = self.__index
      substr = '' if m.start(0) == match_pos else self.__src[self.__index:m.start(0)]
      if consume_match: substr += m.group(0)
      substr_len = len(substr)
      
    if log:
      matched = {}
      matched['index'] = None if m is None else match_pos
      matched['text'] = substr
      matched['len'] = None if m is None else substr_len
      matched['pos'] = self.__index
      matched['matchinfo'] = m
      if self.__match_history:
        self.__match_history = [self.__match_history[-1], matched]
      else:
        self.__match_history = [matched]
      
    if consume and m:
      self.__index = match_pos + substr_len
      
    return substr
    
  def check(self, pattern, flags=0):
    """Return a match for the pattern (or None) at the scan pointer without 
    actually consuming the string
    If the pattern matched but was zero length, the empty string is returned
    If the pattern did not match, None is returned
    
    """
    return self.__check(pattern, flags)
    
  def check_to(self, pattern, flags=0):
    """Return all text up until the beginning of the first match for the pattern 
    after the scan pointer without consuming the string
    If the pattern matched but was zero length, the empty string is returned
    If the pattern did not match, None is returned
    """
    return self.__check(pattern, flags, search_func='search', consume_match=False)
    
  def check_until(self, pattern, flags=0):
    """Return all text up until the end of the first match for the pattern 
    after the scan pointer without consuming the string
    If the pattern matched but was zero length, the empty string is returned
    If the pattern did not match, None is returned
    """
    return self.__check(pattern, flags, consume=False, search_func='search')
    
  def scan(self, pattern, flags=0):
    """Return a match for the pattern at the scan pointer and consume the 
    string.
    Return None if not match is found"""
    return self.__check(pattern, flags, consume=True)
    
  def scan_to(self, pattern, flags=0):
    """Return all text up until the beginning of the first match for the pattern
    after the scan pointer.
    The pattern is not included in the match.
    The scan pointer will be moved such that it immediately precedes the pattern
    Return None if no match is found"""
    return self.__check(pattern, flags, consume=True, consume_match=False,
      search_func='search')
    
  def scan_until(self, pattern, flags=0):
    """Return the first match for the pattern after the scan pointer and 
    consumes the string up until the end of the match.    
    Return None if no match is found"""    
    return self.__check(pattern, flags, consume=True, search_func='search')
    
  def skip(self, pattern, flags=0):
    """Scan ahead over the given pattern and return how many characters were
    consumed, or None.
    Similar to scan, but does not return the string or record the match """
    m = self.__check(pattern, flags, log=False, consume=True)
    return None if m is None else len(m)
    
  def skip_to(self, pattern, flags=0):
    """Scan ahead until the beginning of first occurrance of the given pattern
    and return how many characters were skipped, or None if the match
    failed
    The match is not recorded.
    """    
    start = self.__index
    m = self.__check(pattern, flags, log=False, consume=True, consume_match=False,
      search_func='search')
    return None if m is None else self.__index - start
    
  def skip_until(self, pattern, flags=0):
    """Scan ahead until the end of first occurrance of the given pattern and 
    return how many characters were consumed, or None if the match failed
    The match is not recorded
    """
    start = self.__index
    m = self.__check(pattern, flags, log=False, consume=True, search_func='search')
    return None if m is None else self.__index - start
    
  def skip_lines(self, n=1):
    """ Skip the given number of lines and return the number of lines consumed """
    for i in xrange(n):
      if not self.skip_until('.^', re.M|re.S): return i
    return i+1
    
  def skip_bytes(self, n):
    """Skip the given number of bytes and return the number of bytes consumed"""
    return len(self.get(n))
    
  def skip_whitespace(self, n=None, multiline=True):
    """Skip over whitespace characters and return the number of characters 
    consumed
    
    Arguments: 
    n -- maximum number of characters to cosume (default None)
    multiline -- whether or not to consume newline characters (default True)
    """
    chars = r'\s' if multiline else '[\b\f\t ]'
    chars += ('+' if n is None else '{{,{0}}}'.format(n))
    skipped = self.skip(chars)
    return 0 if skipped is None else skipped
    
  def exists(self, pattern, flags=0):
    """ Return True if the given pattern matches ANYWHERE after the scan 
    pointer. Don't advance the scan pointer or record the match"""
    return self.__check(pattern, flags, consume=False, log=False,
      search_func='search') is not None
    
  def peek(self, length=1):
    """Return the given number of characters from the current string pointer
    without consuming them.
    If we reach the end of the stream, the empty string is returned"""
    return self.__src[self.__index: self.__index + length]
    
  def get(self, length=1):
    """Return the given number of characters from the current string pointer 
    and consume them
    If we reach the end of the stream, the empty string is returned
    """
    s = self.peek(length)
    self.__index += len(s)
    return s
