# Debug do Dashboard

## Problemas Comuns e Soluções

### 1. Não Consegue Adicionar Widget

**Sintomas:**
- Clica no widget do catálogo mas nada acontece
- Catálogo não abre
- Widget não aparece no grid

**Verificações:**

#### A. Abra o Console do Navegador (F12)
Procure por erros em vermelho. Os erros mais comuns:

```
1. Erro de API (400/500):
   - Verifique se as tabelas foram criadas corretamente
   - Execute: DESCRIBE dashboards; DESCRIBE dashboard_widgets; DESCRIBE widget_tipos;
   - Todas as colunas devem estar presentes

2. Erro "Cannot read property of undefined":
   - Dashboard não foi carregado
   - Verifique se existe um dashboard padrão

3. Erro de CORS:
   - Verifique se a API está rodando
   - URL correta: /api/dashboard/...
```

#### B. Verifique se Dashboard foi Criado
```javascript
// No console do navegador:
Dashboard.dashboardAtual
// Deve retornar um objeto com id, nome, etc.
// Se retornar null ou undefined, nenhum dashboard foi criado
```

#### C. Crie Dashboard Manualmente (Temporário)
Se não houver dashboard, a aplicação deve criar automaticamente, mas você pode forçar:

1. Abra o console (F12)
2. Execute:
```javascript
fetch('/public_html/api/dashboard/from-template', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    },
    body: JSON.stringify({
        nome: 'Meu Dashboard',
        template_codigo: 'template_geral'
    })
}).then(r => r.json()).then(console.log)
```

### 2. Layout com CSS Faltando

**O que foi adicionado:**
- `dashboard-complete.css` com estilos de botões e formulários
- Estilos para `.btn-primary`, `.btn-secondary`, `.form-control`
- CSS Variables para temas

**Verificação:**
- Inspecione um botão (F12 > Elements)
- Verifique se `dashboard-complete.css` está carregado
- Verifique se há erros 404 nos arquivos CSS

### 3. Catálogo de Widgets não Abre

**Verificações:**

```javascript
// 1. Verifique se widgetsTipos foi carregado
Dashboard.widgetsTipos
// Deve retornar array com 40+ widgets

// 2. Verifique se o botão tem listener
document.getElementById('btn-adicionar-widget')
// Deve retornar o elemento button

// 3. Teste abrir manualmente
document.getElementById('catalogo-widgets').classList.remove('hidden')
```

### 4. GridStack não Funciona

**Sintomas:**
- Widgets não podem ser arrastados
- Widgets não redimensionam

**Verificação:**
```javascript
// Verifique se GridStack foi inicializado
Dashboard.grid
// Deve retornar objeto GridStack

// Verifique se biblioteca carregou
typeof GridStack
// Deve retornar "function"
```

## Comandos Úteis no Console

```javascript
// Ver estado atual
console.log('Dashboard Atual:', Dashboard.dashboardAtual);
console.log('Widgets:', Dashboard.dashboardAtual?.widgets);
console.log('Tipos Disponíveis:', Dashboard.widgetsTipos.length);

// Recarregar dados
Dashboard.carregarDashboards();
Dashboard.carregarWidgetsTipos();
Dashboard.carregarTemplates();

// Forçar reload do dashboard
Dashboard.carregarDashboardPadrao();
```

## Verificação de API

### Teste as rotas diretamente:

```bash
# Listar dashboards
GET /public_html/api/dashboard

# Dashboard padrão
GET /public_html/api/dashboard/padrao

# Widget tipos
GET /public_html/api/dashboard/widget-tipos

# Templates
GET /public_html/api/dashboard/templates
```

### Resposta Esperada:
```json
{
    "sucesso": true,
    "dados": [...],
    "mensagem": "..."
}
```

## Passos para Resolver

1. **Atualize a página** (Ctrl + F5)
2. **Abra o Console** (F12)
3. **Procure erros em vermelho**
4. **Verifique se as APIs respondem** (Network tab)
5. **Execute os comandos de debug** acima
6. **Anote a mensagem de erro completa**

## Estrutura Esperada

```
Dashboard {
    dashboardAtual: {
        id: 1,
        nome: "Dashboard Geral",
        widgets: [...]
    },
    dashboards: [...],
    widgetsTipos: [42 items],
    templates: [6 items],
    grid: GridStack {...}
}
```

## Próximos Passos se Ainda Não Funcionar

1. Copie o erro completo do console
2. Verifique a aba "Network" (F12) para ver qual requisição falhou
3. Verifique o "Response" da requisição que falhou
4. Me envie essas informações para ajudar
