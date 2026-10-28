# Deploy (DigitalOcean App Platform)

## Pipeline

| Trigger | Action |
|---|---|
| Pull request | CI tests only |
| Push to `main` | Test → build/push DOCR → deploy **staging** (image digests only) |
| GitHub Release published | Build/push DOCR → deploy **production** (image digests only; requires your approval) |

Images: `registry.digitalocean.com/padeliz/padeliz-backend`  
Deploy uses an **immutable image digest** (not floating `latest`).

Daily deploys do **not** push [`.do/app.*.yaml`](../.do/). They only update component image digests on the existing App Platform app so encrypted secrets in the DigitalOcean UI are never wiped.

## App identity

| Env | App name | App ID |
|---|---|---|
| Staging | `padeliz-backend-staging` | `c7daf18c-6b7c-40a0-8cff-fabb33cdd0f5` |
| Production | `padeliz-backend-prod` | `d3999831-c1e1-46f4-8dff-ca8787c0981a` |

## Role of `.do/app.*.yaml`

Checked-in **bootstrap / contract** only:

- App shape (domains, components, image repository, alerts)
- Plain env defaults documented in git (e.g. `REDIS_SCHEME=tls`)
- Secret **key names** as `type: SECRET` with no values

Secret **values** and the live applied config live in the App Platform UI. Changing structure or plain envs in YAML requires a **manual** DO update (or rare one-off spec sync), not the normal GitHub deploy.

## Runtime secrets (App Platform UI)

Set these as **encrypted** env vars on each backend app (staging and production). Paste real non-empty values; encrypt in DO. Rotate in the UI only, then redeploy.

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

### Plain Redis / Valkey (required on staging and production)

Managed Valkey uses TLS. Set these as **plain** (non-encrypted) app-level env vars in the App Platform UI (also documented in `.do/app.*.yaml`):

- `REDIS_SCHEME` = `tls`
- `REDIS_USERNAME` = `default` (or the username from Valkey Connection details)

Local Docker Redis keeps `REDIS_SCHEME=tcp` and no username (see `.env.example`).

### Netopia URLs (exact)

| Env | `NETOPIA_RETURN_URL` | `NETOPIA_NOTIFY_URL` |
|---|---|---|
| Staging | `https://backend.staging.padeliz.ro/api/v1/payments/netopia/return` | `https://backend.staging.padeliz.ro/api/v1/webhooks/netopia/ipn` |
| Production | `https://backend.padeliz.ro/api/v1/payments/netopia/return` | `https://backend.padeliz.ro/api/v1/webhooks/netopia/ipn` |

Mobile deep link after return uses scheme `padeliz-app://` (see frontend app).

### Production only

- `MAIL_PASSWORD` — Google Workspace App Password for `noreply@padeliz.ro`

Staging mail uses `MAIL_MAILER=log` (no SMTP password).

## Managed databases

- Allow the App Platform app / VPC in MySQL and Valkey **Trusted Sources**.
- Prefer **private** hostnames when the app and databases share a VPC.

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
