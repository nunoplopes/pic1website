#!/bin/sh

set -e

for f in `find -name '*.php'` ; do
  php$PHP_VERSION -l $f
done
