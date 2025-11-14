# Frontend - Gerenciamento de Relatórios de Abastecimento

## 📋 Descrição

Interface web para gerenciamento de relatórios automáticos de abastecimento de frota. Permite configurar, visualizar histórico e gerar relatórios manualmente.

## 📁 Arquivos Criados

```
public_html/
├── frota_abastecimento_relatorios.html   (20KB) - Página principal
├── css/
│   └── frota_abastecimento_relatorios.css (15KB) - Estilos
└── js/
    └── frota_abastecimento_relatorios.js  (36KB) - Lógica da aplicação
```

## 🚀 Como Acessar

1. Faça login no sistema
2. Acesse: `https://seu-dominio.com/public_html/frota_abastecimento_relatorios.html`

## 🎯 Funcionalidades

### 1. **Aba Configurações**

#### ✨ Criar/Editar Configuração
- **Tipo de Relatório**: Semanal ou Mensal
- **Dia de Envio**:
  - Semanal: Segunda a Domingo (⚠️ Recomendado: Segunda-feira)
  - Mensal: Dia 1 a 28
- **Hora de Envio**: Padrão 08:00
- **Formato**:
  - **Resumido**: Totais e médias gerais
  - **Detalhado**: Resumo + Top 5 veículos/motoristas (padrão)
  - **Completo**: Tudo + rankings completos
- **Status**: Ativo/Inativo

#### 📋 Minhas Configurações
- Lista todas as configurações do usuário
- Ações disponíveis:
  - ✏️ **Editar**: Modificar configuração existente
  - ⏸️/▶️ **Ativar/Desativar**: Toggle de status
  - 🗑️ **Deletar**: Remover configuração (com confirmação)

### 2. **Aba Histórico de Envios**

#### 🔍 Filtros
- **Tipo**: Semanal ou Mensal
- **Status**: Enviado, Erro, Pendente, Cancelado
- **Período**: Data início e fim

#### 📊 Tabela de Envios
Exibe para cada envio:
- ID do log
- Tipo de relatório
- Período coberto
- Nome do destinatário
- Formato utilizado
- Status do envio
- Data/hora de envio
- Botão para ver detalhes completos

#### 👁️ Detalhes do Envio
Ao clicar em "Ver Detalhes", abre modal com:
- Informações gerais (ID, tipo, status, formato)
- Período do relatório
- Dados do destinatário (nome, telefone)
- Estatísticas (tentativas, tamanho da mensagem, tempo de processamento)
- Mensagem de erro (se houver)
- Mensagem enviada completa

#### 📄 Paginação
- Navega entre páginas de histórico
- Exibe página atual e total de páginas

### 3. **Aba Snapshots**

Snapshots são dados pré-calculados armazenados para performance.

#### 🔍 Filtros
- **Tipo**: Semanal ou Mensal
- **Ano**: Ano específico
- **Mês**: Janeiro a Dezembro (apenas para mensais)

#### 📸 Cards de Snapshot
Cada snapshot exibe:
- Tipo e período
- Total de abastecimentos
- Total de litros consumidos
- Valor total gasto
- Consumo médio (km/L)
- Custo médio por km
- Total de alertas

#### 🔍 Detalhes do Snapshot
Modal com:
- Resumo geral completo
- Rankings de consumo (melhores e piores)
- Dados detalhados por frota, motorista e combustível

### 4. **Geração Manual**

Botão no topo direito da página: **"Gerar Manual"**

Abre modal para:
1. Escolher tipo (semanal/mensal)
2. Definir período (data início e fim)
3. Selecionar formato
4. Opções:
   - **"Apenas Gerar"**: Visualiza o relatório sem enviar
   - **"Gerar e Enviar"**: Gera e envia via WhatsApp imediatamente

## 🎨 Interface

### Tema
- Suporte a tema claro e escuro (botão no header)
- Transições suaves entre temas
- Ícones do Font Awesome

