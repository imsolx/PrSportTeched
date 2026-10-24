# Deploy on Render + Supabase

This project can run on Render with Docker and use Supabase PostgreSQL.

## 1. Create Supabase Project

Create a Supabase project, then open:

```text
Project Settings -> Database -> Connection string
```

Use the pooled connection if available. It usually looks like:

```text
postgresql://postgres.PROJECT_REF:PASSWORD@aws-0-REGION.pooler.supabase.com:6543/postgres?sslmode=require
```

## 2. Create Render Web Service

Create a new Render Web Service from the GitHub repository.

Use:

```text
Language / Runtime: Docker
Branch: main
Dockerfile: ./Dockerfile
```

Render will build the PHP Apache image from `Dockerfile`.

## 3. Render Environment Variables

Set these in Render:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-render-service.onrender.com
PRSPORT_APP_URL=https://your-render-service.onrender.com
PRSPORT_TIMEZONE=Asia/Bangkok

DB_CONNECTION=pgsql
DB_SSLMODE=require
DATABASE_URL=postgresql://postgres.PROJECT_REF:PASSWORD@aws-0-REGION.pooler.supabase.com:6543/postgres?sslmode=require

PRSPORT_ADMIN_EMAIL=admin@rmutt.local
PRSPORT_ADMIN_PASSWORD=Temp@1234
```

You can use separate `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values instead of `DATABASE_URL`.

## 4. Verify

After deploy, open:

```text
https://your-render-service.onrender.com/server-check.php
https://your-render-service.onrender.com/deploy-check.php
```

`deploy-check.php` should show a PostgreSQL connection and table count.

## Notes

- PostgreSQL schema initialization runs on the first request of each container, then uses a local success marker in `storage/schema-*.ready`. Changes to `Database.php`, the PostgreSQL schema, or seed invalidate the marker automatically. Failed initialization is retried on the next request. After manually restoring or replacing a database, restart/redeploy the service to initialize a fresh container.
- PostgreSQL uses PDO emulated prepares for compatibility with Supabase transaction pooling. Values are still passed through PDO parameter binding.
- Render free web services sleep after inactivity.
- Render free filesystem is not persistent. Uploaded logos and exported reports can disappear after redeploys unless moved to Supabase Storage or another object storage provider.
- Keep `.env` out of Git. Configure production secrets in Render only.
