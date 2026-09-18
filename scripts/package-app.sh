#!/bin/sh
set -eu
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
mkdir -p dist
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir "$stage/ngsign"
cp -R appinfo css js l10n lib templates "$stage/ngsign/"
tar -czf "dist/ngsign-${version}.tar.gz" -C "$stage" ngsign
printf '%s\n' "Created dist/ngsign-${version}.tar.gz"
