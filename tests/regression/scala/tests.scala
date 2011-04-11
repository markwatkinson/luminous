 
// this is a comment
this isnt.
/* this is a comment */
this isnt
/* this is a /* nested */ comment */
this isnt
/* this is also a
  // /*
  nested */
  /*  comment */
  */
this isnt


// float literals
0;
21;
0xFFFFFFFF;
0777L;

0.0;
1e30f;
3.14159f;
1.0e-100;
.1

true false null



c = 'a';
c = '\u0041'
c = '\n'
c = '\t'
"hello,\nworld!";
"this string contains a \" character"

"hello
nostring

"""multiline
string"""

""" multiline \" string """;
""" multiline " string """;
""" multiline "" string """;

var xml = <tag1> </tag1>;
var xml = function(<tag1> </tag1>);
var xml = {<tag1> </tag1>};
var xml = <tag1>
            <tag2>
              <tag3/>
            </tag2>
            <tag4>
              <tag5> </tag5>
            </tag4>
          </tag1>;
          //scala!;

var something = 5 < 3;
var tag1 = 32;
// not xml
something = 5<tag1> </tag>;

// this is XML according to the spec but it's presumably illegal
something = 5 <tag1> </tag1>

