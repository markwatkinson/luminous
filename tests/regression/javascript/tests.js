// contrived, not real
//

var x = /xyz/g;
var x /xyz/ ; // snytax error, but technically division with a missing operand.

g = 12;
// vim gets this wrong
4/2/g       


"this is an unterminated string
eek
"this is not \
    an unterminated string";
'same story
here
'xyz\
    abc';


[/123/, /regex/, 
  // hello )
  /345/];

var x = <iam>an XML literal </iam>;
var x = <iamanxmlliteral/>
// we don't validate their XML, we just keep track of how many open tags there
// are. From our point of view, this is complete
var x = <i am > <an> </xml> </literal>;
var x = <i am> <an/> </xml> literal
// not XML
var x = 1<i> am not xml;

{ 'key': /regex/,
  'another':
    // xyz
    /regex/,
  'key2': <xml/>,
  'another': /*  this shouldn't confuse    */ // us
    <xml>,
}


