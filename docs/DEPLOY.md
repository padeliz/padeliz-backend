# Deploy (DigitalOcean App Platform)

## Pipeline

| Trigger | Action |
|---|---|
| Pull request | CI tests only |
| Push to `main` | Test → build/push DOCR → deploy **staging** |
| GitHub Release published | Build/push DOCR → deploy **production** (requires your approval) |

Images: `registry.digitalocean.com/padeliz/padeliz-backend`  
Deploy uses an **immutable image digest** (not floating `latest`).

## App IDs

| Env | App ID |
|---|---|
| Staging | `c7daf18c-6b7c-40a0-8cff-fabb33cdd0f5` |
| Production | `d3999831-c1e1-46f4-8dff-ca8787c0981a` |

## Runtime secrets (App Platform UI)

Set these as **encrypted** env vars on each backend app (staging and production). Specs declare them as `SECRET` without values.

### Both environments

- `APP_KEY` — `php artisan key:generate --show`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from Managed MySQL connection details
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` — from Managed Valkey
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` — Spaces keys
- `AWS_URL` — optional public/CDN base for the bucket (e.g. `https://padeliz-staging.fra1.cdn.digitaloceanspaces.com`)
- `GOOGLE_APP_CLIENT_ID` — Google Web client ID for Player App id_tokens
- `GOOGLE_MANAGER_CLIENT_ID` — Google Web client ID for Manager id_tokens
- `NETOPIA_POS_SIGNATURE`, `NETOPIA_API_KEY`
- `NETOPIA_RETURN_URL` / `NETOPIA_NOTIFY_URL` — full URLs below (also set notify in Netopia merchant settings)

### Netopia URLs (exact)

| Env | `NETOPIA_RETURN_URL` | `NETOPIA_NOTIFY_URL` |
|---|---|---|
| Staging | `https://backend.staging.padeliz.ro/api/v1/payments/netopia/return` | `https://backend.staging.padeliz.ro/api/v1/webhooks/netopia/ipn` |
| Production | `https://backend.padeliz.ro/api/v1/payments/netopia/return` | `https://backend.padeliz.ro/api/v1/webhooks/netopia/ipn` |

Mobile deep link after return uses scheme `padeliz-app://` (see frontend app).

### Production only

- `MAIL_PASSWORD` — Google Workspace App Password for `noreply@padeliz.ro`

Staging mail uses `MAIL_MAILER=log` (no SMTP password).

## Components (same image digest)

| Component | Command |
|---|---|
| `web` | nginx + php-fpm |
| `worker` | `queue:work redis` |
| `scheduler` | `schedule:work` |
| `migrate` (PRE_DEPLOY) | `backend:refresh --deploy` |

## Rollback

Redeploy a previous digest (DO App Platform → Deployments → redeploy, or re-run the GitHub Actions deploy workflow on an older commit / release).

## Health

`GET https://backend…/up` → `ok`
