#!/usr/bin/env python

""" Fetches the master version (or tag) from the GitHub 
  repo and packages it suitable for end-users
"""

# the placeholder is for the version
url = 'https://github.com/markwatkinson/luminous/tarball/{0}'

import os
import shutil
import subprocess
import sys
import urllib2

# dirs and files which need removing, relative to the root of the luminous dir
to_remove = ['dist', 
  'docs', 
  'package.py', 
  'tests', 
  '.gitignore', 
  'screenshots',
  'PREVIEW.markdown'
]


def print_help(): 
  print('''Usage: python {0} [version]
  Version should correspond to a tag in the Git repository. If it is omitted, 
  'master' is used.'''.format(sys.argv[0]))

def do_production(data):
  """ unset the debug flag in the PHP source """
  subprocess.call(['sed', '-i', 
    "s/define('LUMINOUS_DEBUG', true);/define('LUMINOUS_DEBUG', false);/",
    "src/debug.php"])


def do_removals(data):
  for d in to_remove: 
    if os.path.isdir(d): shutil.rmtree(d)
    elif os.path.isfile(d): os.remove(d)
    
def do_doxygen(data):
  try:
    subprocess.call(['doxygen'])
  except OSError as e:
    print('Warning: failed to execute doxygen: ' + str(e))

def do_version(data): 
  version = data
  f = open('README.markdown')
  s = f.read().splitlines()
  s[0] += ' - ' + version
  f.close()
  f = open('README.markdown', 'w')
  f.write('\n'.join(s))
  f.close()
  
    

functions = [do_removals, do_doxygen, do_production, do_version]

def package(version):
  # move into the dist dir
  os.chdir(sys.path[0])
  if not os.path.exists('dist'): os.mkdir('dist')
  os.chdir('dist')
  
  # Empty the tmp dir and move into it
  if os.path.exists('tmp'): shutil.rmtree('tmp')
  os.mkdir('tmp')
  os.chdir('tmp')
  
  # fetch the tarball
  url_to_fetch = url.format(version)
  print('Fetching version {0} from {1}'.format(version, url_to_fetch))
  try:
    tarball = urllib2.urlopen(url.format(version)).read()
  except Exception as e:
    print 'Fetch failed: {0}\nWrong version?'.format(str(e))
    sys.exit(1)
  print 'Fetched'
  f = open('tmp.tar.gz', 'wb')
  f.write(tarball)
  f.close()
  # extract the tarball here
  subprocess.call(['tar', '-xf', 'tmp.tar.gz'])
  os.remove('tmp.tar.gz')
  # move the distributions's root dir to something more sensibly named
  dirname_ = os.listdir('.')[0]
  dirname = 'luminous-{0}'.format(version)
  os.rename(dirname_, dirname)
  # move into the root dir and execute the functions
  os.chdir(dirname)
  for f in functions: f(version)

  
  # now move back out of it and tar/zip it up
  os.chdir('..')  
  tarname = dirname + '.tar.bz2'
  zipname = dirname + '.zip'  
  subprocess.call(['tar', '-cjf', tarname, dirname])
  subprocess.call(['zip', '-rq', zipname, dirname])
  # and move the resulting archives up a level (into the dist dir)
  for f in (tarname, zipname): os.rename(f, '../' + f)
  # done. Let's print something.  
  os.chdir('..')  
  print('Wrote {0}/{1} and {0}/{2}'.format(os.getcwd(), tarname, zipname))
  
if __name__ == '__main__':
  if '--help' in sys.argv: print_help()
  else: package('master' if len(sys.argv) < 2 else sys.argv[1])
  
  
