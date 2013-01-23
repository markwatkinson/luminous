#!/usr/bin/ruby
#

# Ruby interpolation is complicated


x = "#{'}'}"
x = "#{ %q{ abc } }"
x = "#{x.gsub /"/, '""'}" #out of string now.






