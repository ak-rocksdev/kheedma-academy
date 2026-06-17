# Deployment — Kheedma Academy

Atomic, zero-downtime deploy to the VPS (`103.157.97.233`), mirroring the
hyperscore deploy convention. Server details live in `docs/server-info.md`
(gitignored).

## Layout on the server

```
/srv/www/kheedma-academy/
├── current -> releases/<timestamp>   # atomic symlink (Nginx root → current/public)
├── deploy/{setup.sh, deploy.sh}
├── releases/<timestamp>/             # each release (keep last 5)
├── shared/{.env, storage/}           # persisted across releases
├── logs/                             # deploy logs + ledger
└── database/                         # DB backups (only when --migrate is used)
```

- **PHP**: `php8.4-fpm` (Laravel 13 requires PHP ≥ 8.3 — *not* the server default 8.2).
- **Node**: v20.19.6 (satisfies Vite 8).
- **Domain**: `https://kheedma.hyperscore.cloud` (DNS A → 103.157.97.233).

## First-time setup

```bash
# 1. directory skeleton + shared/.env (APP_KEY auto-generated, file-based drivers, no DB)
ssh ak_rocks@103.157.97.233 "mkdir -p /srv/www/kheedma-academy/deploy"
scp deploy/setup.sh deploy/deploy.sh ak_rocks@103.157.97.233:/srv/www/kheedma-academy/deploy/
ssh ak_rocks@103.157.97.233 "bash /srv/www/kheedma-academy/deploy/setup.sh"

# 2. allow passwordless reload of php8.4-fpm for the deploy user (one-off, needs sudo)
#    echo 'ak_rocks ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm' \
#      | sudo tee /etc/sudoers.d/kheedma-deploy

# 3. first deploy
ssh ak_rocks@103.157.97.233 "bash /srv/www/kheedma-academy/deploy/deploy.sh main"

# 4. Nginx site + TLS
#    copy docs/deploy/nginx-kheedma.conf to /etc/nginx/sites-available/, enable it,
#    nginx -t && reload, then:
#    sudo certbot --nginx -d kheedma.hyperscore.cloud
```

## Routine deploy

```bash
ssh ak_rocks@103.157.97.233 "bash /srv/www/kheedma-academy/deploy/deploy.sh main"
# add --migrate once the app uses a database
```

## Notes

- Repo is **public** for now → server clones over **HTTPS** (no deploy key).
  When it goes private: add a read-only deploy key + SSH host alias on the
  server and switch `GIT_REPO` in `deploy/deploy.sh` to the `git@github-…` form.
- The UI preview needs **no database**; `shared/.env` ships file-based session/
  cache drivers so the app boots without one.
