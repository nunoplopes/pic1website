#!/bin/sh

set -e

for f in `find -name '*.php' -not -path './vendor/*'` ; do
  php$PHP_VERSION -l $f
done
