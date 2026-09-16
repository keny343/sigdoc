# Deploy SIGDoc on Render (GitHub auto-deploy)

SIGDoc is a classic **PHP + MySQL** app. Render runs the PHP container from this repo’s `Dockerfile`. MySQL must be provisioned **separately** (Render’s managed DB is PostgreSQL).

## Architecture on Render

```text
GitHub (keny343/sigdoc)
        │  push
        ▼
 Render Web Service (Docker · Apache · PHP 8.2)
        │  SIGDOC_DB_* + SIGDOC_DB_SSL=1
        ▼
 Aiven MySQL (TLS required)
```

**Live demo:** https://sigdoc-1fsj.onrender.com/  
**Login:** https://sigdoc-1fsj.onrender.com/auth/login.php  

Auto-deploy: connect the GitHub repo once → every push to the linked branch rebuilds and redeploys.

## 1. Create a MySQL database (Aiven)

1. Create a MySQL service on [Aiven](https://aiven.io)  
2. Allow external IPs (or `0.0.0.0/0` for portfolio demo)  
3. Import schema with [`database/install_aiven.sql`](../database/install_aiven.sql)  
   (or run `node scripts/apply-schema.mjs` locally using a gitignored `.env`)  
4. Note **host**, **port**, **database** (`defaultdb`), **user**, **password**

Seed users (change after first login):

| Email | Password | Role |
|-------|----------|------|
| `admin@sigdoc.local` | `Admin@123` | admin |
| `gestor@sigdoc.local` | `Admin@123` | gestor |

## 2. Deploy the web service

### Option A — Blueprint (`render.yaml`)

1. Push this repository to GitHub (including `Dockerfile`, `render.yaml`)  
2. [Render Dashboard](https://dashboard.render.com) → **New** → **Blueprint**  
3. Select `keny343/sigdoc`  
4. Fill `sync: false` env vars when prompted  

### Option B — Manual Web Service

1. **New** → **Web Service** → connect GitHub repo `sigdoc`  
2. Runtime: **Docker**  
3. Dockerfile path: `./Dockerfile`  
4. Instance: Free (or Starter if you need a persistent disk)  
5. Health check path: `/health.php`  
6. Add environment variables (below)

## 3. Environment variables

| Variable | Required | Example |
|----------|----------|---------|
| `SIGDOC_DB_HOST` | yes | `xxxxx.mysql.database.azure.com` / Railway host |
| `SIGDOC_DB_PORT` | no (default 3306) | `3306` |
| `SIGDOC_DB_NAME` | yes | `sigdoc` |
| `SIGDOC_DB_USER` | yes | `sigdoc_user` |
| `SIGDOC_DB_PASS` | yes | *(secret)* |
| `SIGDOC_DB_CHARSET` | no | `utf8mb4` |
| `SIGDOC_DB_SSL` | **yes for Aiven** | `1` |
| `SIGDOC_SMTP_HOST` | for 2FA email | `smtp.gmail.com` |
| `SIGDOC_SMTP_USER` | for 2FA | |
| `SIGDOC_SMTP_PASS` | for 2FA | app password |
| `SIGDOC_SMTP_PORT` | no | `587` |
| `SIGDOC_SMTP_SECURE` | no | `tls` |
| `SIGDOC_API_TOKENS` | API demos | `token1,token2` |

**Never** put these in Git. Locally continue using `includes/config.local.php`.

## 4. Smoke test after deploy

1. `https://<service>.onrender.com/health.php` → `"db":"up"`  
2. `https://<service>.onrender.com/auth/login.php`  
3. Login → painel → documentos → upload → mapa → API  

Full checklist: [`INFINITYFREE.md`](./INFINITYFREE.md) smoke table (same app behaviour).

## 5. Uploads / persistence

On the **free** web tier the filesystem is ephemeral: uploaded files can disappear on redeploy.

For a portfolio demo this is often acceptable. For a durable demo:

- Add a **persistent disk** mounted on `/var/www/html/uploads` (paid), or  
- Move files to object storage later (S3-compatible)

## 6. Local Docker check (optional)

```bash
docker build -t sigdoc .
docker run --rm -p 8080:8080 \
  -e PORT=8080 \
  -e SIGDOC_DB_HOST=host.docker.internal \
  -e SIGDOC_DB_NAME=sigdoc \
  -e SIGDOC_DB_USER=sigdoc_user \
  -e SIGDOC_DB_PASS=yourpass \
  sigdoc
```

Open `http://localhost:8080/health.php`.

## 7. Free-tier cold starts

Render free web services sleep after idle time. First request after sleep can take ~30–60s — same pattern as the Mara & Lu backend on Render.

## Security

- Secrets only in Render env  
- Rotate any credentials that ever appeared in git history  
- Remaining gap: state-changing GETs — see [`SECURITY.md`](./SECURITY.md)
