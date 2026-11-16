# warehouse-api

Laravel API for **Shaqlin Warehouse** — portfolio, bids (Freelancer / Upwork / other), planning, and AI/app config.

## Quick start

```bash
cd warehouse-api
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API base: `http://127.0.0.1:8000/api`

### Demo login (from seeder)

- Email: `demo@shaqlin.warehouse`
- Password: `demo1234`

### Try

```bash
curl http://127.0.0.1:8000/api/health
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" -d "{\"email\":\"demo@shaqlin.warehouse\",\"password\":\"demo1234\"}"
```

## Tables

| Table | Module |
|-------|--------|
| users | Auth |
| work_projects | Previous / portfolio projects |
| bid_workspaces | Freelancer, Upwork, Other |
| bids | Bid rows per workspace |
| plans / plan_items | Future planning + progress |
| settings | AI + app config (secrets masked) |
| personal_access_tokens | Sanctum |

## Config

- `config/warehouse.php` — AI/app defaults  
- `.env` — `OPENAI_API_KEY`, `WAREHOUSE_AI_*` (optional; AI off by default)

## Next

CRUD controllers for portfolio, bids, plans, settings; wire React login to Sanctum.
"# warehouse-api" 
