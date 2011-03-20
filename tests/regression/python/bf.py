#!/usr/bin/env python

import sys

def bf(code):
  input_ptr = 0
  tape_ptr = 0
  tape = [0] * 30000

  while input_ptr < len(code):
    char = code[input_ptr]
    if char == '>' : tape_ptr += 1
    elif char == '<' : tape_ptr -= 1
    elif char == '+' : tape[tape_ptr] += 1
    elif char == '-' : tape[tape_ptr] -= 1
    elif char == '.' : sys.stdout.write(chr(tape[tape_ptr]))
    elif char == ',' : 
      try: tape[tape_ptr] = ord(raw_input()[0])
      except: continue
    elif char == '[' and not tape[tape_ptr] or char == ']' and tape[tape_ptr]:
      stack = 0
      while 0 <= input_ptr < len(code):
        input_ptr += 1 if char == '[' else -1
        if code[input_ptr] == char: stack+=1
        elif code[input_ptr] == '[' or code[input_ptr] == ']':
          if stack: stack -= 1
          else: 
            break
      if stack: raise Exception("Mismatched brackets")

    input_ptr += 1

if __name__ == '__main__':
  code = ""
  if len(sys.argv) < 2 or sys.argv[1] == '-':
    code = sys.stdin.read()
  else: code = sys.argv[1]
  bf(code)

