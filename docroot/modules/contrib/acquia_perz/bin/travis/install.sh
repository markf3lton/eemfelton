#!/usr/bin/env bash

# NAME
#     install.sh - Install Travis CI dependencies
#
# SYNOPSIS
#     install.sh
#
# DESCRIPTION
#     Creates the test fixture.

set -ev

cd "$(dirname "$0")" || exit; source _includes.sh

# Exit early in the absence of a fixture.
[[ -d "$ORCA_FIXTURE_DIR" ]] || exit 0

DRUPAL_CORE=9;
if [[ "$ORCA_JOB" == "INTEGRATED_TEST_ON_OLDEST_SUPPORTED" || "$ORCA_JOB" == "INTEGRATED_TEST_ON_LATEST_LTS" || $DEPLOY || $DO_DEV ]]; then
  DRUPAL_CORE=8;
fi

if [[ "$DRUPAL_CORE" == "9" ]]; then
  echo "Adding modules for Drupal 9.x..."
  # @TODO: Make sure to update beta version of webform to a released version once it is released.
  # @TODO: Stop forcing Composer path. Doing it for now to prevent errors.
  composer -d"$ORCA_FIXTURE_DIR" require --dev \
    drupal/paragraphs
  # Determining PHPUnit version.
  PHPUNIT_VERSION=`phpunit --version | cut -d ' ' -f 2`
  if [[ $PHPUNIT_VERSION  =~ ^[8] ]]; then
    composer -d"$ORCA_FIXTURE_DIR" require --dev dms/phpunit-arraysubset-asserts
  else
    composer -d"$ORCA_FIXTURE_DIR" require --dev dms/phpunit-arraysubset-asserts
  fi
else
  echo "Adding modules for Drupal 8.x..."
  # Eliminating Warnings to avoid failing tests on deprecated functions:
  export SYMFONY_DEPRECATIONS_HELPER=disabled codecept run
  composer -d"$ORCA_FIXTURE_DIR" require --dev \
    drupal/paragraphs
fi
