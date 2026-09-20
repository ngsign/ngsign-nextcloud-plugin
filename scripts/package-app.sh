#!/bin/sh
set -eu
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
mkdir -p dist
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir "$stage/ngsign"
cp -R appinfo css img js l10n lib templates "$stage/ngsign/"
# COPYFILE_DISABLE avoids macOS tar emitting AppleDouble "._*" sidecar files
# and extended-attribute pax headers, which GNU tar on the target Linux host
# either dumps as noisy warnings or extracts as literal junk files.
COPYFILE_DISABLE=1 tar -czf "dist/ngsign-${version}.tar.gz" -C "$stage" ngsign
printf '%s\n' "Created dist/ngsign-${version}.tar.gz"
