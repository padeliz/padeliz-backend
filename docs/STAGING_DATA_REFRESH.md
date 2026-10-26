# On-demand staging data refresh (prod → staging)

**Do not** run this on every deploy. Use when you need realistic data for QA.

## Safety rules

1. Staging must keep **sandbox** Netopia keys and `MAIL_MAILER=log`.
2. Never point staging apps at production MySQL/Valkey/Spaces.
3. Treat refreshed staging as **production PII** (access control, no public dumps).
4. Prefer anonymization for long-term process (future improvement).

## MySQL

1. In DigitalOcean: create a **backup/fork** of `padeliz-mysql-prod` or download a logical dump with `doctl` / mysqldump over trusted network.
2. Restore into `padeliz-mysql-staging` (destructive: wipe staging DB first).
3. Confirm staging backend still uses staging DB credentials only.
4. Run `php artisan backend:refresh --deploy` on staging (or trigger a no-op image redeploy) so caches/queues restart cleanly.

Example outline (adjust users/hosts from DO UI):

```bash
# On a secure admin machine with DO DB access
mysqldump -h <PROD_HOST> -u <USER> -p --single-transaction --routines padeliz > prod.sql
mysql -h <STAGING_HOST> -u <USER> -p -e "DROP DATABASE IF EXISTS padeliz; CREATE DATABASE padeliz;"
mysql -h <STAGING_HOST> -u <USER> -p padeliz < prod.sql
```

## Spaces

Sync objects from `padeliz-prod` → `padeliz-staging` (AWS CLI / rclone with Spaces endpoint `https://fra1.digitaloceanspaces.com`):

```bash
aws s3 sync s3://padeliz-prod s3://padeliz-staging \
  --endpoint-url https://fra1.digitaloceanspaces.com \
  --delete
```

Use **staging** Spaces keys. `--delete` makes staging a mirror (destructive to staging-only objects).

## After refresh checklist

- [ ] Staging `APP_URL` / CORS still staging hosts
- [ ] `NETOPIA_IS_LIVE=false` and sandbox keys
- [ ] `MAIL_MAILER=log`
- [ ] Login smoke on `https://app.staging.padeliz.ro` and manager staging
- [ ] Sample upload still hits `padeliz-staging` bucket
