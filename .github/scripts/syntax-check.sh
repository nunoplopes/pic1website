#!/bin/sh

set -e

php -v

for f in `find -name '*.php' -not -path './vendor/*'` ; do
  php -l $f
done
