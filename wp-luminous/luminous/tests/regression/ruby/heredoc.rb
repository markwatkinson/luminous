#Ruby supports multiline strings by providing two types of here doc syntax. The first syntax uses and additional dash, but allows you to indent the "end of here doc" delimiter ('eos' in the example).
  <<-eos
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor 
    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
    exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute 
    irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla 
    pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia
    deserunt mollit anim id est laborum.
    ' "
  eos
#Another here doc syntax doesn't require you to use the dash, but it does require that the "end of here doc" delimiter is in column 1 (or there are no spaces that precede it).
  <<eos
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor 
    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
    exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute 
    irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla 
    pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia
    deserunt mollit anim id est laborum.
    ' "
eos
#Both options support the following syntax when passed as a parameter.
  Content.new(:value => <<eos)
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor 
    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
    exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute 
    irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla 
    pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia
    ' "
eos

 Content.new(:value => <<eos
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor 
    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
    exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute 
    irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla 
    pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia
    ' "
eos
)
#As an alternative, you could also pass the parameter using quotes.
  Content.new(:value => "Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor 
    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud 
    exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute 
    irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla 
    pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia")
             
             
             
             
             
             
print <<EOF
The price is #{$Price}.
EOF

print <<"EOF";          # same as above
The price is #{$Price}.
EOF

print <<`EOC`           # execute commands
echo hi there
echo lo there
EOC

# this should go until the indented one
print <<"foo", <<-"bar"  # you can stack them
I said foo.
foo
I said bar.
    bar

# Kate gets this one wrong
myfunc(<<"THIS", 23, <<'THAT')
Here's a line
or two.
THIS
and here's another.
THAT

if need_define_foo
eval <<-EOS         # delimiters can be indented
def foo
print "foo\n"
end
EOS
end
