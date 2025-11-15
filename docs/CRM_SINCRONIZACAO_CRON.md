# Sincronização CRM com Cron - 100 Requisições/Minuto

## Visão Geral

Esta abordagem usa **cron** para controlar a taxa de requisições (100/min), **sem delays artificiais** no código. O cron executa a cada minuto e processa exatamente 100 registros.

## Vantagens

✅ **Sem usleep() ou sleep()** - código executa sem bloqueios
✅ **Previsibilidade** - sempre 100 requisições por minuto
✅ **Escalabilidade** - fácil ajustar frequência via cron
✅ **Resiliência** - falhas não afetam próxima execução
✅ **Monitoramento** - logs por execução facilitam debug

---

## 1. Estrutura da Tabela de Controle

```sql
CREATE TABLE crm_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_loja INT NOT NULL,
    entidade ENUM('cliente', 'produto', 'venda', 'atividade') NOT NULL,
    id_registro INT NOT NULL,
    direcao ENUM('ecletech_para_crm', 'crm_para_ecletech') NOT NULL,
    prioridade INT DEFAULT 0,
    tentativas INT DEFAULT 0,
    processado TINYINT(1) DEFAULT 0,
    erro TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processado_em TIMESTAMP NULL,
    INDEX idx_processar (processado, id_loja, prioridade DESC, criado_em ASC),
    INDEX idx_entidade (entidade, id_registro)
) ENGINE=InnoDB;
```

**Funcionamento:**
- Registros são **enfileirados** quando há mudanças
- Cron processa os **100 primeiros** não processados a cada minuto
- Prioridade permite controlar ordem de sincronização

---

## 2. ServiceCrmCron.php - SEM DELAYS

```php
<?php

namespace App\CRM\Services;

use App\CRM\Core\CrmManager;
use App\CRM\Models\ModelCrmSyncQueue;
use App\CRM\Models\ModelCrmSyncLog;

class ServiceCrmCron
{
    private CrmManager $crmManager;
    private ModelCrmSyncQueue $modelQueue;
    private ModelCrmSyncLog $modelLog;

    // Limite de registros por execução do cron (100/min)
    private const BATCH_SIZE = 100;

    // Máximo de tentativas antes de desistir
    private const MAX_TENTATIVAS = 3;

    public function __construct()
    {
        $this->crmManager = new CrmManager();
        $this->modelQueue = new ModelCrmSyncQueue();
        $this->modelLog = new ModelCrmSyncLog();
    }

    /**
     * Processa 100 itens da fila - chamado pelo cron a cada minuto
     */
    public function processar(): array
    {
        $inicio = microtime(true);
        $processados = 0;
        $erros = 0;

        // Busca 100 itens não processados (ordenados por prioridade)
        $itens = $this->modelQueue->buscarPendentes(self::BATCH_SIZE);

        foreach ($itens as $item) {
            try {
                $this->processarItem($item);
                $processados++;
            } catch (\Exception $e) {
                $erros++;
                $this->registrarErro($item, $e);
            }
        }

        $tempo = round(microtime(true) - $inicio, 2);

        return [
            'processados' => $processados,
            'erros' => $erros,
            'tempo_segundos' => $tempo,
            'taxa' => $processados > 0 ? round($processados / $tempo, 2) : 0
        ];
    }

    /**
     * Processa um único item da fila - SEM DELAY
     */
    private function processarItem(array $item): void
    {
        $provider = $this->crmManager->obterProvider($item['id_loja']);

        switch ($item['direcao']) {
            case 'ecletech_para_crm':
                $this->sincronizarParaCrm($provider, $item);
                break;

            case 'crm_para_ecletech':
                $this->sincronizarParaEcletech($provider, $item);
                break;
        }

        // Marca como processado
        $this->modelQueue->marcarProcessado($item['id'], [
            'processado' => 1,
            'processado_em' => date('Y-m-d H:i:s')
        ]);
    }

    private function sincronizarParaCrm($provider, array $item): void
    {
        // Busca dados do Ecletech
        $model = $this->obterModel($item['entidade']);
        $dados = $model->buscarPorId($item['id_registro']);

        if (!$dados) {
            throw new \Exception("Registro {$item['entidade']}#{$item['id_registro']} não encontrado");
        }

        // Se já tem external_id, atualiza. Senão, cria
        if (!empty($dados['external_id'])) {
            $provider->atualizar($item['entidade'], $dados['external_id'], $dados, $item['id_loja']);
        } else {
            $resultado = $provider->criar($item['entidade'], $dados, $item['id_loja']);
            // Salva external_id no Ecletech
            $model->atualizar($item['id_registro'], ['external_id' => $resultado['external_id']]);
        }

        $this->registrarLog($item, 'sucesso', 'Sincronizado para CRM');
    }

    private function sincronizarParaEcletech($provider, array $item): void
    {
        // Busca dados do CRM
        $model = $this->obterModel($item['entidade']);
        $dadosLocais = $model->buscarPorId($item['id_registro']);

        if (!$dadosLocais || empty($dadosLocais['external_id'])) {
            throw new \Exception("External ID não encontrado para {$item['entidade']}#{$item['id_registro']}");
        }

        $dadosCrm = $provider->buscar($item['entidade'], $dadosLocais['external_id'], $item['id_loja']);

        // Atualiza no Ecletech
        $handler = $provider->obterHandler($item['entidade']);
        $dadosTransformados = $handler->transformarParaInterno($dadosCrm);
        $model->atualizar($item['id_registro'], $dadosTransformados);

        $this->registrarLog($item, 'sucesso', 'Sincronizado do CRM');
    }

    private function registrarErro(array $item, \Exception $e): void
    {
        // Incrementa tentativas
        $tentativas = $item['tentativas'] + 1;

        $update = [
            'tentativas' => $tentativas,
            'erro' => $e->getMessage()
        ];

        // Se atingiu máximo, marca como processado (para não tentar mais)
        if ($tentativas >= self::MAX_TENTATIVAS) {
            $update['processado'] = 1;
            $update['processado_em'] = date('Y-m-d H:i:s');
        }

        $this->modelQueue->atualizar($item['id'], $update);
        $this->registrarLog($item, 'erro', $e->getMessage());
    }

    private function registrarLog(array $item, string $status, string $mensagem): void
    {
        $this->modelLog->criar([
            'id_loja' => $item['id_loja'],
            'entidade' => $item['entidade'],
            'id_registro' => $item['id_registro'],
            'direcao' => $item['direcao'],
            'status' => $status,
            'mensagem' => $mensagem,
            'criado_em' => date('Y-m-d H:i:s')
        ]);
    }

    private function obterModel(string $entidade): object
    {
        $className = 'App\\Models\\Model' . ucfirst($entidade);
        return new $className();
    }
}
```

