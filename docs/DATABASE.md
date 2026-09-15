# Database — SIGDoc

## Engine

**MySQL** via PDO, with **spatial** functions for document locations (`POINT`, `ST_Distance_Sphere`, etc.).

## Schema sources

There is **no single `database.sql` dump** in the repository yet (roadmap item).

Schema is established by:

- production / hosting database (primary source of truth historically)
- runtime `CREATE TABLE IF NOT EXISTS` / `ALTER` in:
  - `includes/db.php` — `acessos`, `permissoes`, `perfil_permissoes`
  - `documentos/adicionar.php` — `metadados`
  - `includes/webhook.php` — `webhooks`
  - `auth/configurar_2fa.php` — 2FA columns on `usuarios`
  - API helpers — `usuariosapi`, `categorias`, …

## Core tables (logical model)

```text
usuarios
  ├── perfil (admin|gestor|colaborador|visitante|…)
  ├── 2FA fields (email OTP)
  └── optional geo fields

documentos
  ├── classification / estado / sector / priority
  ├── file path
  ├── localizacao (POINT)
  ├── documento_versoes
  ├── movimentacao
  └── metadados

acessos                          # login/logout audit
tentativas_acesso_sigiloso       # sensitive access attempts
permissoes + perfil_permissoes   # permission catalog
usuariosapi                      # API accounts / tokens
notificacoes
webhooks
```

## Access model

Documents carry an **access category**. Combined with the user **role** (and 2FA when required), the app decides list/view/edit rights.

Permission keys (examples): `documentos.ver`, `documentos.criar`, `usuarios.editar`, `auditoria.ver`, …

## Export / reporting

Exports read from the documents domain filtered by the caller’s visibility rules (`exportar_documentos.php`).

## Roadmap

- Commit a canonical `database/schema.sql` generated from production
- Document indexes for search + spatial queries
- Complete group-based ACL tables if group RBAC is productized
