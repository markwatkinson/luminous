#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
    huffman.py: Calculates a Huffman coding scheme for a given code
    
    Copyright (C) 2007 Mark Watkinson

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
"""    

""" 
  Example usage:
  ./huffman.py a 15 b 7 c 6 d 6 e 5
  a : 0
  e : 100
  c : 101
  d : 110
  b : 111
  average 2.231 bits per symbol
"""




import sys


# builds the binary tree. 
# symbols_to_freqs is a dictionary mapping symbol names to relative frequencies,
# which should be normalsed such that they all add up to 1.
# Returns the tree's root node. Each node is:
# (
#  node's symbol ( | None if is a parent), 
#  total frequency of children,
#  childnode1 | None,
#  childnode2 | None,
# )
# where childnodes are the same structure.
def build_tree(symbols_to_freqs):
  
  #alphabet = list of: (symbol, relative freq)
  alphabet = [ (symbol, symbols_to_freqs[symbol]) for symbol in symbols_to_freqs.keys() ]
  
  sign = lambda x: -1 if x<0 else (1 if x>0 else 0)
  alphabet.sort( lambda a, b: sign(a[1] - b[1]))
  alphabet.reverse()
  # tree = list of (symbol, relative freq, child1, child2
  tree = [ (symbol, freq, None, None) for symbol, freq in alphabet]

  while len(tree) > 1:
    # pop the two lowest probability nodes 
    # and amalgamate them to form a parent.
    node1 = tree.pop()
    node2 = tree.pop()
    
    sym1, freq1, dummy, dummy2 = node1
    sym2, freq2, dummy3, dummy4 = node2
    
    #node 3 is the parent.
    freq3 = freq1 + freq2
    
    node3 = (None, freq3, node1, node2)
    # now put it back on the tree.
    tree.append(node3)  
    tree.sort(lambda a, b: sign(a[1] - b[1]))
    tree.reverse()  

  return tree[0]


# recurses the tree to build a huffman code
# returns code as a list of (symbol, binary string)
def build_symbols(node, sym_string = ""):
  c, f, n1, n2 = node
  # base case
  code = []
  if n1 is None and n2 is None:
    code +=  [(c, sym_string)] 
  
  if n1 is not None:
     code += build_symbols(n1, sym_string + "0")
  
  if n2 is not None:
    code += build_symbols(n2, sym_string + "1")
    
  return code
    
def main():
  if "--help" in sys.argv or len(sys.argv) == 1:
    print "Usage:", sys.argv[0],  "symbol_1 freq_1 symbol_2 freq_1 ... symbol_n freq_n"
    sys.exit()   
  
  # Parse arguments
  symbols = sys.argv[1::2]  
  freqs = [float(x) for x in sys.argv[2::2]]
  # convert freqs to relative freqs
  total = sum(freqs)
  freqs = [x/total for x in freqs]
  
  # set up the symbol => frequency lookup dictionary
  symbols_to_freqs = {}
  for sym, freq in zip(symbols, freqs):
    symbols_to_freqs[sym] = freq
    
  # build the tree
  tree = build_tree(symbols_to_freqs)
  # recurse to build a huffman code
  code = build_symbols(tree)    
  
  #sort by bit length
  code.sort( lambda a,b: len(a[1]) - len(b[1]) )
  #calculate average symbol length
  avg_bits = sum( [len(binary_code)*symbols_to_freqs[symbol] for symbol, binary_code in code] )

  print "\n".join( [symbol + " : " + binary_code for symbol, binary_code in code] )
  print "average", round(avg_bits, 3) , "bits per symbol"

  
if __name__ == "__main__":
  main()