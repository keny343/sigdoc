# SIGDoc — Document Management and Access Control Platform

PHP · MySQL · Leaflet · PHPMailer · REST API · PT/EN

---

## What it is

**SIGDoc** is a **document management and access-control platform** for organizations that need to:

- register and classify documents
- control who can see confidential material
- track workflow / movement history
- locate documents on a map
- export inventories (CSV / PDF)
- notify users by email (including email-based 2FA)

It is **not** “just a PHP CRUD”. It demonstrates backend security concerns, RBAC concepts, geospatial data, exports, and API integration on a classic LAMP-style stack.

## Problem

Paper and shared folders do not provide:

- clear access categories (public → secret)
- audit of who opened sensitive files
- geospatial context
- a single dashboard for pending work

## Solution

A web platform with:

| Capability | Implementation |
|------------|----------------|
| Document lifecycle | Create, edit, version, archive, history |
| Access control | Roles + classification (`publico` … `secreto`) |
| Step-up auth | Email OTP (2FA) for sensitive documents |
| Geospatial | MySQL spatial + Leaflet map |
| Integration | REST API + webhooks |
| i18n | Portuguese / English |

## Live demo / hosting

| | URL |
|--|-----|
| **App** | https://sigdoc-1fsj.onrender.com/ |
| **Login** | https://sigdoc-1fsj.onrender.com/auth/login.php |
| **Health** | https://sigdoc-1fsj.onrender.com/health.php |

Demo seed (change after first login): `admin@sigdoc.local` / `Admin@123`

Stack in production: **Render** (Docker · PHP 8.2 · Apache) + **Aiven MySQL** (TLS).  
Guide: [`docs/RENDER.md`](./docs/RENDER.md) · Schema: [`database/install_aiven.sql`](./database/install_aiven.sql)

Also works on InfinityFree / shared hosting / VPS (PHP + MySQL).

### Render (auto-deploy from GitHub)

1. Create MySQL on Aiven (or other) and run [`database/install_aiven.sql`](./database/install_aiven.sql)  
2. On Render: **Web Service** → repo `keny343/sigdoc` → branch with Dockerfile → runtime **Docker**  
3. Set `SIGDOC_DB_*` including `SIGDOC_DB_SSL=1`  
4. Health check: `/health.php`  
5. Push → Render rebuilds automatically  

Blueprint: [`render.yaml`](./render.yaml) · Dockerfile: [`Dockerfile`](./Dockerfile)

### Shared hosting checklist (InfinityFree, etc.)

1. Create MySQL database + user on the host  
2. Import [`database/schema.sql`](./database/schema.sql) via phpMyAdmin — guide: [`docs/INFINITYFREE.md`](./docs/INFINITYFREE.md)  
3. Upload project files (exclude `.git`, local secrets, and large junk)  
4. Copy `includes/config.example.php` → `includes/config.local.php` **on the server only** and fill MySQL, SMTP, API tokens  
5. **Rotate** any credentials that were previously committed or leaked  
6. Ensure `uploads/`, `logs/`, and `backups/` are writable by PHP  
7. Point the domain/document root at the project folder  
8. Open `/auth/login.php` — seed: `admin@sigdoc.local` / `Admin@123` (change immediately) 

Until redeployed, run locally — [`docs/INSTALLATION.md`](./docs/INSTALLATION.md).

### UI refresh

Sidebar product shell, slate + teal tokens, dense KPIs/tables, institutional login, and document CRUD pages (add/edit/view/versions/history) on the shared layout — same craft as Mara & Lu admin, distinct SIGDoc brand.

## Architecture

```text
Browser (Bootstrap / Leaflet / SPA landing)
        │
        ▼
PHP application (sessions + RBAC helpers)
        │
        ├── MySQL (documents, users, audit, spatial)
        ├── PHPMailer (notifications + 2FA codes)
        └── File storage (uploads/)
```

Details: [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md)


## Features

- Document CRUD with metadata, priority, sector, workflow state
- Document versions and movement history
- Roles: admin, gestor, colaborador, visitante
- Access categories with stricter rules for confidential/secret
- Email 2FA for sensitive access
- Dashboard (`painel.php`)
- Map (`mapa.php`) with clustering / routing aids
- CSV & PDF export
- REST API (`api/`, `api_rest.php`)
- Webhooks admin
- PT / EN language switch

## Tech stack

- **Backend:** PHP, PDO/MySQL, Composer
- **Mail:** PHPMailer
- **PDF:** FPDF
- **Maps:** Leaflet (+ plugins)
- **UI:** Bootstrap 5 + SIGDoc design system (`includes/style.css`, app shell)
- **Frontend landing:** prebuilt React assets (`assets/`)
- **i18n:** `includes/lang_*.php`

## Security

- `password_hash` / `password_verify`
- PDO prepared statements
- Session cookie hardening helpers (`config_ssl.php`)
- Security headers (CSP-related / frame / XSS headers)
- Role + classification checks; sensitive-access audit table
- **Secrets moved out of source** → `includes/config.local.php` (gitignored)

See [`docs/SECURITY.md`](./docs/SECURITY.md) — including known debt and rotation guidance.

> **Important:** Older commits may still contain credentials. Rotate DB password, Gmail app password, and API tokens after this change.

## Documentation

| Doc | Topic |
|-----|--------|
| [ARCHITECTURE.md](./docs/ARCHITECTURE.md) | System design |
| [SECURITY.md](./docs/SECURITY.md) | Auth, ACL, secrets |
| [DATABASE.md](./docs/DATABASE.md) | Tables & spatial |
| [INSTALLATION.md](./docs/INSTALLATION.md) | Local & hosting setup |
| [api/README.md](./api/README.md) | REST overview |

## Quick start

```bash
git clone https://github.com/keny343/sigdoc.git
cd sigdoc
composer install

# Secrets (required)
cp includes/config.example.php includes/config.local.php
# edit includes/config.local.php with your MySQL + SMTP + API tokens

# Serve with Apache/Nginx + PHP, or:
php -S localhost:8080
```

Full steps: [`docs/INSTALLATION.md`](./docs/INSTALLATION.md)

## Screenshots

Add real captures under [`screenshots/`](./screenshots/) (login, dashboard, document list, document detail, map, 2FA). Folder exists; images still to capture from the live/local instance.

## Challenges & learnings

- Designing document classification and role checks on a classic PHP stack
- Safer secret management (`config.local.php`) without depending on a live host
- Spatial queries for map features
- Dual surfaces: server-rendered PHP UI + SPA landing assets

## Roadmap

- [ ] CSRF tokens on forms (**P0**)
- [ ] Rate limiting on login and 2FA (**P0**)
- [ ] Enforce `exigir_permissao()` across UI pages
- [ ] Full user admin (list/edit/disable)
- [ ] Replace static API bearer list with JWT / per-user tokens only
- [ ] Capture and commit real screenshots

## Author

**Adnírcio Inocêncio** — Software / Full Stack Developer  
GitHub: [keny343](https://github.com/keny343)

Related portfolio flagship: [colegio-mara-lu](https://github.com/keny343/colegio-mara-lu)
