#!/usr/bin/env bash

set -exuo pipefail

# Run all grumphp tasks
CWD=$(pwd)

# foreach packages/*
directories=$(find $CWD/packages -maxdepth 1 -mindepth 1 -type d)
for dir in $directories
do
    cd $dir
    # cleanup each package symlinks:
    rm -rf vendor/pluswerk/grumphp-bom-task vendor/pluswerk/grumphp-xliff-task vendor/andersundsehr/phpstan-git-files vendor/andersundsehr/rector-p
done

for dir in $directories
do
    cd $dir
    echo -e "\033[0;31m"
    echo -e "\n\n\n           Running grumphp in $(basename $(pwd))\n\n\n"
    echo -e "\033[0m"
    # mirroring of composer takes to long so we use rsync instead of deleting the vendor folder
    rsync -az --exclude=packages --exclude=vendor --mkpath $CWD/ vendor/pluswerk/grumphp-config/
    # update everything else:
    composer update --ignore-platform-req=php+
    # run the checks:
    vendor/bin/grumphp run
    # cleanup symlinks so that we can run the script again
    rm -rf vendor/pluswerk/grumphp-bom-task vendor/pluswerk/grumphp-xliff-task vendor/andersundsehr/phpstan-git-files vendor/andersundsehr/rector-p
done

cd $CWD
echo -e "\033[0;31m"
echo -e "\n\n\n           Running grumphp in ROOT\n\n\n"
echo -e "\033[0m"
composer update --ignore-platform-req=php+
vendor/bin/grumphp run
