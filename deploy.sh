#!/bin/bash
# Deploy the CRM on the server: pull latest, rebuild image, migrate, cache, restart workers.
set -e
cd "$(dirname "$0")"

echo "==> Pulling latest code..."
git pull --ff-only

echo "==> Building image..."
docker compose build app

echo "==> Starting container..."
docker compose up -d app

echo "==> Waiting for container..."
sleep 5

echo "==> Running migrations..."
docker compose exec -T app php artisan migrate --force

echo "==> Rebuilding caches..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "==> Restarting queue workers (load new code)..."
docker compose exec -T app php artisan queue:restart

echo "==> Deployed! Health:"
docker compose ps
