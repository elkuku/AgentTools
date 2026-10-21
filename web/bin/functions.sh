#!/bin/bash
#
# Functions for install/start/stop scripts
#
# License: WTFPL
# Author: Nikolai Plath
#

function checkIsInstalled {
  if ! command -v "$1" &> /dev/null
  then
      echo "❌ ERROR: Command '$1' could not be found on your system :("
      exit 1
  fi
}

function getContainerHealth {
  docker inspect --format "{{.State.Health.Status}}" $1
}

function waitContainer {
  while STATUS=$(getContainerHealth $1); [ $STATUS != "healthy" ]; do
    if [ "$STATUS" == "unhealthy" ]; then
      echo "Failed!"
      exit 1
    fi
    printf .
    sleep 1
  done
}
