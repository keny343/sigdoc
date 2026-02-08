# SIGDoc - Instruções de Upload para InfinityFree

## Conteúdo para fazer upload

Faça upload de **toda** a pasta do projeto para o `htdocs` do InfinityFree.

### Estrutura obrigatória (na raiz)

```
├── index.html          ← Página React (landing)
├── index.php           ← Redireciona para /
├── serve.php           ← Serve JS/CSS com MIME correto
├── favicon.svg
├── .htaccess
│
├── assets/
│   ├── .htaccess
│   ├── index-1-NR7_7R.js
│   └── index-TzBenqMC.css
│
├── api/
├── auth/
├── documentos/
├── usuarios/
├── includes/
├── vendor/
├── font/
├── uploads/
├── backups/            (pasta vazia ou com backups)
├── logs/               (pasta com ou sem logs)
│
└── Ficheiros PHP na raiz:
    backup_system.php, config_email.php, config_ssl.php,
    conexao.php, exportar_documentos.php, inserir_documento.php,
    listar_arquivos.php, listar_documentos.php, login_api.php,
    cadastro_api.php, mapa.php, painel.php, meu_token_api.php,
    arquivo_acao.php, webhooks_admin.php, webhooks_login.php, api_rest.php
```

### NÃO enviar (opcional)

- `frontend/` – código fonte React (só necessário se quiser editar no futuro)
- `INSTRUCOES_UPLOAD.md` – pode excluir
- `composer.json`, `composer.lock` – opcional

---

## URLs após o deploy

- **Página inicial:** https://seu-dominio.com/ ou https://seu-dominio.com/#/
- **Login:** https://seu-dominio.com/auth/login.php
- **Painel:** Após login

---

## Verificações

1. A pasta `assets` tem os 2 ficheiros: `index-1-NR7_7R.js` e `index-TzBenqMC.css`
2. O `.htaccess` está na raiz
3. O `serve.php` está na raiz
4. A base de dados está configurada em `includes/db.php`
