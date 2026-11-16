# warehouse-api

Laravel API only (no Laravel frontend). React app lives in a separate repo/folder.

Base URL: `https://api.warehouse.liwaas.com`

## Endpoints (current)

- `GET /` — service ping  
- `GET /api/health`  
- `POST /api/login`  
- `GET /api/me` · `POST /api/logout` · `GET /api/overview` (auth)

No database seeder — create data via API.

## Deploy / update on cPanel

```bash
cd ~/api.warehouse.liwaas.com
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Document root must be: `.../api.warehouse.liwaas.com/public`
