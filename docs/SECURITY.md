# Security — SIGDoc

## Goals

Protect document confidentiality while remaining deployable on shared PHP hosting.

## Controls implemented

| Control | Where |
|---------|--------|
| CSRF on HTML form POSTs | `includes/csrf.php` + forms / handlers |
| Password hashing | `password_hash` / `password_verify` |
| SQL injection resistance | PDO prepared statements |
| Session hardening | `config_ssl.php` (Secure / HttpOnly / SameSite when HTTPS) |
| HTTP headers | Frame options, XSS, content-type, referrer, CSP-related helpers |
| Role checks | `is_admin()`, `is_gestor()`, … |
| Document classification | `publico` / `privado` / `confidencial` / `secreto` |
| Step-up auth | Email OTP 2FA for sensitive categories |
| Audit | `acessos`, `tentativas_acesso_sigiloso`, document movements |
| Secrets outside git | `includes/config.local.php` (gitignored) |

## Secrets management (required)

### Local / InfinityFree

1. Copy `includes/config.example.php` → `includes/config.local.php`
2. Fill DB, SMTP, and API tokens
3. Upload `config.local.php` to the server **without** committing it

### Render / Docker

Set `SIGDOC_DB_*` (and optional SMTP / API token) environment variables in the Render dashboard.  
No `config.local.php` on the server. See [`RENDER.md`](./RENDER.md).

### Rotation

**Rotate** any credentials that ever appeared in public git history:

- MySQL password
- Gmail **app password**
- API bearer tokens

## Known risks / debt (honest portfolio note)

| Issue | Status | Mitigation path |
|-------|--------|-----------------|
| CSRF tokens on state-changing HTML forms | **Done** | Session `_csrf` + `csrf_require()` |
| Rate limiting on login / 2FA OTP | **P0 gap** | Per-IP / per-account throttle + lockout |
| State-changing actions via GET (e.g. delete document, webhook toggle) | Gap | Convert to POST + CSRF |
| Static API bearer tokens in config | Weak for production | Prefer `usuariosapi` tokens or JWT |
| CORS `*` on API | Broad | Restrict origins |
| `arquivo_acao.php` path ops | High risk if exposed | Auth + path allowlist |
| Fine-grained `exigir_permissao()` underused in UI | Partial RBAC | Enforce on every mutating page |
| Uploads under web root | Common shared-host pattern | Deny script execution in `uploads/` |

Documenting these gaps is intentional: security maturity includes knowing what remains.

### Deployment blocker vs code debt

Go-live depends on **hosting steps** (MySQL + secrets + smoke test), not only code. Prefer [`RENDER.md`](./RENDER.md) for GitHub auto-deploy, or [`INFINITYFREE.md`](./INFINITYFREE.md) for shared hosting. CSRF and rate limits should be implemented next while the demo stays usable with strong passwords and short demo sessions.

## Pre-push checklist

```bash
git status
git diff
```

Search for: `password`, `SMTP_PASS`, `sql106`, `gmail.com`, `Bearer`, `api_token`.

Never stage `includes/config.local.php`.
