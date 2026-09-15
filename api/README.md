# SIGDoc REST API

JSON API for integrating external systems with the document platform.

## Authentication

Send a Bearer token:

```http
Authorization: Bearer <your-token>
```

Tokens are configured in **`includes/config.local.php`** under `api.tokens` (never commit real tokens).

Prefer per-user tokens stored in `usuariosapi` when using `api_rest.php`.

## Base

Router: `api/index.php?endpoint=...`

### Documents

- `GET ?endpoint=documentos` — list (supports `limit`, `offset`, `search`, `estado`, …)
- `POST ?endpoint=documentos` — create
- Other verbs as implemented in `api/endpoints/documentos.php`

### Users / stats / movements

See endpoint files under `api/endpoints/` and the switch in `api/index.php`.

## Example

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-host/api/?endpoint=documentos&limit=10"
```

## Notes

- CORS is currently permissive (`*`) — tighten for production.
- Some endpoint files exist but may not be registered in the main router switch — check `api/index.php` before relying on them.
- Parallel richer API: `api_rest.php`.

Security context: [`docs/SECURITY.md`](../docs/SECURITY.md).
