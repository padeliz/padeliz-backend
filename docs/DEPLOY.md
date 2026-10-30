# Deploy (DigitalOcean App Platform)

## Pipeline

| Trigger | Action |
|---|---|
| Pull request | CI tests only |
| Push to `main` | Test → build/push DOCR → live-spec reconcile **staging** |
| GitHub Release published | Build/push DOCR → live-spec reconcile **production** (requires your approval) |

Images: `registry.digitalocean.com/padeliz/padeliz-backend`  
Deploy uses an **immutable image digest** (not floating `latest`).

CI builds `linux/amd64` only with provenance/SBOM attestations disabled on the DOCR push so the tag points at a single image manifest.

Deploy uses `scripts/ci/deploy-app-platform.sh`: fetch the **live** App Platform spec; pin `web` / `worker` / `scheduler` / `migrate` to DOCR `padeliz/padeliz-backend` @ this build’s digest; if any of those components are missing, add them from the checked-in contract (`.do/app.*.yaml`) without copying contract envs or databases; `doctl apps update` and wait for `ACTIVE`. Encrypted secrets and attached DBs stay as configured in DO — git specs are never applied wholesale.

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
| `web` | `deploy:optimize` then nginx + php-fpm |
| `worker` | `deploy:optimize` then `queue:work redis` |
| `scheduler` | `deploy:optimize` then `schedule:work` |
| `migrate` (PRE_DEPLOY) | `deploy:release` (`migrate --force` + `queue:restart`) |

### Why not generate docs / Laravel caches in PRE_DEPLOY

App Platform PRE_DEPLOY runs in an ephemeral container. Files written there (e.g. `storage/api-docs`, `bootstrap/cache`) never reach web/worker/scheduler.

| Artifact | Where it is produced |
|---|---|
| OpenAPI (`storage/api-docs/api-docs-*.json`) | Docker **image build** (`php artisan api:docs:generate-all`) |
| `config` / `route` / `event` / `view` caches | Each runtime container via **`deploy:optimize`** (needs live App Platform env) |
| Migrations + queue restart signal | **`deploy:release`** in PRE_DEPLOY (shared MySQL / Redis) |

Local/dev: `php artisan backend:refresh` still clears optimize state and regenerates API docs on the same container you use.

## Rollback

Redeploy a previous digest (DO App Platform → Deployments → redeploy, or re-run the GitHub Actions deploy workflow on an older commit / release).

## Health

`GET https://backend…/up` → `ok`
