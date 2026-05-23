#!/usr/bin/env bash
set -euo pipefail
root=$(pwd)
passenger_test="tests/Feature/PassengerApiTest.php"
# Build ordered list of test files
mapfile -t files < <(find tests -type f -name '*Test.php' | sort)
# Find index of passenger test
idx=-1
for i in "${!files[@]}"; do
  if [[ "${files[$i]}" == "$passenger_test" ]]; then
    idx=$i
    break
  fi
done
if [[ $idx -lt 0 ]]; then
  echo "Passenger test not found in list"
  exit 1
fi
echo "Passenger test is at index $idx (${files[$idx]})"
# Binary search for smallest prefix that causes passenger test to fail after running
low=0
high=$idx
found=
while [[ $low -lt $high ]]; do
  mid=$(( (low+high)/2 ))
  echo "Testing prefix up to index $mid (${files[$mid]})"
  prefix=("${files[@]:0:mid+1}")
  # Run prefix
  if ! ./vendor/bin/phpunit --colors=always "${prefix[@]}" >/tmp/prefix_run.out 2>&1; then
    echo "Prefix run failed on its own; narrowing high to mid"
    high=$mid
    continue
  fi
  # Now run the passenger test after the prefix
  if ! ./vendor/bin/phpunit --colors=always "$passenger_test" >/tmp/passenger_after_prefix.out 2>&1; then
    echo "Passenger test failed after prefix up to index $mid"
    found=$mid
    high=$mid
  else
    echo "Passenger test passed after prefix up to index $mid"
    low=$((mid+1))
  fi
done
if [[ -z "$found" ]]; then
  echo "No single prefix up to passenger test reproduced failure. Consider increasing runs or different ordering."
  exit 0
fi
echo "Found minimal prefix index $found causing failure when followed by passenger test"
echo "Offending file: ${files[$found]}"
# show logs
echo "---- prefix run output ----"
sed -n '1,200p' /tmp/prefix_run.out || true
echo "---- passenger after prefix output ----"
sed -n '1,200p' /tmp/passenger_after_prefix.out || true
exit 0
