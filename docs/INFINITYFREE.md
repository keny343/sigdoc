# InfinityFree — base de dados SIGDoc

## Onde está a BD?

| Item | Local |
|------|--------|
| Schema SQL para importar | [`database/schema.sql`](../database/schema.sql) |
| Credenciais no servidor | `includes/config.local.php` (**não** vai no Git) |
| Template | `includes/config.example.php` |

A app liga-se via PDO em `includes/db.php` → lê `config.local.php`.

> Ligação remota a partir do PC costuma **falhar** no InfinityFree (DNS/firewall). Usa sempre o **phpMyAdmin** do painel.

## Passo a passo

### 1. MySQL no painel InfinityFree

1. Client Area → **MySQL Databases**
2. Cria a base (ex.: `if0_XXXX_sigdoc`) se ainda não existir
3. Anota:
   - **Host** (ex.: `sql106.infinityfree.com`)
   - **Database name**
   - **Username**
   - **Password**

### 2. Importar o schema

1. Abre **phpMyAdmin** (link no painel MySQL)
2. Seleciona a base `if0_XXXX_sigdoc` na esquerda
3. Separador **Import** → escolhe `database/schema.sql`
4. **Go** / Executar

Se der erro em `POINT` / `POLYGON` / `SPATIAL`, o host pode ter spatial desactivado — nesse caso contacta o suporte ou importa sem as tabelas `acessos_geograficos` e `limites_geograficos` (o núcleo da app continua a funcionar; mapa fica limitado).

### 3. `config.local.php` no servidor

No teu PC (já existe localmente) ou cria a partir do example:

```bash
cp includes/config.example.php includes/config.local.php
```

Preenche com os valores do painel:

```php
'db' => [
    'host' => 'sqlXXX.infinityfree.com',  // do painel — NÃO uses localhost
    'port' => 3306,
    'name' => 'if0_XXXX_sigdoc',
    'user' => 'if0_XXXX',
    'pass' => 'a-tua-password-mysql',
    'charset' => 'utf8mb4',
],
```

Faz upload de `includes/config.local.php` via File Manager / FTP para o hosting.  
**Nunca** commits este ficheiro.

### 4. Login inicial (seed)

Após importar `schema.sql`:

| Email | Password | Perfil |
|-------|----------|--------|
| `admin@sigdoc.local` | `Admin@123` | admin |
| `gestor@sigdoc.local` | `Admin@123` | gestor |

Altera as passwords depois do primeiro login. O segundo utilizador existe para o campo **área destino** (email) ao criar documentos.

### 5. Pastas graváveis

Garante permissão de escrita em:

- `uploads/`
- `logs/`
- `backups/`

### 6. Smoke test

1. `https://teu-dominio/auth/login.php`
2. Login com `admin@sigdoc.local`
3. Painel → Documentos → Mapa

## Segurança

As passwords MySQL/SMTP que estiveram no código público devem ser **rodadas** no painel InfinityFree e no Gmail (app password), e actualizadas só em `config.local.php` no servidor.
