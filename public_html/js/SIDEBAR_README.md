# Sidebar.js - Documentação

Sistema centralizado de controle de acesso ao menu lateral baseado em permissões.

## Visão Geral

O `sidebar.js` controla automaticamente a visibilidade dos itens do menu lateral baseado nas permissões do usuário logado. Não é necessário implementar verificação de permissões em cada página - tudo é feito de forma centralizada.

## Como Funciona

1. **Carregamento Automático**: O script é carregado automaticamente com a página
2. **Busca de Permissões**: Faz uma requisição para `/permissoes/usuario` e obtém a lista de permissões
3. **Aplicação de Regras**: Oculta itens do menu que possuem o atributo `data-permission` se o usuário não tiver a permissão necessária
4. **Limpeza de Seções**: Remove seções vazias automaticamente

## Instalação

### 1. Incluir o Script

Adicione o script após `API.js` e `Auth.js` em suas páginas HTML:

```html
<script src="js/API.js"></script>
<script src="js/Auth.js"></script>
<script src="js/sidebar.js"></script>
```

### 2. Marcar Elementos do Menu

Adicione o atributo `data-permission` aos elementos que devem ter controle de acesso:

```html
<!-- Permissão única -->
<a href="./colaboradores.html" data-permission="colaboradores.visualizar">
    <span class="icon"><i class="fas fa-users"></i></span>
    <span>Colaboradores</span>
</a>

<!-- Múltiplas permissões (lógica OR) -->
<a href="./gestao.html" data-permission="permissoes.visualizar,roles.visualizar">
    <span class="icon"><i class="fas fa-cog"></i></span>
    <span>Gestão de Acessos</span>
</a>
```

## Exemplos de Uso

### Exemplo 1: Menu Simples

```html
<nav class="sidebar-nav">
    <div class="nav-section">
        <div class="nav-section-title">Gestão</div>

        <!-- Visível apenas para quem pode visualizar colaboradores -->
        <a href="./colaboradores.html" data-permission="colaboradores.visualizar">
            <span>Colaboradores</span>
        </a>

        <!-- Visível apenas para quem pode visualizar frotas -->
        <a href="./frotas.html" data-permission="frota.visualizar">
            <span>Frotas</span>
        </a>

        <!-- Sempre visível (sem permissão) -->
        <a href="./home.html">
            <span>Home</span>
        </a>
    </div>
</nav>
```

### Exemplo 2: Submenu

```html
<button class="submenu-toggle" onclick="toggleSubmenu('cadastros', this)">
    <span>Cadastros</span>
</button>

<div class="submenu" id="cadastros">
    <!-- Cada item tem sua própria permissão -->
    <a href="./estados.html" data-permission="estados.visualizar">Estados</a>
    <a href="./cidades.html" data-permission="cidades.visualizar">Cidades</a>
</div>
```

### Exemplo 3: Permissões Múltiplas (OR)

O usuário precisa ter **pelo menos uma** das permissões listadas:

```html
<a href="./admin.html" data-permission="admin.visualizar,super.admin">
    <span>Administração</span>
</a>
```

## API do Sidebar Manager

### Métodos Disponíveis

```javascript
// Verificar se uma permissão existe
SidebarManager.verificarPermissao('colaboradores.visualizar')
// Retorna: true ou false

// Verificar múltiplas permissões (AND - todas devem existir)
SidebarManager.verificarTodasPermissoes(['colaboradores.criar', 'colaboradores.editar'])
// Retorna: true ou false

// Verificar múltiplas permissões (OR - pelo menos uma deve existir)
SidebarManager.verificarAlgumaPermissao(['admin.visualizar', 'super.admin'])
// Retorna: true ou false

// Obter lista de permissões do usuário
SidebarManager.obterPermissoes()
// Retorna: ['colaboradores.visualizar', 'frota.visualizar', ...]

// Verificar se está carregado
SidebarManager.estaCarregado()
// Retorna: true ou false

// Recarregar permissões (útil se mudarem durante a sessão)
await SidebarManager.recarregar()

// Ativar modo debug
SidebarManager.setDebug(true)
```

### Exemplo de Uso Programático

```javascript
// Mostrar um botão apenas se o usuário puder editar
if (SidebarManager.verificarPermissao('colaboradores.editar')) {
    document.getElementById('btnEditar').style.display = 'block';
}

// Verificar múltiplas permissões
if (SidebarManager.verificarTodasPermissoes(['admin.visualizar', 'admin.editar'])) {
    console.log('Usuário é administrador completo');
}
```

## Estrutura de Permissões

As permissões seguem o padrão `modulo.acao`:

