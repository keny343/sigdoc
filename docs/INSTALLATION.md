# Installation — SIGDoc

## Requirements

- PHP 7.4+ (8.x recommended) with PDO MySQL, sessions, mbstring
- MySQL 5.7+ / 8.x with spatial support
- Composer
- Apache/Nginx **or** `php -S` for local smoke tests
- SMTP account for 2FA / notifications (optional for read-only demos)

## 1. Clone

```bash
git clone https://github.com/keny343/sigdoc.git
cd sigdoc
composer install
```

## 2. Configure secrets

```bash
cp includes/config.example.php includes/config.local.php
```

Edit `includes/config.local.php`:

- `db.*` — host, database, user, password
- `smtp.*` — mail for 2FA
- `api.tokens` — bearer tokens for `api/index.php` (dev)

**Never commit** `config.local.php`.

## 3. Database

1. Create an empty MySQL database
2. Import / recreate tables from your environment (see [DATABASE.md](./DATABASE.md))
3. Create an admin user with a bcrypt password hash

Boot helpers in `includes/db.php` create some support tables automatically when the app connects.

## 4. Run locally

**Option A — PHP built-in server**

```bash
php -S localhost:8080
```

Open `http://localhost:8080`.

**Option B — Apache/Nginx vhost** pointing at the project root (needed for nicer rewrites / `.htaccess`).

## 5. Redeploy (shared hosting)

Follow `INSTRUCOES_UPLOAD.md`, and:

1. Upload the project files (including the new UI: `includes/style.css`, `layout_*.php`, `auth/login.php`)
2. Upload `includes/config.local.php` separately
3. Ensure `uploads/`, `logs/`, `backups/` are writable
4. For PDF export, ensure FPDF is reachable (`vendor/fpdf/fpdf.php`)
5. Smoke-test `/auth/login.php` → painel → documentos → mapa

## 6. Smoke test

- [ ] Login (`auth/login.php`)
- [ ] Painel KPIs + charts
- [ ] Lista de documentos
- [ ] Adicionar / editar / visualizar documento
- [ ] Versões e histórico de movimentação
- [ ] Mapa
- [ ] 2FA (SMTP configurado)
- [ ] Export CSV

## Troubleshooting

| Symptom | Check |
|---------|--------|
| “missing config.local.php” | Copy from example |
| DB connection error | Host/user/pass/firewall; don’t print credentials |
| PDF export fails | FPDF path / fonts under `font/` |
| 2FA email not sent | SMTP app password, less-secure blocks, port 587 TLS |

Related: [SECURITY.md](./SECURITY.md), [ARCHITECTURE.md](./ARCHITECTURE.md).
