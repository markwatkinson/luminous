Luminous Changelog since 0.6.0
==============================

##master (as of 27/04/11):

- General:
    - 'plain' is used as a default scanner in the User API (previously an
      exception was thrown if a scanner was unknown)
    - Fix bug where the User API's 'relative root' would collapse double slashes
      in protocols (i.e. http:// => http:/)
    - User API now throws Exception if the highlighting function is called with
      non-string arguments
    - Some .htaccesses are provided to prevent search engines/bots crawling the
      Luminous directories (many of the files aren't supposed to be executed
      individually and will therefore populate error logs should a bot
      discover a directory)
    - Minor tweaks to the geonyx theme
    - Obsolete JavaScript has been removed and replaced with a much less
      intrusive behaviour of double click the line numbers to hide them,
      js inclusion is disabled by default by User API.

- Language fixes:
    - Pod/cut style comments in Perl should now work all the time

- New Stuff:
    - Go language support

-  Internal/Development:
    - Unit test of stateful scanner much more useful
    - Syntax test for scanners (syntax.php)
    - Stateful scanner throws an exception if the initial state is popped
      (downgraded from an assertion)
    - Stateful scanner safety check no longer requires that an iteration
      advances the pointer as long as the state is changed
    - Coding standards applied in htmlformatter.php
    - All scanning classes have complete API documentation
    - Paste test (interface.php) works properly with Unicode

## v0.6.0 (16/04/11):
- 0.6.0 is a near-total rewrite with a lot of changes. The hosting has 
  moved from Google Code to GitHub and most code is freshly written.
- Changelog is restarted
