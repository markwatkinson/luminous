#!/usr/bin/env python

import random 

import syllables

class PasswordGenerator:

  """
    m: a markov model instance
  """
  def __init__(self, m, lookbehind_chars=2):
    
    self.__m = m
    self.__lookbehind = lookbehind_chars
    self.punc =  '_-!.;,#?'
    self.char_number_mappings =  {'a': '4@&', 'e': '3', 'l': '1!', 's': '$5', 
      't': '+', 'o':'0*', 'i':'!'}
    
    # probability of case mangling any alphanumeric char
    self.case_mangling_p = 0.25
    
    # probability of converting any given char to a number 
    # (present in char_number_mappings)
    self.char_mangling_p = 0.5 
    
    self.mangle_chars = True
    self.mangle_punc = True
    self.mangle_case = True
    

  def generate_base_phrase(self, length):    
    alpha = 'abcdefghijklmnopqrstuvwxyz'
    string =  ' ' + random.choice(alpha)
    for i in xrange(0, length):

      j=len(string)
      
      lb = max(0, j-self.__lookbehind)
      inc = False
      while lb < j and not inc:
        c = self.__m.get_transition(string[lb:j], random.random(), 
          forbidden=' ', min_transitions=3)
        
        if c is not None and c.isspace() and random.random() > 0.5:
          continue
        
        if c is not None:          
          string+=c
          inc = True
          
        lb+=1
      # no transitions  
      if not inc:
        string += random.choice(alpha)
      if len(string) > length: break

    return string[1:]
  
  
  
  def __mangle_punctuation(self, phrase):
    
    s = syllables.split_syllables(phrase)
    s = [s_ + ('' if random.randint(0, 4) == 0 
      else random.choice(self.punc)) 
      for s_ in s]
    return ''.join(s)
      
  
  def __mangle_spaces_to_punc(self, phrase):    
    assert(self.punc.count(' ') == 0) # infinite loop condition
    while 1:
      s = phrase.replace(' ', random.choice(self.punc), 1)
      if s == phrase: break
      phrase = s
    return phrase
  
  def __mangle_char_to_num(self, phrase):    
    for i, c in enumerate(phrase):
      if self.char_number_mappings.get(c, None):
        if random.random() <  self.char_mangling_p:
          phrase = phrase[:i] + \
            random.choice(self.char_number_mappings.get(c)) + \
            phrase[i+1:]
    return phrase
  
  def __mangle_case(self, phrase):    
    for i, c in enumerate(phrase):      
      if c.isalpha:
        if c.isupper() and random.random() <  self.case_mangling_p:
          phrase = phrase[:i] + c.lower() + phrase[i+1:]
        if c.lower() and random.random() < self.case_mangling_p:
          phrase = phrase[:i] + c.upper() + phrase[i+1:]  
    return phrase
  
  def mangle(self, phrase):
    if self.mangle_punc:
      phrase = self.__mangle_punctuation(phrase)
    if self.mangle_chars:
      phrase = self.__mangle_char_to_num(phrase)
    if self.mangle_case:
      phrase = self.__mangle_case(phrase)
    return phrase
