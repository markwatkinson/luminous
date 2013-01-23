#!/usr/bin/env python

""" Fetches the master version (or tag) from the GitHub 
  repo and packages it suitable for end-users
"""

# the placeholder is for the version
url_tarball = 'https://github.com/markwatkinson/luminous/tarball/{0}'
url_clone = 'git://github.com/markwatkinson/luminous.git'

import re
import os
import shutil
import subprocess
import sys
import urllib2

# dirs and files which need removing, relative to the root of the luminous dir
to_remove = [
  '.git',
  'cache',
  'dist',
  'extern',
  'tests',
  'screenshots',
  '.gitignore',
  'package.py',
  'PREVIEW.markdown'
]


def print_help(): 
  print('''Usage: python {0} [version]
  Version should correspond to a tag in the Git repository. If it is omitted, 
  'master' is used.'''.format(sys.argv[0]))

def read_file(fn):
  with open(fn, 'r') as fn:
    return fn.read()


class Packagers(object):

  def __init__(self):
    self.version = None
    self.replacements = []

  def __do_production(self):
    """ unset the debug flag in the PHP source """
    subprocess.call(['sed', '-i', 
      "s/define('LUMINOUS_DEBUG', true);/define('LUMINOUS_DEBUG', false);/",
      "src/debug.php"])

  def __do_removals(self):
    """ remove the files that don't need to be included in the distribution """
    for d in to_remove:
      if os.path.isdir(d): shutil.rmtree(d)
      elif os.path.isfile(d): os.remove(d)
    
  def __do_doxygen(self):
    """ Generate Doxygen docs (if Doxygen is present) """
    try:
      subprocess.call(['doxygen'],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    except OSError as e:
      print('Warning: failed to execute doxygen: ' + str(e))

  def __do_version(self): 
    """ Append version number to the Readme """
    f = open('README.markdown')
    s = f.read().splitlines()
    s[0] += ' - ' + self.version
    f.close()
    f = open('README.markdown', 'w')
    f.write('\n'.join(s))
    f.close()
    
    # Sets the version attribute in luminous.php
    subprocess.call(['sed', '-i',
      "s/define('LUMINOUS_VERSION',.*;/define('LUMINOUS_VERSION', '{0}');/".format(self.version),
      'src/luminous.php'])
    subprocess.call(['sed', '-i',
      r's/^##\s*master/## {0}/'.format(self.version),
      'CHANGES.markdown'])


  def __do_readme_replace(self, match):
    """ Highlight and alias code blocks in markdown """
    lang = match.group(1)
    code = match.group(2)
    code = code.rstrip('\r\n')
    
    p = subprocess.Popen(
        ['php', 'luminous.php', '-f', 'html', '-l', lang, code],
        stdout=subprocess.PIPE)

    out = p.communicate()[0]
    self.replacements.append(out)
    return '``REPLACEMENT_' + str(len(self.replacements)-1) + '``'

  def __do_readme_unreplace(self, match):
    return self.replacements[int(match.group(1))]

  def __do_readme(self):
    """ translate README.markdown to index.html, requires perl """
    markdown = read_file('README.markdown')    
    # The readme is for github and s therefore stored in GitHub markdown.
      
    # we're going to alter it slightly, GitHub markdown allows syntax
    # highlighting, but the original Markdown script does not.
    # We're going to extract code blocks, run it through Luminous,
    # then insert it again later
    markdown = re.sub('```(.*)(?:\r\n|[\r\n])([\\S\\s]*?)```',
      self.__do_readme_replace, markdown)

    # format the markdown
    # use the executing env's version of the script
    p = subprocess.Popen(['perl',
      sys.path[0] + '/extern/Markdown_1.0.1/Markdown.pl'],
      stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    p.stdin.write(markdown)
    text = p.communicate()[0]
    text = re.sub(r'<code>REPLACEMENT_(\d+)</code>',
      self.__do_readme_unreplace, text)


    api_docs = ''
    # now we're going to copy the API docs from the site. The site
    # uses a variant on Markdown so we use a different parser
    path = 'docs/site/User-API-Reference'
    # use the executing's env if the path doesn't exist in the child
    if not os.path.exists(path): path = sys.path[0] + '/' + path
    with open(path, 'r') as f:
      p = subprocess.Popen( ['php',
        sys.path[0] + '/extern/markuplite/markup.php'],
        stdin=f, stdout=subprocess.PIPE)
      api_docs = p.communicate()[0]

    html_template = '''<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Luminous Syntax Highlighter</title>
    <link rel='stylesheet' type='text/css' href='style/luminous.css'>
    <link rel='stylesheet' type='text/css' href='style/geonyx.css'>
    <style>
      h1{{font-size: x-large;}}
      h2{{font-size: large; }}
      h3{{font-size: medium;}}
      body{{ margin:1em; font-family: sans-serif; font-size:12pt;}}
      .luminous {{ border: 1px solid #bbb; }}
      span.inline-code {{
        background-color: #E7E7E7;
        padding: 1px 0.25em;
        font-family: monospace;
        font-weight: bold;
        color: #323232;
        border: 1px solid #888;
      }}
    </style>
  </head>
  <body>
    {0}
  </body>
</html>'''
    with open('index.html', 'w') as f: f.write(html_template.format(text))
    with open('api-docs.html', 'w') as f: f.write(html_template.format(api_docs))

  def package(self, version):
    self.version = version
    self.__do_version()
    self.__do_doxygen()
    self.__do_production()
    self.__do_readme()
    self.__do_removals()


def clone(version):
  global url_clone
  subprocess.call(['git', 'clone', url_clone, 'luminous-' + version])
  if version != 'master':
    os.chdir('luminous-' + version)
    ret = subprocess.call(['git', 'checkout', version])
    if ret:
      print ('Failed to checkout tag {0}, wrong version?'.format(version))
      sys.exit(1)
    os.chdir('..')

def fetch(version):
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
  

def package(version):
  # move into the dist dir
  os.chdir(sys.path[0])
  if not os.path.exists('dist'): os.mkdir('dist')
  os.chdir('dist')
  
  # Empty the tmp dir and move into it
  if os.path.exists('tmp'): shutil.rmtree('tmp')
  os.mkdir('tmp')
  os.chdir('tmp')

  # use git to clone if we can.
  if subprocess.call(['which', 'git']):
    fetch(version)
  else: clone(version)
  
  # move into the root dir and execute the functions
  dirname = 'luminous-' + version
  os.chdir(dirname)

  p = Packagers()
  p.package(version)
  
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
  
