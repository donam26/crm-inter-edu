# Deployment — crm.interedu.ai.vn

Testing server deployment. Same architecture as the interedu production stack
(host nginx + certbot TLS → Docker app container → shared db-stack).

## Topology

```
Internet ──HTTPS──> host nginx (:443, Let's Encrypt)
                        │  reverse proxy
                        ▼
                 127.0.0.1:8000  (crm-inter-edu container)
                 php-fpm + nginx + supervisor (queue worker, scheduler)
                        │  docker network: db-stack_db_net
                        ▼
                 mysql:3306 / redis:6379  (db-stack compose)
```

- **Server:** `103.72.99.132` (SSH port `24700`), Ubuntu 24.04, 2 vCPU / 2 GB (+2 GB swap).
- **Domain:** `crm.interedu.ai.vn` (DNS A → 103.72.99.132, Tenten).
- **Repo image:** built on the server from this repo's `Dockerfile` (php:8.4-fpm-alpine).

## Paths on the server

| What | Path |
|------|------|
| App (git clone) | `/root/docker-volume/ecosystem/crm/crm-inter-edu` |
| App env (secrets) | `…/crm-inter-edu/.env` (chmod 600, not in git) |
| DB stack (MySQL+Redis) | `/root/docker-volume/platform/db-stack` |
| DB stack env | `…/db-stack/.env` (DB/Redis passwords) |
| Host nginx vhost | `/etc/nginx/sites-available/crm.interedu.ai.vn.conf` |
| TLS certs | `/etc/letsencrypt/live/crm.interedu.ai.vn/` |
| GitHub deploy key | `/root/.ssh/id_ed25519_github` |

## Deploy an update

```bash
ssh -p 24700 root@103.72.99.132
cd /root/docker-volume/ecosystem/crm/crm-inter-edu
./deploy.sh        # git pull → docker compose build → up -d → migrate → cache → queue:restart
```

## Config choices

- **Cache / session / queue = `database`** (MySQL-backed). Redis is provisioned in
  db-stack but the app does not depend on it; switch the `*_DRIVER` vars in `.env`
  to `redis` (host `redis`) to use it.
- Container binds `127.0.0.1:8000` only; host nginx terminates TLS and proxies.
  `trustProxies(at: '*')` in `bootstrap/app.php` makes Laravel emit `https://` URLs.
- **Production image excludes dev deps** (`--no-dev`) → Faker is absent, so the
  factory-based demo seeders don't run in prod. Only `RolePermissionSeeder` +
  `SuperAdminSeeder` are seeded (no Faker).

## TLS renewal

`certbot.timer` runs daily; renewal deploy hook
`/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` reloads host nginx.

## Auto-start

`docker` + `nginx` services enabled; all containers `restart: unless-stopped` →
everything comes back after a reboot.
