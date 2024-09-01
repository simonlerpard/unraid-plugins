#!/bin/bash

name="simonlerpard.one.password.cli"

# Abort if usr directory exists! (don't want to accidentally remove it)
[ -d "usr/" ] && echo "usr directory exists, please remove it." && exit 1

# Create usr directory
mkdir -p "usr/local/emhttp/plugins/${name}/"

# Copy all source files to the directory
cp -r "packages/src/"* "usr/local/emhttp/plugins/${name}/"

# Tar and compress into a package (exclude the original images to keep the file size as small as possible)
tar -cvJf "packages/archive/${name}.txz" --exclude="*/images/original" "usr/local/emhttp/plugins/${name}/"

# Remove temporary usr directory
rm -r "usr/"

#md5sum "packages/archive/${name}.txz"

md5=$(md5sum "packages/archive/${name}.txz" | awk '{print $1}')
sed -i "s/<!ENTITY MD5 \"[^\"]*\">/<!ENTITY MD5 \"${md5}\">/" "packages/plugins/${name}.plg"

echo "Found md5sum ${md5} for the new archive. It's now also inserted into the plg file. Please check the diff before commit."
