# Sistema de Email com PHPMailer

Sistema completo de gerenciamento e envio de emails integrado ao sistema Ecletech, seguindo o mesmo padrão da implementação do WhatsApp.

## 📋 Índice

- [Características](#características)
- [Arquitetura](#arquitetura)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso](#uso)
- [API Endpoints](#api-endpoints)
- [Cron Job](#cron-job)
- [Templates](#templates)
- [Tracking](#tracking)

## ✨ Características

- ✅ **Envio via SMTP** com PHPMailer 6.9
- ✅ **Sistema de Fila** para envios assíncronos
- ✅ **Sistema de Entidades** (Cliente, Colaborador, Fornecedor, Transportadora)
- ✅ **Templates HTML** personalizáveis
- ✅ **Tracking de Abertura** com pixel transparente
- ✅ **Tracking de Cliques** em links
- ✅ **Retry Automático** com backoff exponencial
- ✅ **Histórico Completo** de todos os envios
- ✅ **Permissões ACL** integradas
- ✅ **API RESTful** completa

## 🏗️ Arquitetura

### Estrutura de Diretórios

```
/App
├── Controllers/Email/          # 5 Controllers
│   ├── ControllerEmailEnvio.php
│   ├── ControllerEmailConexao.php
│   ├── ControllerEmailPainel.php
│   ├── ControllerEmailConfiguracao.php
│   └── ControllerEmailTracking.php
├── Models/Email/               # 5 Models
│   ├── ModelEmailConfiguracao.php
│   ├── ModelEmailQueue.php
│   ├── ModelEmailHistorico.php
│   ├── ModelEmailEntidade.php
│   └── ModelEmailSMTP.php
├── Services/Email/             # 2 Services
│   ├── ServiceEmail.php
│   └── ServiceEmailEntidade.php
├── Helpers/
│   └── AuxiliarEmail.php
├── Routes/
│   └── email.php
└── Views/Email/                # Templates
    ├── base.php
    └── notificacao.php

/database/migrations/
├── 2025_01_13_create_email_tables.sql
└── 052_adicionar_permissoes_email.sql

/cron/
└── processar_email.php
```

### Banco de Dados (5 Tabelas)

1. **email_configuracoes** - 73 configurações do sistema
2. **email_queue** - Fila de emails pendentes
3. **email_historico** - Histórico completo de envios
4. **email_entidades** - Mapeamento entidade→email
5. **email_cron_logs** - Logs do processamento cron

## 📦 Instalação

### 1. Instalar Dependências

```bash
composer update phpmailer/phpmailer
```

### 2. Executar Migrations

```bash
# Migration principal (5 tabelas)
mysql -u root -p ecletech < database/migrations/2025_01_13_create_email_tables.sql

# Permissões ACL
mysql -u root -p ecletech < database/migrations/052_adicionar_permissoes_email.sql
```

### 3. Configurar Cron Job

```bash
# Adicionar ao crontab (executar a cada 1 minuto)
* * * * * php /caminho/para/cron/processar_email.php >> /var/log/email_cron.log 2>&1

# Ou a cada 5 minutos
*/5 * * * * php /caminho/para/cron/processar_email.php >> /var/log/email_cron.log 2>&1
```

### 4. Tornar Cron Executável

```bash
chmod +x cron/processar_email.php
```

## ⚙️ Configuração

### Configuração SMTP (via banco de dados)

Acesse a tabela `email_configuracoes` e configure:

```sql
-- Servidor SMTP
UPDATE email_configuracoes SET valor = 'smtp.gmail.com' WHERE chave = 'smtp_host';
UPDATE email_configuracoes SET valor = '587' WHERE chave = 'smtp_port';
UPDATE email_configuracoes SET valor = 'tls' WHERE chave = 'smtp_secure';

-- Autenticação
UPDATE email_configuracoes SET valor = 'seu@email.com' WHERE chave = 'smtp_usuario';
UPDATE email_configuracoes SET valor = 'sua_senha' WHERE chave = 'smtp_senha';

-- Remetente padrão
UPDATE email_configuracoes SET valor = 'noreply@ecletech.com.br' WHERE chave = 'from_email';
UPDATE email_configuracoes SET valor = 'Ecletech Sistemas' WHERE chave = 'from_name';
```

### Configuração de Entidades

```sql
-- Cliente
UPDATE email_configuracoes SET valor = 'clientes' WHERE chave = 'entidade_cliente_tabela';
UPDATE email_configuracoes SET valor = 'email' WHERE chave = 'entidade_cliente_campo_email';

-- Colaborador
UPDATE email_configuracoes SET valor = 'colaboradores' WHERE chave = 'entidade_colaborador_tabela';

-- Fornecedor
UPDATE email_configuracoes SET valor = 'fornecedores' WHERE chave = 'entidade_fornecedor_tabela';

-- Transportadora
UPDATE email_configuracoes SET valor = 'transportadoras' WHERE chave = 'entidade_transportadora_tabela';
```

## 🚀 Uso

### Envio via PHP

```php
use App\Services\Email\ServiceEmail;

$email = new ServiceEmail();

// Envio simples
$resultado = $email->enviarEmail([
    'destinatario' => 'cliente:123',  // ou email direto
    'assunto' => 'Bem-vindo!',
    'corpo_html' => '<h1>Olá!</h1><p>Bem-vindo ao sistema.</p>',
    'corpo_texto' => 'Olá! Bem-vindo ao sistema.',
    'modo_envio' => 'fila'  // ou 'direto'
]);

if ($resultado['sucesso']) {
    echo "Email enviado! Queue ID: " . $resultado['queue_id'];
}
```

### Envio com Template

```php
$resultado = $email->enviarEmail([
    'destinatario' => 'joao@email.com',
    'assunto' => 'Notificação Importante',
    'template' => 'notificacao',
    'dados_template' => [
        'titulo' => 'Pedido Aprovado',
        'tipo_alerta' => 'success',
        'mensagem' => 'Seu pedido #1234 foi aprovado!',
        'detalhes' => [
            'Pedido' => '#1234',
            'Data' => '13/01/2025',
            'Valor' => 'R$ 1.500,00'
        ],
        'botao_texto' => 'Ver Pedido',
        'botao_link' => 'https://sistema.com/pedidos/1234'
    ]
]);
```

### Envio com Anexos

```php
$resultado = $email->enviarEmail([
    'destinatario' => 'cliente:456',
    'assunto' => 'Nota Fiscal',
    'corpo_html' => '<p>Segue em anexo sua nota fiscal.</p>',
    'anexos' => [
        [
            'caminho' => '/uploads/nf-1234.pdf',
            'nome' => 'Nota_Fiscal_1234.pdf'
        ]
    ]
]);
```

### Prioridades

```php
// Urgente - processa primeiro
'prioridade' => 'urgente'

// Alta - processa em segundo
'prioridade' => 'alta'

// Normal - padrão
'prioridade' => 'normal'

// Baixa - processa por último
'prioridade' => 'baixa'
```

## 📡 API Endpoints

### Envio

```bash
# Enviar email
POST /email/enviar
{
  "destinatario": "cliente:123",
  "assunto": "Teste",
  "corpo_html": "<p>Olá!</p>",
  "modo_envio": "fila",
  "prioridade": "normal"
}

# Listar fila
GET /email/fila?status=1&limit=50

# Cancelar email
DELETE /email/fila/{id}

# Estatísticas
GET /email/estatisticas

# Histórico
GET /email/historico?data_inicio=2025-01-01&data_fim=2025-01-31
```

### Conexão SMTP

```bash
# Status da conexão
GET /email/conexao/status

# Testar conexão
POST /email/conexao/testar

# Informações
GET /email/conexao/info
```

### Configuração

```bash
# Listar todas
GET /email/config

# Obter específica
GET /email/config/{chave}

# Salvar
POST /email/config/salvar
{
  "chave": "smtp_host",
  "valor": "smtp.gmail.com"
}

# Sincronizar entidade
POST /email/config/sincronizar-entidade
{
  "tipo": "cliente",
  "id": 123
}
```

### Tracking

```bash
# Pixel de abertura (público)
GET /email/track/open/{tracking_code}

# Rastreamento de cliques (público)
GET /email/track/click/{tracking_code}?url=https://...

# Estatísticas de tracking
GET /email/track/stats/{tracking_code}
```

## ⏰ Cron Job

O arquivo `cron/processar_email.php` processa a fila automaticamente.

### Configurações do Cron

```sql
-- Habilitar/desabilitar
UPDATE email_configuracoes SET valor = 'true' WHERE chave = 'cron_habilitado';

-- Limite de emails por execução
UPDATE email_configuracoes SET valor = '20' WHERE chave = 'cron_limite_mensagens';

-- Horário de funcionamento
UPDATE email_configuracoes SET valor = '08:00' WHERE chave = 'cron_horario_inicio';
UPDATE email_configuracoes SET valor = '22:00' WHERE chave = 'cron_horario_fim';
```

### Processar Manualmente

```bash
php cron/processar_email.php
```

## 📄 Templates

### Criar Template

1. Criar arquivo em `App/Views/Email/meu_template.php`
2. Usar variáveis PHP para conteúdo dinâmico
3. Habilitar templates:

```sql
UPDATE email_configuracoes SET valor = 'true' WHERE chave = 'templates_habilitados';
```

### Exemplo de Template

```php
<!DOCTYPE html>
<html>
<head>
    <title><?= $assunto ?></title>
</head>
<body>
    <h1><?= $titulo ?></h1>
    <p><?= $mensagem ?></p>

    <?php if (isset($detalhes)): ?>
        <ul>
            <?php foreach ($detalhes as $item): ?>
                <li><?= $item ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
```

## 📊 Tracking

### Rastreamento de Abertura

Automaticamente injeta pixel transparente 1x1 no HTML quando tracking está habilitado.

```sql
UPDATE email_configuracoes SET valor = 'true' WHERE chave = 'tracking_pixel_habilitado';
```

### Rastreamento de Cliques

Converte automaticamente todos os links em URLs de rastreamento.

```sql
UPDATE email_configuracoes SET valor = 'true' WHERE chave = 'tracking_links_habilitado';
```

### Visualizar Estatísticas

```bash
GET /email/track/stats/{tracking_code}
```

Retorna:
- Data de envio
- Data de abertura
- Data de clique
- IP e User Agent

## 🔐 Permissões

### 3 Permissões ACL

1. **`email.acessar`** - Visualizar painel, fila, histórico
2. **`email.alterar`** - Enviar emails, alterar configs
3. **`email.deletar`** - BLOQUEADA (segurança)

### Atribuir Permissões

```sql
-- Super Admin e Admin já têm acesso automaticamente

-- Atribuir para outra role
INSERT INTO role_permissoes (role_id, permissao_id)
SELECT 3, id FROM permissoes WHERE nome = 'email.acessar';
```

## 📈 Status Codes

- **0** = Erro
- **1** = Pendente
- **2** = Enviado
- **3** = Bounce (rejeitado)
- **4** = Aberto
- **5** = Clicado

## 🔧 Troubleshooting

### Email não envia

1. Verificar configurações SMTP
2. Testar conexão: `POST /email/conexao/testar`
3. Verificar cron: `tail -f /var/log/email_cron.log`

### Tracking não funciona

1. Verificar se está habilitado nas configurações
2. Verificar se `APP_URL` está configurado no `.env`
3. Verificar rotas públicas de tracking

### Fila não processa

1. Verificar se cron está configurado
2. Verificar `cron_habilitado` = true
3. Verificar horário de funcionamento
4. Executar manualmente para testar

## 📚 Documentação Adicional

- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- Ver também: `README_WHATSAPP.md` (implementação similar)

## 🎯 Próximos Passos

- [ ] Criar painel web (email.html + Email.js)
- [ ] Implementar bounce detection
- [ ] Adicionar suporte a múltiplos SMTP
- [ ] Dashboard de analytics

---

**Desenvolvido seguindo o padrão WhatsApp** | Ecletech Sistemas © 2025
