# Sistema de Integração CRM - Ecletech

Sistema completo de integração com CRM externo (GestãoClick, Pipedrive, Bling, etc.) com sincronização bidirecional, processamento batch e rate limiting via cron.

## 📋 Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso](#uso)
- [Estrutura](#estrutura)
- [Adicionar Novo Provider](#adicionar-novo-provider)
- [Adicionar Nova Entidade](#adicionar-nova-entidade)
- [Monitoramento](#monitoramento)

---

## 🚀 Instalação

### 1. Executar Migration

```bash
php executar_migration_crm.php
```

Isso criará:
- Tabela `crm_integracoes` (configurações)
- Tabela `crm_sync_queue` (fila de sincronização)
- Tabela `crm_sync_log` (histórico)
- Campo `external_id` nas tabelas existentes

### 2. Configurar Cron

Adicione ao crontab (`crontab -e`):

```bash
# Sincronização contínua (100 itens/minuto)
* * * * * /usr/bin/php /var/www/ecletech/cron/crm_sync.php >> /var/log/ecletech/crm_sync.log 2>&1

# Limpeza diária (às 3h)
0 3 * * * /usr/bin/php /var/www/ecletech/cron/crm_cleanup.php >> /var/log/ecletech/crm_cleanup.log 2>&1

# Sincronização completa de clientes (às 2h)
0 2 * * * /usr/bin/php /var/www/ecletech/cron/crm_sync_full_clientes.php >> /var/log/ecletech/crm_full.log 2>&1
```

---

## ⚙️ Configuração

### 1. Configurar CRM para uma Loja

```php
use App\CRM\Core\CrmConfig;

$config = new CrmConfig();

$config->salvarConfiguracao(
    idLoja: 1,
    provider: 'gestao_click',
    credenciais: [
        'api_token' => 'SEU_TOKEN_AQUI'
    ],
    configuracoes: [
        'webhook_url' => 'https://ecletech.com.br/webhook/crm'
    ]
);
```

### 2. Testar Conexão

```php
use App\Services\ServiceCrm;

$service = new ServiceCrm();
$resultado = $service->testarConexao(idLoja: 1);

if ($resultado['success']) {
    echo "Conexão OK!";
} else {
    echo "Erro: " . $resultado['message'];
}
```

---

## 💡 Uso

### Criar Cliente no CRM

```php
use App\Services\ServiceCrm;

$service = new ServiceCrm();

$resultado = $service->criar('cliente', [
    'id' => 123,
    'nome' => 'João Silva',
    'email' => 'joao@example.com',
    'telefone' => '11999887766',
    'tipo_pessoa' => 'PF',
    'cpf' => '12345678900'
], idLoja: 1);

if ($resultado['success']) {
    $externalId = $resultado['external_id'];
    // Salvar $externalId no banco
}
```

### Enfileirar para Sincronização Automática

```php
use App\Models\ModelCrmSyncQueue;

$queue = new ModelCrmSyncQueue();

// Enfileira cliente para sincronizar (será processado pelo cron)
$queue->enfileirar(
    idLoja: 1,
    entidade: 'cliente',
    idRegistro: 123,
    direcao: 'ecletech_para_crm',
    prioridade: 10  // 0-10 (maior = mais importante)
);
```

### Sincronização Bidirecional

```php
use App\Services\ServiceCrmSync;

$service = new ServiceCrmSync();

// Ecletech → CRM
$resultado = $service->sincronizarParaCrm('cliente', $dados, idLoja: 1);

// CRM → Ecletech
$resultado = $service->sincronizarParaEcletech('cliente', $externalId, idLoja: 1);
```

### Importação em Lote do CRM

```php
$service = new ServiceCrmSync();

$resultado = $service->importarDoCrm('cliente', idLoja: 1, limite: 100);

echo "Importados: " . $resultado['importados'];
```

---

## 📁 Estrutura

```
App/
├── CRM/
│   ├── Core/
│   │   ├── CrmManager.php           # Orquestrador principal
│   │   ├── CrmConfig.php            # Gerencia configurações
│   │   └── CrmException.php         # Exceção customizada
│   │
│   └── Providers/
│       ├── CrmProviderInterface.php # Interface base
│       │
│       └── GestaoClick/             # Provider isolado
│           ├── GestaoClickProvider.php
│           ├── config.php
│           └── Handlers/
│               ├── ClienteHandler.php
│               ├── ProdutoHandler.php
│               ├── VendaHandler.php
│               └── AtividadeHandler.php
│
├── Models/
│   ├── ModelCrmIntegracao.php       # Configurações CRM
│   ├── ModelCrmSyncQueue.php        # Fila
│   └── ModelCrmSyncLog.php          # Logs
│
└── Services/
    ├── ServiceCrm.php               # CRUD básico
    ├── ServiceCrmSync.php           # Sincronização
    └── ServiceCrmCron.php           # Processamento batch

cron/
├── crm_sync.php                     # Sincronização (1/min)
├── crm_cleanup.php                  # Limpeza
└── crm_sync_full_clientes.php      # Sync completa

database/migrations/
└── crm_tables.sql                   # Migration SQL
```

---

## 🔌 Adicionar Novo Provider

### 1. Criar Estrutura

```bash
mkdir -p App/CRM/Providers/Pipedrive/Handlers
```

### 2. Criar config.php

```php
<?php
// App/CRM/Providers/Pipedrive/config.php

return [
    'api_base_url' => 'https://api.pipedrive.com/v1',
    'rate_limit' => 100,
    'endpoints' => [
        'cliente' => [
            'listar' => '/persons',
            'criar' => '/persons',
            // ...
        ]
    ]
];
```

### 3. Criar Provider

```php
<?php
// App/CRM/Providers/Pipedrive/PipedriveProvider.php

namespace App\CRM\Providers\Pipedrive;

use App\CRM\Providers\CrmProviderInterface;

class PipedriveProvider implements CrmProviderInterface
{
    // Implementar métodos da interface
}
```

### 4. Criar Handlers

```php
<?php
// App/CRM/Providers/Pipedrive/Handlers/ClienteHandler.php

namespace App\CRM\Providers\Pipedrive\Handlers;

class ClienteHandler
{
    public function transformarParaExterno(array $cliente): array {
        // Transforma Ecletech → Pipedrive
    }

    public function transformarParaInterno(array $clienteCrm): array {
        // Transforma Pipedrive → Ecletech
    }
}
```

### 5. Usar Novo Provider

```php
$config->salvarConfiguracao(
    idLoja: 2,
    provider: 'pipedrive',  // Nome em snake_case
    credenciais: ['api_token' => 'xxx']
);
```

---

## ➕ Adicionar Nova Entidade

### 1. Atualizar Banco (se usar ENUM)

```sql
ALTER TABLE crm_sync_queue
MODIFY COLUMN entidade VARCHAR(50) NOT NULL;
```

### 2. Criar Handler

```php
<?php
// App/CRM/Providers/GestaoClick/Handlers/PedidoHandler.php

namespace App\CRM\Providers\GestaoClick\Handlers;

class PedidoHandler
{
    public function transformarParaExterno(array $pedido): array {
        return [
            'order_number' => $pedido['numero'],
            'customer_id' => $pedido['external_id_cliente'],
            'total_value' => $pedido['valor_total']
        ];
    }

    public function transformarParaInterno(array $pedidoCrm): array {
        return [
            'external_id' => $pedidoCrm['id'],
            'numero' => $pedidoCrm['order_number'],
            'valor_total' => $pedidoCrm['total_value']
        ];
    }
}
```

### 3. Adicionar em config.php

```php
'endpoints' => [
    // ...
    'pedido' => [
        'listar' => '/orders',
        'criar' => '/orders',
        'atualizar' => '/orders/{id}',
        'buscar' => '/orders/{id}',
        'deletar' => '/orders/{id}'
    ]
]
```

### 4. Usar

```php
// Enfileira automaticamente
$queue->enfileirar(1, 'pedido', 456, 'ecletech_para_crm');

// Ou cria direto
$service->criar('pedido', $dados, 1);
```

**Pronto!** A nova entidade funciona automaticamente.

---

## 📊 Monitoramento

### Estatísticas da Fila

```php
use App\Services\ServiceCrmCron;

$service = new ServiceCrmCron();
$stats = $service->obterEstatisticas();

echo "Pendentes: " . $stats['pendentes'];
echo "Processados hoje: " . $stats['processados_hoje'];
echo "Erros (24h): " . $stats['erros_24h'];
```

### Logs de Sincronização

```php
use App\Models\ModelCrmSyncLog;

$logModel = new ModelCrmSyncLog();

// Logs por registro
$logs = $logModel->buscarPorRegistro('cliente', 123);

// Logs com erro
$erros = $logModel->buscarErros(50);

// Estatísticas
$stats = $logModel->obterEstatisticas();
echo "Taxa de sucesso: " . $stats['taxa_sucesso'] . "%";
```

### Query SQL Úteis

```sql
-- Itens na fila por status
SELECT
    entidade,
    SUM(CASE WHEN processado = 0 THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN processado = 1 THEN 1 ELSE 0 END) as processados
FROM crm_sync_queue
WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY entidade;

-- Taxa de sucesso
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
    ROUND(SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as taxa
FROM crm_sync_log
WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

---

## 🎯 Características

✅ **100 requisições/minuto** - Controlado por cron
✅ **Sem delays** - Código executa sem bloqueios
✅ **Múltiplos providers** - GestãoClick, Pipedrive, Bling...
✅ **Bidirecionall** - Ecletech ↔ CRM
✅ **Priorização** - Fila com prioridades configuráveis
✅ **Retry automático** - Até 3 tentativas
✅ **Logs completos** - Rastreabilidade total
✅ **Extensível** - Adicionar provider/entidade facilmente

---

## 📝 Documentação Completa

Veja os documentos em `/docs`:
- `CRM_SINCRONIZACAO_CRON.md` - Detalhes do processamento cron
- `CRM_PROVIDER_GESTAOCLICK.md` - Provider GestãoClick
- `CRM_ESTRUTURA_DADOS.md` - Mapeamentos de dados
- `CRM_CONFIGURACAO_BANCO.md` - Estrutura do banco

---

## 🆘 Suporte

Para problemas ou dúvidas:
1. Verifique os logs em `/var/log/ecletech/crm_*.log`
2. Consulte tabela `crm_sync_log` para histórico
3. Execute `php executar_migration_crm.php` novamente se necessário

---

**Desenvolvido para Ecletech** 🚀
