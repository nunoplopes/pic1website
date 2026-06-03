#!/bin/bash
# Script to generate code coverage reports

set -e

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

echo "Generating code coverage reports..."

mkdir -p coverage

vendor/bin/phpunit --coverage-html coverage/ --coverage-clover coverage/clover.xml --coverage-text

echo ""
echo "Coverage report generated."
echo ""
echo "Coverage Summary:"
echo "   - HTML Report: open coverage/index.html"
echo "   - Clover XML:  coverage/clover.xml"
echo ""
