#!/usr/bin/env sh
# Creates .env with random secrets.
set -eu
[ -f .env ] && { echo ".env already exists"; exit 1; }
sed -e "s/__POSTGRES__/$(openssl rand -hex 16)/" \
    -e "s/__API__/$(openssl rand -hex 24)/" \
    -e "s/__WEBHOOK__/$(openssl rand -hex 16)/" \
    -e "s/__N8N__/$(openssl rand -hex 24)/" .env.example > .env
chmod 600 .env
echo "Created .env"
