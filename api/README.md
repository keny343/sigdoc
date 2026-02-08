# 🔗 API REST - SIGDoc

## 📋 Visão Geral

A API REST do SIGDoc permite integração externa com o sistema de gestão documental. Todas as respostas são em formato JSON.

## 🔐 Autenticação

A API utiliza autenticação por token no header `Authorization`:

```
Authorization: Bearer sigdoc_api_2025
```

### Tokens Válidos:
- `sigdoc_api_2025` - Token principal
- `admin_token_123` - Token administrativo

## 📚 Endpoints Disponíveis

### 1. Documentos

#### GET `/api/?endpoint=documentos`
Lista documentos com paginação e filtros.

**Parâmetros:**
- `limit` (opcional): Número de itens por página (padrão: 50)
- `offset` (opcional): Deslocamento para paginação (padrão: 0)
- `search` (opcional): Busca por título ou tipo
- `estado` (opcional): Filtro por estado
- `categoria` (opcional): Filtro por categoria de acesso

**Exemplo:**
```bash
curl -H "Authorization: Bearer sigdoc_api_2025" \
     "http://localhost/sistema-documental/api/?endpoint=documentos&limit=10&search=relatorio"
```

#### POST `/api/?endpoint=documentos`
Cria um novo documento.

**Body (JSON):**
```json
{
    "titulo": "Relatório Mensal",
    "tipo": "relatorio",
    "setor": "Financeiro",
    "categoria_acesso": "privado",
    "area_origem": "financeiro@empresa.com",
    "area_destino": "gerencia@empresa.com",
    "prioridade": "alta",
    "estado": "pendente"
}
```

#### PUT `/api/?endpoint=documentos&id=123`
Atualiza um documento existente.

#### DELETE `/api/?endpoint=documentos&id=123`
Exclui um documento.

### 2. Estatísticas

#### GET `/api/?endpoint=estatisticas`
Retorna estatísticas gerais do sistema.

**Exemplo:**
```bash
curl -H "Authorization: Bearer sigdoc_api_2025" \
     "http://localhost/sistema-documental/api/?endpoint=estatisticas"
```

**Resposta:**
```json
{
    "success": true,
    "data": {
        "geral": {
            "total_documentos": 150,
            "total_usuarios": 25,
            "total_movimentacoes": 450,
            "documentos_mes_atual": 12
        },
        "por_estado": {
            "pendente": 30,
            "em_analise": 15,
            "aprovado": 95,
            "arquivado": 10
        },
        "por_categoria": {
            "publico": 80,
            "privado": 45,
            "confidencial": 20,
            "secreto": 5
        },
        "top_setores": [
            {"setor": "Financeiro", "total": 25},
            {"setor": "RH", "total": 20}
        ],
        "ultimas_movimentacoes": [...],
        "alertas": [...]
    }
}
```

### 3. Usuários

#### GET `/api/?endpoint=usuarios`
Lista usuários do sistema.

### 4. Movimentação

#### GET `/api/?endpoint=movimentacao`
Lista histórico de movimentações.

## 📊 Códigos de Status HTTP

- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Dados inválidos
- `401` - Token inválido
- `404` - Recurso não encontrado
- `405` - Método não permitido
- `500` - Erro interno do servidor

## 🔧 Exemplos de Uso

### JavaScript (Fetch API)
```javascript
// Listar documentos
fetch('http://localhost/sistema-documental/api/?endpoint=documentos', {
    headers: {
        'Authorization': 'Bearer sigdoc_api_2025'
    }
})
.then(response => response.json())
.then(data => console.log(data));

// Criar documento
fetch('http://localhost/sistema-documental/api/?endpoint=documentos', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer sigdoc_api_2025',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        titulo: 'Novo Documento',
        tipo: 'memorando',
        setor: 'Administrativo',
        categoria_acesso: 'publico'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Python (requests)
```python
import requests

# Configurar headers
headers = {
    'Authorization': 'Bearer sigdoc_api_2025',
    'Content-Type': 'application/json'
}

# Listar documentos
response = requests.get(
    'http://localhost/sistema-documental/api/?endpoint=documentos',
    headers=headers
)
documentos = response.json()

# Criar documento
novo_doc = {
    'titulo': 'Documento via Python',
    'tipo': 'oficio',
    'setor': 'TI',
    'categoria_acesso': 'privado'
}

response = requests.post(
    'http://localhost/sistema-documental/api/?endpoint=documentos',
    headers=headers,
    json=novo_doc
)
resultado = response.json()
```

### PHP (cURL)
```php
// Listar documentos
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sistema-documental/api/?endpoint=documentos');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer sigdoc_api_2025'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$documentos = json_decode($response, true);
```

## 🚀 Configuração

1. **Habilitar CORS** (já configurado)
2. **Configurar tokens** em `api/index.php`
3. **Ajustar permissões** conforme necessário

## 📝 Notas Importantes

- Todos os endpoints retornam JSON
- Paginação automática em listas grandes
- Validação de dados em operações de escrita
- Logs de movimentação automáticos
- Tratamento de erros consistente

## 🔒 Segurança

- Autenticação obrigatória
- Validação de dados de entrada
- Sanitização de parâmetros
- Headers de segurança configurados
- Logs de auditoria mantidos 