**Características:**
- ✅ **Nenhum delay** - processa 100 itens o mais rápido possível
- ✅ **Controle de tentativas** - para após 3 falhas
- ✅ **Logs detalhados** - facilita monitoramento
- ✅ **Priorização** - processa itens mais importantes primeiro

---

## 3. ModelCrmSyncQueue.php

```php
<?php

namespace App\CRM\Models;

use App\Core\Database;

class ModelCrmSyncQueue
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Busca itens pendentes (ordenados por prioridade e data)
     */
    public function buscarPendentes(int $limit = 100): array
    {
        return $this->db->query("
            SELECT *
            FROM crm_sync_queue
            WHERE processado = 0
              AND tentativas < 3
            ORDER BY prioridade DESC, criado_em ASC
            LIMIT :limit
        ", ['limit' => $limit]);
    }

    /**
     * Adiciona item na fila
     */
    public function enfileirar(int $idLoja, string $entidade, int $idRegistro, string $direcao, int $prioridade = 0): int
    {
        // Evita duplicatas
        $existe = $this->db->queryOne("
            SELECT id FROM crm_sync_queue
            WHERE entidade = :entidade
              AND id_registro = :id_registro
              AND direcao = :direcao
              AND processado = 0
        ", [
            'entidade' => $entidade,
            'id_registro' => $idRegistro,
            'direcao' => $direcao
        ]);

        if ($existe) {
            return $existe['id'];
        }

        return $this->db->insert('crm_sync_queue', [
            'id_loja' => $idLoja,
            'entidade' => $entidade,
            'id_registro' => $idRegistro,
            'direcao' => $direcao,
            'prioridade' => $prioridade
        ]);
    }

    public function marcarProcessado(int $id, array $dados): void
    {
        $this->db->update('crm_sync_queue', $dados, ['id' => $id]);
    }

    public function atualizar(int $id, array $dados): void
    {
        $this->db->update('crm_sync_queue', $dados, ['id' => $id]);
    }

    /**
     * Remove itens processados há mais de 7 dias (limpeza)
     */
    public function limparAntigos(): int
    {
        return $this->db->execute("
            DELETE FROM crm_sync_queue
            WHERE processado = 1
              AND processado_em < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }
}
```

