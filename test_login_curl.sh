#!/bin/bash

rm -f cookies.txt
echo "--- GET LOGIN PAGE ---"
curl -s -D headers_get.txt -o /dev/null -c cookies.txt https://bbmeet.site/login
cat headers_get.txt | grep -i Set-Cookie

# Extract token
TOKEN=$(curl -s -b cookies.txt https://bbmeet.site/login | grep -o 'name="_token" value="[^"]*"' | grep -o 'value="[^"]*"' | tr -d 'value="')
echo "TOKEN: $TOKEN"

echo "--- POST LOGIN ---"
curl -s -D headers_post.txt -o /dev/null -b cookies.txt -c cookies.txt -X POST https://bbmeet.site/login \
  -d "_token=$TOKEN" -d "username=admin" -d "password=admin"
cat headers_post.txt | grep -i Set-Cookie || echo "No cookies set on POST"
cat headers_post.txt | grep -i Location

echo "--- GET REDIRECT TARGET ---"
# usually /inbox or /admin
curl -s -D headers_target.txt -o /dev/null -b cookies.txt -c cookies.txt https://bbmeet.site/inbox
cat headers_target.txt | grep -i Location
