#!/usr/bin/env python

"""
  Automated testing, huzzah

  This script automates the regression, module, and fuzz tests.
  
TODO: interface to formatter tests as well
"""

import glob
import os
import subprocess
import sys
import time


# verbose prints out all test results, unverbose prints only failures and warnings
verbose = True
# tests to run, a list of function references
tests = []

# a bunch of file paths
root_path = sys.path[0]
log_path = '{0}/log/'.format(root_path)
log_file = '{0}/runtests.out'.format(log_path)
old_log_file = '{0}/runtests.out.1'.format(log_path)



def init():
  """ set up the options, read arguments, etc """
  global verbose, tests
  if not os.path.exists(log_path): os.mkdir(log_path)
  if (os.path.exists(log_file)):
    if os.path.exists(old_log_file): os.remove(old_log_file)
    os.rename(log_file, old_log_file)

  f = open(log_file, 'w')
  f.write('# Date: {0}\n'.format(time.strftime('%Y-%m-%d %H:%M:%S')))
  f.close()

  test_defs = {
    'fuzz' : fuzz_test,
    'unit' : unit_tests,
    'regression' : regression_tests,
  }

  for arg in sys.argv[1:]:
    if arg == '--quiet': verbose = False
    elif arg.startswith('--') and arg[2:] in test_defs.keys():
      tests.append( test_defs[arg[2:]] )
    elif arg == '--help':
      print ''' Usage: {0} [OPTIONS]
Valid options:
  --<test> \t where test may be: {1}
  
  --quiet  \t Only print failures and warnings
  '''.format(sys.argv[0], ', '.join([name for name in test_defs]))
      sys.exit()
    else:
      print 'Unrecognised argument {0}'.format(arg)

  if not tests: tests = [func for test,func in test_defs.items()]
  #uniquify
  tests = list(set(tests))


def output(text, level=0):
  """ wrapper to print, checks verbosity before printing """
  if level == 0 and not verbose: return
  print text

def feedback(path, retval):
  """ Generates feedback for a test based on a return value
  Also applies ansi colour sequences, huzzah!
  """
  retval = max(0, min(retval, 2))
  colours = ['\033[92m', '\033[91m', '\033[93m']
  texts = ['pass', 'fail', 'warning']
  end = '\033[0m'

  output('  {1}\t\t{0}{2}{3}'.format(colours[retval], path, texts[retval], end),
    1 if retval else 0)


def test(path, args=''):
  """ Test a path with the given arguments"""
  log = open(log_file, 'a')
  log.write('Begin {0} {1}\n'.format(path, args))
  log.flush()
  ret = subprocess.call(['php', path, args], stdout=log, stderr=log)
  log.write('\nEnd {0} {1}\n'.format(path, args))
  log.close()
  feedback(path + ' ' + args, ret)
  return ret == 0

def unit_tests():
  """ Runs unit tests, i.e. those in unit/"""
  output ('Begin unit tests', 1)
  os.chdir(root_path + '/unit/')
  for t in glob.iglob('*.php'):
    test(t)
  

def fuzz_test():
  """ execute the fuzz tester """
  output ('Begin fuzz test (this may take some time)', 1)
  os.chdir('fuzz')
  test('ifuzz.php')
  test('fuzz.php')
  
def regression_tests():
  output ('Begin regression tests', 1)
  os.chdir(root_path + '/regression/')
  files = glob.glob('*/*')
  files = filter(lambda s: not s.endswith('.luminous') and not s.startswith('.') and not s.endswith('~'), files)
  files.sort()
  for f in files:
    test('test.php', f)

if __name__ == '__main__':
  init()
  for func in tests:
    func()
    os.chdir(root_path)
