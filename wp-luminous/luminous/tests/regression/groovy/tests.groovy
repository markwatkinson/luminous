 
def s = /.*foo.*/
def dirname  = /^.*\//
def basename = /[Strings and GString^\/]+$/

assert 'ab' == 'a' + 'b'    // OK, no slashy string
assert 'a' + 'b' == /ab/    // OK, slashy string on RHS
assert (/ab/ == 'a' + 'b')  // brackets currently required if slashy string is on LHS

println "how deep is deep? ${{-> "deeper and ${{-> "deepest"}}"}}" // for demonstrating it is a nested usage only
