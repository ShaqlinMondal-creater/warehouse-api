# warehouse-api

Laravel API for Shaqlin Warehouse.  
Base: `https://api.warehouse.liwaas.com/api`

No seeder — use these endpoints.

## Auth

| Method | Path | Body |
|--------|------|------|
| POST | `/login` | `email`, `password` |
| GET | `/me` | Bearer token |
| POST | `/logout` | Bearer token |

## Modules (Bearer token required)

| Resource | Paths |
|----------|--------|
| Portfolio | `GET/POST /work-projects`, `GET/PUT/DELETE /work-projects/{id}` |
| Bid workspaces | `GET/POST /bid-workspaces`, `GET/PUT/DELETE /bid-workspaces/{id}` |
| Bids | `GET/POST /bid-workspaces/{id}/bids`, `GET/PUT/DELETE /bids/{id}` |
| Bid metrics | `GET /bid-workspaces/{id}/analytics` (total/active/win rate/avg labels) |
| Plans | `GET/POST /plans`, `GET/PUT/DELETE /plans/{id}` |
| Plan items | `POST /plans/{id}/items`, `PUT/DELETE /plan-items/{id}` |
| Settings | `GET /settings?group=ai`, `PUT /settings` |
| Overview | `GET /overview` |

### Update on cPanel after git push

```bash
cd ~/api.warehouse.liwaas.com
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan route:cache
php artisan config:cache
```
