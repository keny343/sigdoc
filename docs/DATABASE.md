# Database — SIGDoc

## Engine

**MySQL / MariaDB** via PDO, with optional **spatial** types (`POINT`, `ST_GeomFromText`, `ST_Distance_Sphere`) for document locations.

## Canonical schema

Import this file into an empty database (phpMyAdmin on InfinityFree):

**[`database/schema.sql`](../database/schema.sql)**

It creates:

| Table | Role |
|-------|------|
| `usuarios` | Accounts + 2FA columns |
| `documentos` | Documents + optional `POINT` location |
| `documento_versoes` | File versions |
| `movimentacao` | Workflow / audit trail |
| `metadados` | Keywords and extra metadata |
| `acessos` | Login/logout audit |
| `tentativas_acesso_sigiloso` | Sensitive access attempts |
| `permissoes` / `perfil_permissoes` | Permission catalog (also seeded at runtime by `includes/db.php`) |
| `grupos` / `usuario_grupos` / `grupo_permissoes` | Optional group ACL |
| `usuariosapi` | API accounts / tokens |
| `webhooks` | Outbound webhooks |
| `notificacoes` | In-app notifications |
| `acessos_geograficos` / `limites_geograficos` | Optional geo helpers |

Seed users (change after first login): see [`INFINITYFREE.md`](./INFINITYFREE.md).

## Runtime bootstraps

Besides `schema.sql`, the app may `CREATE TABLE IF NOT EXISTS` / `ALTER` for:

- `acessos`, `permissoes`, `perfil_permissoes` — `includes/db.php`
- `metadados` — `documentos/adicionar.php`
- `webhooks` — `includes/webhook.php`
- 2FA columns on `usuarios` — `auth/configurar_2fa.php`

## Access model

Documents carry an **access category** (`publico` → `secreto`). Combined with the user **role** (and 2FA when required), the app decides list/view/edit rights.

## InfinityFree

Full checklist: [`INFINITYFREE.md`](./INFINITYFREE.md)

Connection settings live only in **`includes/config.local.php`** (gitignored).
