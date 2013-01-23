regex1 = s/PATTERN/REPLACEMENT/egimosx;
$a
regex2 = tr/SEARCHLIST/REPLACEMENTLIST/cds;
%b
regex3 = y/SEARCHLIST/REPLACEMENTLIST/cds;
@c

regex4 = s{something}{somethingelse}x;

$string =~ m/sought_text/;    # m before the first slash is the "match" operator.
$string =~ m/whatever(sought_text)whatever2/;
$soughtText = $1;
$string =~ s/originaltext/newtext/;    # s before first slash is "substitute" operator.

(/[^AEIOUYaeiouy]/x)
next if /^\s*$/;

$line =~ s/((?:(?:^|\s)\w|qu)\')/a/ogi;

x = qw(string1 str_ing2 str'ing3 str"ing4);
x =~ s<123>{345}
x =~ s<123>/345/
x =~ s/123/345/
x =~ s?123?[345]?

# Not regexes
$x / $y;
4/5
//
12/15/15
a/b/c
$x = 1/15

#are regexes, although it's probably not defined/syntatically legal perl
$x = /15/15
