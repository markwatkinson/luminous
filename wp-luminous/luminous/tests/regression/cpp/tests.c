#include <something> /*
  comment
  */
1
#if 0
don't compile this code
#endif
1
#if           0         
don't compile this code either
#endif
1
#if 0
  COMMENT
  #if 0
  COMMENT
  #endif
  COMMENT
#endif 
1


int main() {

  char*lie = "i am a unicorn";
  
  "this string is unterminated
  int x;
  "this string is not unterminated\
  because we escaped the nl";

  // chars
  ' '
  '\n'
  '\\'
  '\xCA'
  '\x1212' // not a char
  '' //nope
  float x = 0.0f;
  float y = 1.f
  float z = 1.000f;
  unsigned long int a = 12UL;
  unsigned long int a = 12LU;
  100e100f
  0xfffffff0;
  0xface
  0xFACE
  0Xe110
  0XHELLO
  
}