| Código | Descrição |
|--------|-----------|
| `colaboradores.visualizar` | Visualizar colaboradores |
| `colaboradores.criar` | Criar colaboradores |
| `colaboradores.editar` | Editar colaboradores |
| `colaboradores.deletar` | Deletar colaboradores |
| `frota.visualizar` | Visualizar frotas |
| `frota.criar` | Criar frotas |
| `frota.editar` | Editar frotas |
| `frota.deletar` | Deletar frotas |
| `estado.visualizar` | Visualizar estados |
| `estado.criar` | Criar estados |
| `estado.editar` | Editar estados |
| `estado.deletar` | Deletar estados |
| `cidade.visualizar` | Visualizar cidades |
| `cidade.criar` | Criar cidades |
| `cidade.editar` | Editar cidades |
| `cidade.deletar` | Deletar cidades |
| `situacao_venda.visualizar` | Visualizar situações de vendas |
| `situacao_venda.criar` | Criar situações de vendas |
| `situacao_venda.editar` | Editar situações de vendas |
| `situacao_venda.deletar` | Deletar situações de vendas |
| `tipo_endereco.visualizar` | Visualizar tipos de endereços |
| `tipo_endereco.criar` | Criar tipos de endereços |
| `tipo_endereco.editar` | Editar tipos de endereços |
| `tipo_endereco.deletar` | Deletar tipos de endereços |
| `tipo_contato.visualizar` | Visualizar tipos de contatos |
| `tipo_contato.criar` | Criar tipos de contatos |
| `tipo_contato.editar` | Editar tipos de contatos |
| `tipo_contato.deletar` | Deletar tipos de contatos |
| `loja.visualizar` | Visualizar informações da loja |
| `loja.editar` | Editar informações da loja |
| `permissoes.visualizar` | Visualizar permissões |
| `permissoes.criar` | Criar permissões |
| `permissoes.editar` | Editar permissões |
| `permissoes.deletar` | Deletar permissões |
| `roles.visualizar` | Visualizar roles |
| `roles.criar` | Criar roles |
| `roles.editar` | Editar roles |
| `roles.deletar` | Deletar roles |
| `niveis.visualizar` | Visualizar níveis |
| `niveis.criar` | Criar níveis |
| `niveis.editar` | Editar níveis |
| `niveis.deletar` | Deletar níveis |
| `usuarios.visualizar` | Visualizar usuários |
| `usuarios.criar` | Criar usuários |
| `usuarios.editar` | Editar usuários |
| `usuarios.deletar` | Deletar usuários |
| `config.visualizar` | Visualizar configurações |
| `config.editar` | Editar configurações |
| `auditoria.visualizar` | Visualizar auditoria |
| `auditoria.deletar` | Deletar auditoria |
| `relatorios.visualizar` | Visualizar relatórios |
| `relatorios.exportar` | Exportar relatórios |

## Comportamento em Caso de Erro

Por segurança, se ocorrer um erro ao carregar as permissões (ex: falha de rede), o sidebar **mostra todos os itens** (fail-open). Isso evita que problemas técnicos bloqueiem completamente o sistema.

A segurança real está no backend - mesmo que um item apareça no menu, o usuário não conseguirá acessar a funcionalidade sem a permissão correta.

## Debug

Para ver logs detalhados do que o sidebar está fazendo:

```javascript
// No console do navegador
SidebarManager.setDebug(true);

// Recarregar para ver os logs
await SidebarManager.recarregar();
```

Isso mostrará:
- Permissões carregadas
- Elementos verificados
- Quais itens foram ocultados/mostrados
- Seções vazias removidas

## Manutenção

### Adicionar Nova Permissão

1. **Backend**: Criar a permissão no banco de dados
   ```sql
   INSERT INTO colaborador_permissions (nome, codigo, modulo)
   VALUES ('Visualizar Vendas', 'vendas.visualizar', 'vendas');
   ```

2. **Frontend**: Adicionar o atributo no HTML
   ```html
   <a href="./vendas.html" data-permission="vendas.visualizar">Vendas</a>
   ```

3. **Teste**: Verificar se a permissão está funcionando

### Atualizar Permissões Durante a Sessão

Se as permissões do usuário mudarem (ex: promoção de nível):

```javascript
// Recarregar as permissões
await SidebarManager.recarregar();

// O menu será atualizado automaticamente
```

## Perguntas Frequentes

### P: Os itens sem `data-permission` são sempre visíveis?
**R**: Sim. Se um item não tem o atributo `data-permission`, ele será sempre exibido.

### P: Posso usar permissões em outros elementos além de links?
**R**: Sim! Pode usar em qualquer elemento HTML:
```html
<button data-permission="colaboradores.criar">Novo</button>
<div data-permission="admin.visualizar">Painel Admin</div>
```

### P: O que acontece se eu usar vírgulas no `data-permission`?
**R**: É interpretado como lógica OR - o usuário precisa ter **pelo menos uma** das permissões.

### P: Como faço para exigir TODAS as permissões (AND)?
**R**: Use o método programático:
```javascript
if (SidebarManager.verificarTodasPermissoes(['perm1', 'perm2'])) {
    // mostrar elemento
}
```

### P: O sidebar.js funciona em mobile?
**R**: Sim, é totalmente responsivo e funciona em qualquer dispositivo.

## Troubleshooting

### Problema: Todos os itens estão sendo ocultados

**Solução**: Verifique se:
1. O usuário está autenticado
2. A API `/permissoes/usuario` está retornando dados
3. As permissões no HTML coincidem com as do banco

```javascript
// Debug no console
console.log(SidebarManager.obterPermissoes());
```

### Problema: Permissões não atualizam

**Solução**: Force um reload:
```javascript
await SidebarManager.recarregar();
```

### Problema: Erro 401 ao carregar permissões

**Solução**: O usuário não está autenticado. Verifique:
1. Cookie JWT está presente
2. Token não expirou
3. Sessão está ativa

## Suporte

Para dúvidas ou problemas:
1. Ative o modo debug: `SidebarManager.setDebug(true)`
2. Verifique o console do navegador
3. Verifique a aba Network para ver a requisição `/permissoes/usuario`
4. Contate a equipe de desenvolvimento

---

**Última atualização**: 2025-11-11