---

## 4. Configuração do Cron

### 4.1 Sincronização Contínua (100/min)

```bash
# /etc/crontab ou crontab -e

# Sincroniza fila a cada minuto (100 itens)
* * * * * /usr/bin/php /var/www/ecletech/cron/crm_sync.php >> /var/log/ecletech/crm_sync.log 2>&1

# Limpeza de itens antigos (diário às 3h)
0 3 * * * /usr/bin/php /var/www/ecletech/cron/crm_cleanup.php >> /var/log/ecletech/crm_cleanup.log 2>&1
```

### 4.2 Sincronização Completa Periódica

```bash
# Sincronização completa de todos os clientes (diário às 2h)
0 2 * * * /usr/bin/php /var/www/ecletech/cron/crm_sync_full_clientes.php >> /var/log/ecletech/crm_full.log 2>&1

# Sincronização completa de produtos (às 4h)
0 4 * * * /usr/bin/php /var/www/ecletech/cron/crm_sync_full_produtos.php >> /var/log/ecletech/crm_full.log 2>&1
```

---

## 5. Scripts Cron

### 5.1 crm_sync.php (Principal - 100/min)

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\CRM\Services\ServiceCrmCron;

try {
    $service = new ServiceCrmCron();
    $resultado = $service->processar();

    echo sprintf(
        "[%s] Processados: %d | Erros: %d | Tempo: %ss | Taxa: %s req/s\n",
        date('Y-m-d H:i:s'),
        $resultado['processados'],
        $resultado['erros'],
        $resultado['tempo_segundos'],
        $resultado['taxa']
    );

    exit(0);
} catch (\Exception $e) {
    echo sprintf("[%s] ERRO: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    exit(1);
}
```

### 5.2 crm_cleanup.php (Limpeza)

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\CRM\Models\ModelCrmSyncQueue;

try {
    $model = new ModelCrmSyncQueue();
    $removidos = $model->limparAntigos();

    echo sprintf(
        "[%s] Limpeza concluída: %d registros removidos\n",
        date('Y-m-d H:i:s'),
        $removidos
    );

    exit(0);
} catch (\Exception $e) {
    echo sprintf("[%s] ERRO: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    exit(1);
}
```

### 5.3 crm_sync_full_clientes.php (Sincronização Completa)

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\CRM\Models\ModelCrmSyncQueue;
use App\Models\ModelCliente;

try {
    $queueModel = new ModelCrmSyncQueue();
    $clienteModel = new ModelCliente();

    $idLoja = 1; // Ajustar conforme necessário

    // Busca todos os clientes que precisam sincronizar
    $clientes = $clienteModel->buscarTodos($idLoja);

    $enfileirados = 0;
    foreach ($clientes as $cliente) {
        // Enfileira com baixa prioridade (0)
        $queueModel->enfileirar(
            $idLoja,
            'cliente',
            $cliente['id'],
            'ecletech_para_crm',
            0
        );
        $enfileirados++;
    }

    echo sprintf(
        "[%s] Sincronização completa: %d clientes enfileirados\n",
        date('Y-m-d H:i:s'),
        $enfileirados
    );

    // Estimativa de tempo (100/min)
    $minutos = ceil($enfileirados / 100);
    echo sprintf("Tempo estimado: ~%d minutos\n", $minutos);

    exit(0);
} catch (\Exception $e) {
    echo sprintf("[%s] ERRO: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    exit(1);
}
```

---

## 6. Como Enfileirar Alterações em Tempo Real

### 6.1 Hook no Model (Trigger Automático)

```php
<?php

namespace App\Models;

use App\CRM\Models\ModelCrmSyncQueue;

class ModelCliente extends Model
{
    private ModelCrmSyncQueue $syncQueue;

    public function __construct()
    {
        parent::__construct();
        $this->syncQueue = new ModelCrmSyncQueue();
    }

    public function criar(array $dados): int
    {
        $id = parent::criar($dados);

        // Enfileira para sincronizar (alta prioridade = 10)
        $this->syncQueue->enfileirar(
            $dados['id_loja'],
            'cliente',
            $id,
            'ecletech_para_crm',
            10 // Alta prioridade para novos registros
        );

        return $id;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $resultado = parent::atualizar($id, $dados);

        if ($resultado) {
            $cliente = $this->buscarPorId($id);

            // Enfileira para sincronizar (média prioridade = 5)
            $this->syncQueue->enfileirar(
                $cliente['id_loja'],
                'cliente',
                $id,
                'ecletech_para_crm',
                5
            );
        }

        return $resultado;
    }
}
```

---

## 7. Monitoramento

### 7.1 Dashboard de Status

```php
<?php

use App\CRM\Models\ModelCrmSyncQueue;

$model = new ModelCrmSyncQueue();

// Pendentes na fila
$pendentes = $model->contarPendentes();

// Processados hoje
$processadosHoje = $model->contarProcessadosHoje();

// Erros nas últimas 24h
$erros = $model->contarErros24h();

// Tempo médio de processamento
$tempoMedio = $model->tempoMedioProcessamento();
```

### 7.2 Query para Monitoramento

```sql
-- Itens na fila por entidade
SELECT
    entidade,
    direcao,
    COUNT(*) as total,
    SUM(CASE WHEN processado = 0 THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN processado = 1 THEN 1 ELSE 0 END) as processados
FROM crm_sync_queue
WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY entidade, direcao;

-- Taxa de sucesso nas últimas 24h
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN erro IS NULL THEN 1 ELSE 0 END) as sucesso,
    SUM(CASE WHEN erro IS NOT NULL THEN 1 ELSE 0 END) as falhas,
    ROUND(SUM(CASE WHEN erro IS NULL THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as taxa_sucesso
FROM crm_sync_queue
WHERE processado = 1
  AND processado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

---

## 8. Exemplo de Fluxo Completo

### Cenário: Cliente cadastrado no Ecletech

```
1. Usuário cadastra cliente no Ecletech
   └─> ModelCliente->criar()
       └─> Enfileira em crm_sync_queue (prioridade 10)

2. Cron executa (próximo minuto)
   └─> ServiceCrmCron->processar()
       └─> Busca 100 itens (incluindo este cliente)
       └─> Processa cada item SEM DELAY
           ├─> Busca dados do cliente
           ├─> Transforma para formato CRM
           ├─> Envia para GestaoClick API
           ├─> Recebe external_id
           ├─> Salva external_id no Ecletech
           └─> Marca como processado

3. Cliente sincronizado em até 60 segundos
```

### Tempo de Sincronização

| Registros | Tempo (100/min) |
|-----------|-----------------|
| 100       | ~1 minuto       |
| 500       | ~5 minutos      |
| 1.000     | ~10 minutos     |
| 10.000    | ~100 minutos    |

---

## 9. Vantagens vs Delays Artificiais

| Aspecto | Com usleep() | Com Cron |
|---------|-------------|----------|
| **Performance** | ❌ Bloqueio desnecessário | ✅ Máxima eficiência |
| **Controle** | ❌ Difícil ajustar taxa | ✅ Fácil ajustar cron |
| **Escalabilidade** | ❌ Execução longa | ✅ Execuções curtas |
| **Resiliência** | ❌ Falha afeta lote | ✅ Falha isolada |
| **Logs** | ❌ Log gigante | ✅ Log por execução |
| **Monitoramento** | ❌ Difícil rastrear | ✅ Fácil rastrear |

---

## 10. Ajustes Finos

### 10.1 Aumentar Taxa (200/min)

```bash
# Executa a cada 30 segundos (200/min)
* * * * * /usr/bin/php /var/www/ecletech/cron/crm_sync.php
* * * * * sleep 30; /usr/bin/php /var/www/ecletech/cron/crm_sync.php
```

### 10.2 Múltiplas Lojas

```bash
# Loja 1
* * * * * /usr/bin/php /var/www/ecletech/cron/crm_sync.php --loja=1

# Loja 2 (offset de 30s)
* * * * * sleep 30; /usr/bin/php /var/www/ecletech/cron/crm_sync.php --loja=2
```

### 10.3 Priorização por Entidade

```php
// Alta prioridade: vendas e atividades (sincronização imediata)
$prioridades = [
    'venda' => 10,
    'atividade' => 10,
    'cliente' => 5,
    'produto' => 2
];
```

---

## Conclusão

✅ **100 requisições/minuto** controladas pelo cron
✅ **Sem delays** no código PHP
✅ **Escalável** - fácil ajustar frequência
✅ **Resiliente** - falhas não bloqueiam
✅ **Monitorável** - logs estruturados por execução

Essa abordagem é **mais eficiente**, **mais confiável** e **mais fácil de manter** que usar delays artificiais no código.
