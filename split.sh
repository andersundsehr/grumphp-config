#!/usr/bin/env bash

set -exuo pipefail

# check if gh is installed
if ! command -v gh &> /dev/null
then
    echo -e "\033[31m[ERROR] gh is not installed\033[0m"
    exit 1
fi

#check if GH_TOKEN is set
if [ -z "${GH_TOKEN}" ]; then
  echo -e "\033[31m[ERROR] GH_TOKEN is not set\033[0m"
  exit 1
fi

# go through all packages
packages=$(ls packages/)
# if first parameter is set, we only want to split this package
if [ $# -gt 0 ]; then
  packages=$1
  # check if directory exists
  if [ ! -d "packages/${packages}" ]; then
    echo -e "\033[31m[ERROR] Package ${packages} does not exist\033[0m"
    exit 1
  fi
fi

commitMessage=$(git log -1 --pretty=format:"%s | %an <%ae> | %ad | https://github.com/andersundsehr/grumphp-config/commit/%H" --date=iso --no-show-signature)

mkdir -p tmp/

function printToSummary() {
  echo -e "\033[33m$1\033[0m"
  if [ ! -z ${GITHUB_STEP_SUMMARY+x} ]; then
    echo "$1" >> $GITHUB_STEP_SUMMARY
  fi
}

# set author for git
# only set if not present
git config --global user.email || git config --global user.email "m.vogel@andersundsehr.com"
git config --global user.name || git config --global user.name "Automated Splitter"

for package in $packages ; do
  echo -e "\033[36m[INFO] ${package} started\033[0m"
  rm -rf tmp/${package}/
  git clone https://${GH_TOKEN}@github.com/andersundsehr/${package}.git tmp/${package}/
  rsync -acv --delete --exclude='.git' --exclude-from=packages/${package}/.gitignore --exclude-from=.gitignore packages/${package}/ tmp/${package}/
  git -C tmp/${package}/ add .

  # if there is nothing to commit we can skip the commit and push
  if [ -z "$(git -C tmp/${package}/ status --porcelain)" ]; then
    # color yellow
    printToSummary "🟡 Nothing to commit for \`${package}\`"
  else
  git -C tmp/${package}/ commit -m "${commitMessage}"

  git -C tmp/${package}/ push --follow-tags
    printToSummary "✅ Pushed to remote \`${package}\`"
  fi

  currentMonoRepoTag=$(git tag --points-at HEAD)
  if [ ! -z "$currentMonoRepoTag" ]; then
    # only if the current package commit doesn't have a tag
    packageCommitTag=$(git -C tmp/${package}/ tag --points-at HEAD)
    commitHash=$(git -C tmp/${package}/ rev-parse HEAD)
    if [ ! -z "$packageCommitTag" ]; then
      commit=$(git -C tmp/${package}/ rev-parse HEAD)
      printToSummary "🦘 skip tag \`${currentMonoRepoTag}\` for \`${package}\` as the current package commit \`${commitHash}\` already has the tag \`${packageCommitTag}\`"
    else
      git -C tmp/${package}/ tag -e -f -a $currentMonoRepoTag -m "See more at https://github.com/andersundsehr/grumphp-config/releases/tag/${currentMonoRepoTag}" --no-edit
      git -C tmp/${package}/ push --tags
      GH_REPO=andersundsehr/${package} gh release create $currentMonoRepoTag --notes-from-tag --verify-tag
      printToSummary "✅ Published \`${package}:${currentMonoRepoTag}\`"
    fi
  fi
done
