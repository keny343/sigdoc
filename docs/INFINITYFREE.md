# InfinityFree — deployment & smoke test (SIGDoc)

Goal: get a **working** demo on InfinityFree without committing secrets.

## Where things live

| Item | Location |
|------|----------|
| Schema to import | [`database/schema.sql`](../database/schema.sql) |
| Credentials on server | `includes/config.local.php` (**never** in Git) |
| Template | [`includes/config.example.php`](../includes/config.example.php) |
| PDO entry | `includes/db.php` → reads `config.local.php` via `includes/config.php` |

> Remote MySQL from your PC usually **fails** on InfinityFree (firewall). Use **phpMyAdmin** in the hosting panel for import and DB checks.

---

## Deployment checklist

### 1. Create MySQL database

1. Client Area → **MySQL Databases**
2. Create DB (e.g. `if0_XXXX_sigdoc`) if needed
3. Note **Host**, **Database name**, **Username**, **Password**

### 2. Import schema

1. Open **phpMyAdmin**
2. Select the database
3. **Import** → choose `database/schema.sql` → **Go**

If `POINT` / `POLYGON` / `SPATIAL` fails, the host may have spatial disabled. Import without `acessos_geograficos` / `limites_geograficos` if needed — core app still works; map features are limited.

### 3. Configure `config.local.php`

On your machine (local copy is gitignored):

```bash
cp includes/config.example.php includes/config.local.php
```

Fill DB (and SMTP if using email 2FA):

```php
'db' => [
    'host' => 'sqlXXX.infinityfree.com',  // from panel — NOT localhost
    'port' => 3306,
    'name' => 'if0_XXXX_sigdoc',
    'user' => 'if0_XXXX',
    'pass' => 'YOUR_MYSQL_PASSWORD',
    'charset' => 'utf8mb4',
],
```

Upload **only** via File Manager / FTP. **Never** commit this file.

### 4. Upload application files

Upload the PHP app (exclude `includes/config.local.php` from git; include it on the server separately).

Writable folders (create if missing, chmod as allowed by host):

- `uploads/`
- `logs/`
- `backups/`

### 5. Seed login (after schema import)

| Email | Password | Role |
|-------|----------|------|
| `admin@sigdoc.local` | `Admin@123` | admin |
| `gestor@sigdoc.local` | `Admin@123` | gestor |

Change passwords after first successful login.

---

## Smoke test (required before calling it “deployed”)

Work through in order. Stop and fix if a step fails.

| # | Check | How |
|---|--------|-----|
| 1 | PDO / DB | Login page loads without DB error; wrong password shows auth error (not connection dump) |
| 2 | Login | `…/auth/login.php` with `admin@sigdoc.local` |
| 3 | Dashboard | `painel.php` loads KPIs / navigation |
| 4 | Documents | List + open a document |
| 5 | Uploads | Add/attach a file under allowed types; file appears; no PHP execution under `uploads/` |
| 6 | Permissions | Non-admin / lower role cannot reach admin-only actions |
| 7 | 2FA | For confidential/secret flow: OTP email path works (SMTP must be configured) |
| 8 | API | Authenticated call to REST endpoint with token from config / `usuariosapi` |
| 9 | Map | `mapa.php` loads (limited if spatial tables skipped) |

Optional follow-ups: export CSV/PDF, movement history, document versions.

---

## Security reminders

- Rotate any MySQL / SMTP / API credentials that ever appeared in public history
- Keep `includes/config.local.php` gitignored and off screenshots
- Known gaps (GET mutations, etc.): see [`SECURITY.md`](./SECURITY.md)

## Local vs hosting

| Environment | DB host tip |
|-------------|-------------|
| Local XAMPP/WAMP | Often `127.0.0.1` |
| InfinityFree | Host from panel (`sqlXXX.infinityfree.com`) |
