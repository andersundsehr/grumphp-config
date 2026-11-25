#!/usr/bin/env bash

set -exuo pipefail

# check if gh is installed
if ! command -v gh &> /dev/null
then
    echo -e "\033[31m[ERROR] gh is not installed\033[0m"
    exit 1
fi

# check if jq is installed
if ! command -v jq &> /dev/null
then
    echo -e "\033[31m[ERROR] jq is not installed\033[0m"
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

# major version of this monorepo from the root composer.json, e.g. "dev-main as 12.0.0" -> 12
sourceMajor=$(jq -r '[(."require-dev" // {})[] | strings | select(startswith("dev-main as ")) | ltrimstr("dev-main as ") | split(".")[0]][0] // empty' composer.json)

for package in $packages ; do
  echo -e "\033[36m[INFO] ${package} started\033[0m"
  rm -rf tmp/${package}/
  git clone https://${GH_TOKEN}@github.com/andersundsehr/${package}.git tmp/${package}/

  # main of the package repository holds the newest major version.
  # An older major version (e.g. a release from the branch 11.x) goes to a branch of that name,
  # starting at the last tag of that major version, so it does not overwrite main.
  targetBranch=main
  # major version on main of the package repository from extra.branch-alias.dev-main, e.g. 12.0.x-dev -> 12
  mainMajor=$(jq -r '.extra["branch-alias"]["dev-main"] // ""' tmp/${package}/composer.json | cut -d. -f1)
  if [ -n "${sourceMajor}" ] && [ -n "${mainMajor}" ] && [ "${sourceMajor}" -lt "${mainMajor}" ]; then
    targetBranch="${sourceMajor}.x"
    if git -C tmp/${package}/ rev-parse --verify --quiet "refs/remotes/origin/${targetBranch}" > /dev/null; then
      git -C tmp/${package}/ checkout -B "${targetBranch}" "origin/${targetBranch}"
    else
      lastTag=$(git -C tmp/${package}/ tag --list "${sourceMajor}.*" --sort=-v:refname | sed -n 1p)
      if [ -z "${lastTag}" ]; then
        echo -e "\033[31m[ERROR] ${package} has no branch ${targetBranch} and no tag ${sourceMajor}.* to start it from\033[0m"
        exit 1
      fi
      git -C tmp/${package}/ checkout -B "${targetBranch}" "${lastTag}"
    fi
  fi

  rsync -acv --delete --exclude='.git' --exclude-from=packages/${package}/.gitignore --exclude-from=.gitignore packages/${package}/ tmp/${package}/
  git -C tmp/${package}/ add .

  # if there is nothing to commit we can skip the commit and push
  if [ -z "$(git -C tmp/${package}/ status --porcelain)" ]; then
    # color yellow
    printToSummary "🟡 Nothing to commit for \`${package}\` (${targetBranch}) https://github.com/andersundsehr/${package}"
  else
  git -C tmp/${package}/ commit -m "${commitMessage}"

  git -C tmp/${package}/ push --follow-tags origin "HEAD:refs/heads/${targetBranch}"
    printToSummary "✅ Pushed to remote \`${package}\` (${targetBranch}) https://github.com/andersundsehr/${package}/tree/${targetBranch}"
  fi

  currentMonoRepoTag=$(git tag --points-at HEAD)
  if [ ! -z "$currentMonoRepoTag" ]; then
    # only if the current package commit doesn't have a tag
    packageCommitTag=$(git -C tmp/${package}/ tag --points-at HEAD)
    commitHash=$(git -C tmp/${package}/ rev-parse HEAD)
    if [ ! -z "$packageCommitTag" ]; then
      commit=$(git -C tmp/${package}/ rev-parse HEAD)
      printToSummary "🦘 skip tag \`${currentMonoRepoTag}\` for \`${package}\` as the current package commit \`${commitHash}\` already has the tag \`${packageCommitTag}\` https://github.com/andersundsehr/${package}/releases/tag/${packageCommitTag}"
    else
      git -C tmp/${package}/ tag -e -f -a $currentMonoRepoTag -m "See more at https://github.com/andersundsehr/grumphp-config/releases/tag/${currentMonoRepoTag}" --no-edit
      git -C tmp/${package}/ push --tags
      GH_REPO=andersundsehr/${package} gh release create $currentMonoRepoTag --notes-from-tag --verify-tag
      printToSummary "✅ Published \`${package}:${currentMonoRepoTag}\` https://github.com/andersundsehr/${package}/releases/tag/${currentMonoRepoTag}"
    fi
  fi
done
