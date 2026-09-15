# Architecture — SIGDoc

## Positioning

**Document Management and Access Control Platform** built on PHP + MySQL, with geospatial visualization and email-assisted step-up authentication.

## High-level view

```text
┌──────────────────────────────────────────┐
│  Clients                                 │
│  - Server-rendered PHP UI (Bootstrap)    │
│  - Leaflet map                           │
│  - Prebuilt SPA landing (assets/)        │
│  - REST consumers (Bearer token)         │
└──────────────────┬───────────────────────┘
                   │ HTTP
                   ▼
┌──────────────────────────────────────────┐
│  Application layer                       │
│  auth/ · documentos/ · usuarios/         │
│  painel.php · mapa.php · exportar_*.php  │
│  api/ · api_rest.php · webhooks_*        │
│  includes/auth.php · db.php · lang.php   │
└──────────────────┬───────────────────────┘
                   │
     ┌─────────────┼─────────────┐
     ▼             ▼             ▼
  MySQL         PHPMailer     uploads/
  (+ spatial)   (2FA/mail)    files
```

## Modules

| Module | Path | Responsibility |
|--------|------|----------------|
| Auth & sessions | `includes/auth.php`, `auth/` | Login session, roles, 2FA helpers |
| Documents | `documentos/` | CRUD, versions, history, classification |
| Dashboard | `painel.php` | Counts, alerts, recent activity |
| Map | `mapa.php`, `includes/geospatial.php` | Document geolocation |
| Export | `exportar_documentos.php` | CSV / PDF |
| API | `api/`, `api_rest.php` | External integration |
| i18n | `includes/lang.php`, `lang_pt.php`, `lang_en.php` | PT/EN |
| Config | `includes/config.php` | Loads gitignored local secrets |

## Configuration strategy

```text
config.example.php  →  committed template
config.local.php    →  local/host secrets (gitignored)
config.php          →  loader used by db/auth/email/api
```

## Design notes

- Classic **multi-page PHP** app for operational screens (good fit for shared hosting).
- **SPA landing** shipped as built assets for public entry.
- Permissions catalog (`permissoes` / `perfil_permissoes`) coexists with legacy `perfil` role checks — UI still relies heavily on role helpers; fine-grained `pode()` is used more in REST paths.
- Geospatial features use MySQL spatial types + Leaflet for UX.

See [SECURITY.md](./SECURITY.md) and [DATABASE.md](./DATABASE.md).
