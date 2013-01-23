#!/usr/bin/env bash

# short script to perform a clean export of the current SVN trunk version of
# luminous, and package it into a zip and tar.bz2 archive for redistribution.

# It omits all the rubbish that people don't need (shell scripts, testing 
# scripts, etc).

# It also compresses the main CSS file and javascript file with
# yui-compressor if found. 


svn_url="http://luminous.googlecode.com/svn/trunk/"

revision=`svn info $svn_url | grep revision -i | awk '{print $2}'`
revision="r$revision"
dir=`dirname $0`
if [ $dir = "." ]; then
  dir=`pwd`
fi


cd "$dir/dist/"
echo $dir 

svn export $svn_url luminous-$revision

if [ $? -ne 0 ]; then
  echo "svn checkout failed with exit status $?"
  exit 1
fi

  
cd luminous-$revision

# compress the javascript and main CSS file if we've got yui installed
if which yui-compressor &> /dev/null; then
  yui-compressor style/luminous.css -o style/luminous.min.css
  yui-compressor client/luminous.js -o client/luminous.min.js
fi

# Figure out the version number from the README
version=$( sed -r '1s/.*[\t ]//' README | head -n 1)
echo "Luminous version $version, is this correct? [y]es/[n]o/[q]uit"

read confirm
confirm=$(echo $confirm | tr '[A-Z]' '[a-z]')
if [ "${confirm[0]:0:1}" == "n" ]; then
  echo Enter version number:
  read version
elif [ "${confirm[0]:0:1}" != "y" ]; then
  exit 1   
fi

cd ../
mv luminous-$revision "luminous-$version"
cd "luminous-$version"

# remove DEV section from doxyfile
sed -i -r 's/(ENABLED_SECTIONS\s*=\s*.*?)(DEV)(.*?)/\1/' Doxyfile

dir=$(pwd | sed -r 's/.*?\///')


# zip wants each individual file specified,
# tar doesn't. It's a pain
excludes=(
          $dir/*.sh
          $dir/**/*~ 
          $dir/tests/*
          $dir/tests
          $dir/docs/*
          $dir/docs

          );

tar_ex=
zip_ex="-x"

for x in ${excludes[@]}
do
  tar_ex="$tar_ex --exclude=$x"
  zip_ex="$zip_ex $x"
done


cd ..
tar -cvvjf luminous-$version.tar.bz2 $tar_ex $dir
zip -r luminous-$version.zip $tar_ex  $dir

echo "Done"

