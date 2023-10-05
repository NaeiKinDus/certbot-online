#!/bin/sh

set -eu
php -r "copy('https://github.com/box-project/box/releases/download/${BOX_VERSION:-4.3.8}/box.phar', 'box.phar');"
chmod 755 box.phar
mv box.phar bin/