### Layout
- Sidebar de navegação centralizada
- Design responsivo (mobile-friendly)
- Animações e transições modernas
- Cores principais: Laranja (#ee670d) e branco/cinza

### Componentes
- **Cards**: Blocos de conteúdo com sombra e hover
- **Badges**: Status coloridos (sucesso, erro, aviso, info)
- **Modais**: Pop-ups para detalhes e formulários
- **Filtros**: Sistema de busca avançada
- **Tabelas**: Listagens paginadas
- **Loading**: Spinners durante carregamento

## 🔧 Tecnologias Utilizadas

- **HTML5**: Estrutura semântica
- **CSS3**:
  - CSS Variables para temas
  - Flexbox e Grid Layout
  - Animações e transições
- **JavaScript (Vanilla)**:
  - Fetch API para requisições
  - Promises e Async/Await
  - Manipulação do DOM

### Dependências Externas
- **Font Awesome 6.4.0**: Ícones
- **Google Fonts (Inter)**: Tipografia
- **API.js**: Utilitários de API (já existente no projeto)
- **Auth.js**: Autenticação (já existente no projeto)
- **Utils.js**: Funções auxiliares (já existente no projeto)
- **theme.js**: Gerenciamento de tema (já existente no projeto)
- **sidebar.js**: Sidebar de navegação (já existente no projeto)

## 🔐 Segurança

- Autenticação obrigatória via cookie httpOnly
- CSRF Token em todas as requisições
- Validação de campos no cliente e servidor
- Confirmação para ações destrutivas (deletar)

## 📱 Responsividade

### Desktop (> 768px)
- Layout em colunas
- Sidebar fixa
- Múltiplas colunas em grids

### Mobile (≤ 768px)
- Layout em coluna única
- Sidebar retrátil
- Tabs com scroll horizontal
- Formulários em coluna única

## 🎯 Endpoints da API Consumidos

### Configurações
```
GET    /frota-abastecimento-relatorios/minhas-configuracoes
POST   /frota-abastecimento-relatorios/configurar
PATCH  /frota-abastecimento-relatorios/configuracao/{id}/ativar
PATCH  /frota-abastecimento-relatorios/configuracao/{id}/desativar
DELETE /frota-abastecimento-relatorios/configuracao/{id}
```

### Histórico
```
GET    /frota-abastecimento-relatorios/historico?pagina=1&por_pagina=10
GET    /frota-abastecimento-relatorios/log/{id}
```

### Snapshots
```
GET    /frota-abastecimento-relatorios/snapshots
GET    /frota-abastecimento-relatorios/snapshot/{id}
```

### Geração Manual
```
POST   /frota-abastecimento-relatorios/gerar-manual
POST   /frota-abastecimento-relatorios/enviar-manual
```

## ⚙️ Configuração

### Variáveis de Estado (JavaScript)
```javascript
const AppState = {
    configuracoes: [],      // Lista de configurações
    historico: [],          // Histórico de envios
    snapshots: [],          // Snapshots calculados
    paginaAtual: 1,         // Página atual do histórico
    itensPorPagina: 10,     // Itens por página
    totalItens: 0,          // Total de itens no histórico
    filtrosHistorico: {},   // Filtros aplicados ao histórico
    filtrosSnapshots: {},   // Filtros aplicados aos snapshots
    configEdicao: null      // Configuração sendo editada
};
```

### API Base URL
Configurado automaticamente em `API.js`:
```javascript
baseURL: window.location.origin + '/public_html/api'
```

## 🐛 Tratamento de Erros

1. **Loading States**: Spinner durante carregamento
2. **Error Messages**: Mensagens de erro amigáveis
3. **Empty States**: Mensagens quando não há dados
4. **Validação de Formulários**: Required nos campos obrigatórios
5. **Try/Catch**: Captura de erros em todas as requisições
6. **Feedback Visual**: Notificações de sucesso/erro

## 📊 Exemplo de Fluxo de Uso

### Configurar Relatório Semanal

1. Acessar página de relatórios
2. Na aba "Configurações", preencher formulário:
   - Tipo: Semanal
   - Dia: Segunda-feira
   - Hora: 08:00
   - Formato: Detalhado
   - Status: Ativo
3. Clicar em "Salvar Configuração"
4. Configuração aparece na lista "Minhas Configurações"
5. Sistema enviará automaticamente toda segunda às 8h

### Gerar Relatório Manual

1. Clicar em "Gerar Manual" no header
2. Preencher:
   - Tipo: Semanal
   - Data Início: 2025-11-10
   - Data Fim: 2025-11-16
   - Formato: Completo
3. Opções:
   - "Apenas Gerar": Ver prévia do relatório
   - "Gerar e Enviar": Enviar imediatamente via WhatsApp

### Visualizar Histórico

1. Ir para aba "Histórico de Envios"
2. Aplicar filtros (opcional):
   - Tipo: Semanal
   - Status: Enviado
   - Período: Última semana
3. Clicar em "Filtrar"
4. Clicar no ícone 👁️ para ver detalhes completos

## ⚠️ Avisos Importantes

### Problema Conhecido - Cron Semanal
⚠️ O cron de relatórios semanais está configurado para rodar apenas às **SEGUNDAS-FEIRAS às 8h**.

**Recomendação**: Sempre configure relatórios semanais para segunda-feira.

Se precisar de outro dia:
1. Ajustar crontab do servidor
2. Ou usar geração manual via interface

### Requisitos para Envio Automático
- Colaborador precisa ter celular cadastrado
- WhatsApp deve estar configurado e conectado
- Crons devem estar ativos no servidor

## 🔄 Atualizações Futuras Sugeridas

- [ ] Biblioteca de toast para notificações (ex: Toastify)
- [ ] Gráficos para visualização de dados (ex: Chart.js)
- [ ] Export de histórico para CSV/Excel
- [ ] Preview do relatório antes de enviar
- [ ] Agendamento de múltiplos relatórios simultâneos
- [ ] Notificações push quando relatório for enviado
- [ ] Dark mode persistente (salvar preferência)
- [ ] PWA (Progressive Web App)

## 📞 Suporte

Para dúvidas ou problemas:
1. Consulte a documentação da API: `RELATORIOS_ABASTECIMENTO.md`
2. Verifique os logs no console do navegador (F12)
3. Confira o histórico de envios para ver erros

## 📝 Changelog

### v1.0.0 (2025-11-14)
- ✅ Interface completa de gerenciamento
- ✅ CRUD de configurações
- ✅ Visualização de histórico com filtros
- ✅ Visualização de snapshots
- ✅ Geração manual de relatórios
- ✅ Tema claro/escuro
- ✅ Design responsivo
- ✅ Integração completa com API
