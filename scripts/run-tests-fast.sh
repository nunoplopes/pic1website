#!/bin/bash
# Script to run tests without generating coverage reports

set -e

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

vendor/bin/phpunit --no-coverage
