#!/usr/bin/env python

import sys

from markov import Markov
from generator import PasswordGenerator
from trainer import MarkovTrainer

ui_data = {'progress_cb':None}


def print_usage():
  print """
Usage: {0} [OPTIONS] [LENGTH]
  
Options:
  
  
  --dont-mangle 
                Doesn't mangle the password at all (equivalent to 
                  --dont-mangle-case --dont-mangle-chars --dont-mangle-punc)
  --dont-mangle-case
                Doesn't mangle the case of the password                  
  --dont-mangle-chars
                Doesn't mangle the characters of the password (i.e. l will    
                  not be converted to 1, e will not be converted to 3, etc)
  --dont-mangle-punc
                Doesn't mangle the punctuation of the password (i.e. all 
                  spaces will be preserved as spaces)
                  
  -f
  --fresh       Does not load any previously saved training data. Use in 
                combination with -l and/or -r
  
  -l PATH PATH2 ... @
  --local-train PATH PATH2 ... @
                The file(s) in the given path are used to train the model.
                PATH may be either a single file or a directory. Directories
                are not scanned recursively. Terminate this list with an @
                                
  -m password,
  --mangle password
                Mangles the given password, does not generate a new one
  -n n,
  --number n    Number of passwords to generate. Each will be printed on an 
                individual line
                
  -r n
  --remote-train n
                Enables remote training. Random articles are fetched from 
                Wikipedia. N is the number of articles to fetch to use as the 
                training data. Note that for reasons of etiquette, there is
                a pause of 1 second between requesting pages.
  -s,
  --save        Saves the training data. This is a potential security risk as
                someone with access to it would be able to drastically reduce
                the entropy of the password. But it means future runs can run
                without needing to re-train.                

  -v            Be verbose
  
                
  LENGTH:       Approximate length of resulting password in characters. 
                Default:20
                
                
  """.format( (sys.argv[0]) )
  
  
def verbose(string, level):
  if level:
    print string


def main_(argv):
  
  if ui_data['progress_cb'] is not None:
    ui_data['progress_cb']('', 0)
    
  if '-h' in argv or '--help' in argv:
    print_usage()
    sys.exit(0)
    
  opts = {
      'length': 20,
      'save': False,
      'local':[],
      'remote':False,
      'mangle' : True,
      'mangle-case' : True,
      'mangle-chars': True,
      'mangle-punc': True,
      'num': 1,
      'to_mangle':False,
      'fresh': False,
      'verbose':0

    }
  i = 1
  
  while i < len(argv):
    a = argv[i]
    
    if a == '--save' or a == '-s':
      opts['save'] = True

    elif a == '--dont-mangle':
      opts['mangle'] = False
    elif a == '--dont-mangle-case':
      opts['mangle-case'] = False
    elif a == '--dont-mangle-chars':
      opts['mangle-chars'] = False
    elif a == '--dont-mangle-punc':
      opts['mangle-punc'] = False
    elif a == '-n' or a == '--number':
      opts['num'] = int(argv[i+1])
      i+=1
    elif a == '-r' or a == '--remote-train':
      opts['remote'] = int(argv[i+1])
      i+=1
    elif a == '-l' or a == '--local-train':
      
      while i < len(argv)-1:        
        if argv[i+1].startswith('-'):
          break
        if argv[i+1] == '@':
          i+=1 
          break
        opts['local'] += [argv[i+1]]
        i+=1
      
    elif a == '-m' or a == '--mangle':
      opts['to_mangle'] = argv[i+1]
      
    elif a == '-f' or a == '--fresh':
      opts['fresh'] = True
      
    elif a == '-v':
      opts['verbose']+=1
      
    else:
      opts['length'] = int(a)
    i+=1
  
  opts['length'] = int(opts['length'])

  m = None
  trainer = MarkovTrainer()
  if opts['fresh']:
    verbose('Fresh load -- no previous data restored', opts['verbose'])
  else:
    m = trainer.load_state()
    
  if m is None:  
    m = Markov()
    

  if opts['remote']:
    verbose('Training remotely', opts['verbose'])
    for x in xrange(int(opts['remote'])):
      if ui_data['progress_cb'] is not None:
        ui_data['progress_cb']('Training remotely', (x+1.0)/float(opts['remote']))
      m = trainer.train_remotely(m, 1)
  
    
  if opts['local']:
    for i, path in enumerate(opts['local']):
      verbose('Training locally: {0}'.format(path), opts['verbose'])
      if ui_data['progress_cb'] is not None:
        ui_data['progress_cb']('Training locally', (i+1.0)/len(opts['local']))
      m = trainer.train_locally(m, path)
      
      
  if opts['save']:
    trainer.save_state(m)
  
  for x in xrange(int(opts['num'])):
    pg = PasswordGenerator(m)  
    pg.mangle_punc = opts['mangle-punc']
    pg.mangle_case = opts['mangle-case']
    pg.mangle_chars = opts['mangle-chars']
    
    if ui_data['progress_cb'] is not None:
      ui_data['progress_cb']('Generating passwords', (x+1.0)/int(opts['num']))

    
    phrase = opts['to_mangle'] if opts['to_mangle'] is not False else pg.generate_base_phrase(opts['length'])
    
    if opts['mangle']:
      phrase = pg.mangle(phrase)
    yield phrase
  if ui_data['progress_cb'] is not None:
    ui_data['progress_cb']('Done', 1)
 
def main(argv):
  
  for out in main_(argv):
    print out

if __name__ == '__main__':
  main(sys.argv)