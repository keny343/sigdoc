# Security — SIGDoc

## Goals

Protect document confidentiality while remaining deployable on shared PHP hosting.

## Controls implemented

| Control | Where |
|---------|--------|
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

1. Copy `includes/config.example.php` → `includes/config.local.php`
2. Fill DB, SMTP, and API tokens
3. Upload `config.local.php` to the server **without** committing it
4. **Rotate** any credentials that ever appeared in public git history:
   - MySQL password (InfinityFree panel)
   - Gmail **app password**
   - API bearer tokens

## Known risks / debt (honest portfolio note)

| Issue | Status | Mitigation path |
|-------|--------|-----------------|
| `auth/login.php` missing in tree | Broken entry in some flows | Restore login page / unify SPA login |
| Static API bearer tokens | Weak for production | Prefer `usuariosapi` tokens or JWT |
| CORS `*` on API | Broad | Restrict origins |
| CSRF tokens absent on forms | Gap | Add CSRF middleware |
| `arquivo_acao.php` path ops | High risk if exposed | Auth + path allowlist |
| Fine-grained `exigir_permissao()` underused in UI | Partial RBAC | Enforce on every mutating page |
| Uploads under web root | Common shared-host pattern | Deny script execution in `uploads/` |

Documenting these gaps is intentional: security maturity includes knowing what remains.

## Pre-push checklist

```bash
git status
git diff
```

Search for: `password`, `SMTP_PASS`, `sql106`, `gmail.com`, `Bearer`, `api_token`.

Never stage `includes/config.local.php`.
