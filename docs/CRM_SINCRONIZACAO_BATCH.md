# 📦 SINCRONIZAÇÃO EM BATCH - CRM

**Como sincronizar milhares de registros respeitando limites da API**

---

## 🎯 PROBLEMA

APIs externas têm limites:
- ✅ GestaoClick: **100 registros/request**
- ✅ Pipedrive: **100 registros/request**
- ✅ Bling: **100 registros/request**
- ✅ Rate limit: **100 requests/minuto**

**Cenário:**
- Você tem **10.000 clientes** no Ecletech
- Precisa sincronizar todos para o CRM
- Limite: 100 por request
- **São necessários 100 requests!**

---

## 📋 SOLUÇÃO: SINCRONIZAÇÃO EM BATCH

### Conceito

```
ECLETECH                                    CRM EXTERNO
10.000 clientes                             0 clientes
     ↓
┌────────────────────────────────────┐
│ BATCH 1 (100 clientes)             │
│ Request 1/100                      │──→ POST /customers (bulk)
└────────────────────────────────────┘    ✓ 100 criados

┌────────────────────────────────────┐
│ BATCH 2 (100 clientes)             │
│ Request 2/100                      │──→ POST /customers (bulk)
└────────────────────────────────────┘    ✓ 200 criados

┌────────────────────────────────────┐
│ BATCH 3 (100 clientes)             │
│ Request 3/100                      │──→ POST /customers (bulk)
└────────────────────────────────────┘    ✓ 300 criados

...

┌────────────────────────────────────┐
│ BATCH 100 (100 clientes)           │
│ Request 100/100                    │──→ POST /customers (bulk)
└────────────────────────────────────┘    ✓ 10.000 criados

Tempo estimado: 100 requests × 2s = ~3-4 minutos
```

---

## 💻 IMPLEMENTAÇÃO

### 1. Sincronização Ecletech → CRM (Batch Export)

**Cenário:** Enviar 10.000 clientes do Ecletech para CRM

#### Código: ServiceCrmBatch.php

```php
<?php

namespace App\CRM\Services;

use App\CRM\Core\CrmManager;
use App\Models\Cliente\ModelCliente;

class ServiceCrmBatch
{
    private CrmManager $manager;
    private ModelCliente $modelCliente;

    public function __construct()
    {
        $this->manager = new CrmManager();
        $this->modelCliente = new ModelCliente();
    }

    /**
     * Sincronizar TODOS os clientes em batch
     */
    public function sincronizarTodosClientesParaCrm(int $idLoja): array
    {
        $inicio = microtime(true);

        // 1. Buscar integração
        $integracao = $this->buscarIntegracao($idLoja);

        if (!$integracao) {
            return ['success' => false, 'message' => 'Integração não configurada'];
        }

        // 2. Obter provider
        $provider = $this->manager->obterProvider($integracao['provider']);

        // 3. Configurações de batch
        $batchSize = $integracao['configuracoes']['batch_size'] ?? 100;
        $offset = 0;
        $totalProcessados = 0;
        $totalCriados = 0;
        $totalAtualizados = 0;
        $totalErros = 0;
        $erros = [];

        echo "Iniciando sincronização em batch...\n";
        echo "Batch size: {$batchSize}\n\n";

        // 4. Loop paginado
        do {
            // Buscar lote de clientes
            $clientes = $this->modelCliente->buscarPaginado($idLoja, $offset, $batchSize);

            $totalNesteBatch = count($clientes);

            if ($totalNesteBatch === 0) {
                break; // Não há mais registros
            }

            echo "Processando batch {$offset}-" . ($offset + $totalNesteBatch) . "...\n";

            // Processar cada cliente do batch
            foreach ($clientes as $cliente) {
                try {
                    $resultado = $this->sincronizarCliente(
                        $provider,
                        $cliente,
                        $idLoja,
                        $integracao
                    );

                    $totalProcessados++;

                    if ($resultado['operacao'] === 'create') {
                        $totalCriados++;
                    } elseif ($resultado['operacao'] === 'update') {
                        $totalAtualizados++;
                    }

                    echo "  ✓ Cliente #{$cliente['id']} - {$cliente['nome']}\n";

                } catch (\Exception $e) {
                    $totalErros++;
                    $erros[] = [
                        'cliente_id' => $cliente['id'],
                        'erro' => $e->getMessage()
                    ];

                    echo "  ✗ Cliente #{$cliente['id']} - ERRO: {$e->getMessage()}\n";
                }

                // Rate limiting: Delay entre requisições
                usleep(100000); // 100ms = 10 requests/segundo
            }

            $offset += $batchSize;

            echo "Batch concluído. Total processados: {$totalProcessados}\n\n";

            // Delay entre batches (evitar rate limit)
            sleep(1);

        } while ($totalNesteBatch === $batchSize);

        $duracao = round(microtime(true) - $inicio, 2);

        echo "\n=== SINCRONIZAÇÃO CONCLUÍDA ===\n";
        echo "Total processados: {$totalProcessados}\n";
        echo "Criados: {$totalCriados}\n";
        echo "Atualizados: {$totalAtualizados}\n";
        echo "Erros: {$totalErros}\n";
        echo "Duração: {$duracao}s\n";

        return [
            'success' => true,
            'total_processados' => $totalProcessados,
            'total_criados' => $totalCriados,
            'total_atualizados' => $totalAtualizados,
            'total_erros' => $totalErros,
            'erros' => $erros,
            'duracao_segundos' => $duracao
        ];
    }

    /**
     * Sincronizar um cliente individual
     */
    private function sincronizarCliente(
        $provider,
        array $cliente,
        int $idLoja,
        array $integracao
    ): array {
        if ($cliente['external_id']) {
            // ATUALIZAR
            $provider->atualizar('cliente', $cliente['external_id'], $cliente, $idLoja);

            return ['operacao' => 'update'];

        } else {
            // CRIAR
            $resultado = $provider->criar('cliente', $cliente, $idLoja);

            // Atualizar external_id
            $this->modelCliente->atualizar($cliente['id'], [
                'external_id' => $resultado['external_id']
            ]);

            return [
                'operacao' => 'create',
                'external_id' => $resultado['external_id']
            ];
        }
    }

    private function buscarIntegracao(int $idLoja): ?array
    {
        $db = \App\Core\BancoDados::obterInstancia();

        return $db->buscarUm(
            "SELECT * FROM crm_integracoes WHERE id_loja = ? AND ativo = 1",
            [$idLoja]
        );
    }
}
```

#### Model com Paginação

```php
// ModelCliente.php

public function buscarPaginado(int $idLoja, int $offset, int $limite): array
{
    return $this->db->buscarTodos(
        "SELECT * FROM clientes
         WHERE id_loja = ?
           AND deletado_em IS NULL
         ORDER BY id ASC
         LIMIT ? OFFSET ?",
        [$idLoja, $limite, $offset]
    );
}
```

#### Output Exemplo

```
Iniciando sincronização em batch...
Batch size: 100

Processando batch 0-100...
  ✓ Cliente #1 - João Silva
  ✓ Cliente #2 - Maria Santos
  ✓ Cliente #3 - Pedro Oliveira
  ...
  ✓ Cliente #100 - Ana Costa
Batch concluído. Total processados: 100

Processando batch 100-200...
  ✓ Cliente #101 - Carlos Souza
  ✓ Cliente #102 - Fernanda Lima
  ...
  ✓ Cliente #200 - Roberto Alves
Batch concluído. Total processados: 200

...

Processando batch 9900-10000...
  ✓ Cliente #9901 - Lucas Martins
  ...
  ✓ Cliente #10000 - Juliana Rocha
Batch concluído. Total processados: 10000

=== SINCRONIZAÇÃO CONCLUÍDA ===
Total processados: 10000
Criados: 8500
Atualizados: 1500
Erros: 0
Duração: 245.3s
```

---

### 2. Sincronização CRM → Ecletech (Batch Import)

**Cenário:** Importar 10.000 clientes do CRM para Ecletech

#### Código: ServiceCrmSync.php

```php
<?php

namespace App\CRM\Services;

class ServiceCrmSync
{
    /**
     * Sincronizar do CRM em batch (paginado)
     */
    public function sincronizarDoCrmEmBatch(int $idLoja, string $entityType = 'cliente'): array
    {
        $inicio = microtime(true);

        // 1. Buscar integração
        $integracao = $this->buscarIntegracao($idLoja);

        if (!$integracao) {
            return ['success' => false, 'message' => 'Integração não configurada'];
        }

        // 2. Obter provider
        $manager = new CrmManager();
        $provider = $manager->obterProvider($integracao['provider']);

        // 3. Configurações
        $limite = $integracao['configuracoes']['batch_size'] ?? 100;
        $pagina = 1;
        $totalProcessados = 0;
        $totalCriados = 0;
        $totalAtualizados = 0;
        $totalErros = 0;
        $erros = [];

        echo "Iniciando importação do CRM...\n";
        echo "Limite por página: {$limite}\n\n";

        // 4. Loop paginado
        do {
            echo "Buscando página {$pagina}...\n";

            // Buscar página do CRM
            $resultado = $provider->buscar($entityType, $pagina, $limite, $idLoja);

            /* $resultado = [
                'dados' => [...],  // Array de clientes
                'total' => 10000,
                'pagina_atual' => 1,
                'total_paginas' => 100
            ] */

            $totalNestaPagina = count($resultado['dados']);

            echo "Recebidos {$totalNestaPagina} registros\n";

            // Processar cada item
            foreach ($resultado['dados'] as $itemExterno) {
                try {
                    $acao = $this->processarItem($idLoja, $integracao, $entityType, $itemExterno);

                    $totalProcessados++;

                    if ($acao === 'criado') {
                        $totalCriados++;
                    } elseif ($acao === 'atualizado') {
                        $totalAtualizados++;
                    }

                } catch (\Exception $e) {
                    $totalErros++;
                    $erros[] = [
                        'external_id' => $itemExterno['external_id'] ?? 'unknown',
                        'erro' => $e->getMessage()
                    ];
                }
            }

            echo "Página {$pagina}/{$resultado['total_paginas']} concluída. Total: {$totalProcessados}\n\n";

            $pagina++;

            // Delay entre páginas (rate limiting)
            if ($pagina <= $resultado['total_paginas']) {
                sleep(1);
            }

        } while ($pagina <= $resultado['total_paginas']);

        $duracao = round(microtime(true) - $inicio, 2);

        echo "\n=== IMPORTAÇÃO CONCLUÍDA ===\n";
        echo "Total processados: {$totalProcessados}\n";
        echo "Criados: {$totalCriados}\n";
        echo "Atualizados: {$totalAtualizados}\n";
        echo "Erros: {$totalErros}\n";
        echo "Duração: {$duracao}s\n";

        return [
            'success' => true,
            'total_processados' => $totalProcessados,
            'total_criados' => $totalCriados,
            'total_atualizados' => $totalAtualizados,
            'total_erros' => $totalErros,
            'erros' => $erros,
            'duracao_segundos' => $duracao
        ];
    }

    private function processarItem(
        int $idLoja,
        array $integracao,
        string $entityType,
        array $itemExterno
    ): string {
        // Buscar por external_id
        $modelCliente = new \App\Models\Cliente\ModelCliente();
        $cliente = $modelCliente->buscarPorExternalId($itemExterno['external_id']);

        if ($cliente) {
            // ATUALIZAR
            $modelCliente->atualizar($cliente['id'], $itemExterno);
            return 'atualizado';

        } else {
            // Verificar duplicação por email
            $existe = $modelCliente->buscarPorEmail($itemExterno['email']);

            if ($existe) {
                // Apenas vincular
                $modelCliente->atualizar($existe['id'], [
                    'external_id' => $itemExterno['external_id']
                ]);
                return 'vinculado';
            }

            // CRIAR
            $modelCliente->criar([
                'id_loja' => $idLoja,
                'external_id' => $itemExterno['external_id'],
                ...$itemExterno
            ]);

            return 'criado';
        }
    }
}
```

#### Output Exemplo

```
Iniciando importação do CRM...
Limite por página: 100

Buscando página 1...
Recebidos 100 registros
Página 1/100 concluída. Total: 100

Buscando página 2...
Recebidos 100 registros
Página 2/100 concluída. Total: 200

...

Buscando página 100...
Recebidos 100 registros
Página 100/100 concluída. Total: 10000

=== IMPORTAÇÃO CONCLUÍDA ===
Total processados: 10000
Criados: 8200
Atualizados: 1500
Vinculados: 300
Erros: 0
Duração: 312.5s
```

---

## ⚡ RATE LIMITING

### Problema

APIs têm limites:
- **100 requests/minuto** (GestaoClick)
- **120 requests/minuto** (Pipedrive)

Se enviar 200 requests em 1 minuto → **BLOQUEADO!**

### Solução 1: Delay Entre Requisições

```php
// Após cada requisição
usleep(100000); // 100ms = máx 10 requests/segundo = 600/minuto
```

**Cálculo:**
```
100ms entre requests = 10 requests/segundo
10 × 60 = 600 requests/minuto ✅
```

### Solução 2: Token Bucket

```php
class RateLimiter
{
    private int $maxRequests;
    private int $perSeconds;
    private array $timestamps = [];

    public function __construct(int $maxRequests = 100, int $perSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->perSeconds = $perSeconds;
    }

    public function aguardar(): void
    {
        $agora = time();

        // Remover timestamps antigos
        $this->timestamps = array_filter(
            $this->timestamps,
            fn($t) => $t > ($agora - $this->perSeconds)
        );

        // Se atingiu limite, aguardar
        if (count($this->timestamps) >= $this->maxRequests) {
            $maisAntigo = min($this->timestamps);
            $aguardar = ($maisAntigo + $this->perSeconds) - $agora;

            if ($aguardar > 0) {
                echo "Rate limit atingido. Aguardando {$aguardar}s...\n";
                sleep($aguardar);
            }

            // Limpar novamente
            $this->timestamps = [];
        }

        // Adicionar timestamp atual
        $this->timestamps[] = $agora;
    }
}

// Uso
$limiter = new RateLimiter(100, 60); // 100 req/min

foreach ($clientes as $cliente) {
    $limiter->aguardar();
    $provider->criar('cliente', $cliente);
}
```

### Solução 3: Retry com Backoff Exponencial

```php
private function requisicaoComRetry(
    string $metodo,
    string $endpoint,
    ?array $dados,
    int $maxTentativas = 3
): array {
    $tentativa = 0;

    while ($tentativa < $maxTentativas) {
        try {
            return $this->requisicao($metodo, $endpoint, $dados);

        } catch (\Exception $e) {
            $tentativa++;

            // Se for rate limit (429)
            if ($e->getCode() === 429) {
                $delay = pow(2, $tentativa); // 2, 4, 8 segundos

                echo "Rate limit! Tentativa {$tentativa}/{$maxTentativas}. Aguardando {$delay}s...\n";

                sleep($delay);
                continue;
            }

            // Outro erro - lançar
            throw $e;
        }
    }

    throw new \Exception("Falhou após {$maxTentativas} tentativas");
}
```

---

## 📊 BULK API (Se Disponível)

Alguns CRMs suportam **bulk API** (criar múltiplos de uma vez):

### GestaoClick Bulk API

```php
// Em vez de 100 requests individuais:
for ($i = 0; $i < 100; $i++) {
    POST /customers  // 1 cliente por vez
}

// Usar bulk:
POST /customers/bulk
{
    "customers": [
        { "name": "João", ... },
        { "name": "Maria", ... },
        ... (100 clientes)
    ]
}

// 1 request para 100 clientes! 100x mais rápido ✅
```

### Implementação

```php
// GestaoClickProvider.php

public function criarEmBulk(string $entidade, array $registros, int $idLoja): array
{
    // Limitar a 100 por request
    $chunks = array_chunk($registros, 100);
    $resultados = [];

    foreach ($chunks as $chunk) {
        $handler = $this->obterHandler($entidade);

        // Transformar todos
        $dadosTransformados = array_map(
            fn($r) => $handler->transformarParaExterno($r),
            $chunk
        );

        // Enviar bulk
        $response = $this->requisicao(
            'POST',
            '/customers/bulk',
            ['customers' => $dadosTransformados],
            $idLoja
        );

        $resultados = array_merge($resultados, $response['created'] ?? []);
    }

    return $resultados;
}
```

**Vantagens:**
- ✅ 100x mais rápido
- ✅ Menos requisições
- ✅ Menos chance de rate limit

**Desvantagens:**
- ❌ Nem todos os CRMs suportam
- ❌ Mais difícil tratar erros individuais

---

## 🔄 PROCESSAMENTO ASSÍNCRONO (Filas)

Para **grandes volumes** (10.000+ registros), usar **fila assíncrona**:

### Fluxo

```
1. Usuário clica "Sincronizar Tudo"
   ↓
2. Sistema cria JOB na fila
   INSERT INTO crm_fila (tipo_job='sync_clientes', status='pendente')
   ↓
3. Retorna IMEDIATAMENTE para o usuário
   "Sincronização agendada! Acompanhe o progresso..."
   ↓
4. Worker processa em background
   php cli/crm-worker.php
   ↓
5. Atualiza progresso
   UPDATE crm_fila SET progresso='45%', status='processando'
   ↓
6. Usuário vê progresso em tempo real (polling ou websocket)
```

### Tabela de Fila

```sql
CREATE TABLE crm_fila (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_loja INT NOT NULL,

    tipo_job ENUM('sync_clientes', 'sync_vendas', 'sync_produtos'),

    status ENUM('pendente', 'processando', 'concluido', 'erro') DEFAULT 'pendente',
    progresso INT DEFAULT 0,  -- 0-100%

    total_registros INT DEFAULT 0,
    registros_processados INT DEFAULT 0,

    erro TEXT NULL,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    iniciado_em DATETIME NULL,
    finalizado_em DATETIME NULL,

    INDEX idx_status (status)
);
```

### Worker

```php
// cli/crm-worker.php

while (true) {
    // Buscar job pendente
    $job = $db->buscarUm(
        "SELECT * FROM crm_fila
         WHERE status = 'pendente'
         ORDER BY id ASC
         LIMIT 1"
    );

    if (!$job) {
        sleep(5);
        continue;
    }

    // Marcar como processando
    $db->executar(
        "UPDATE crm_fila SET status='processando', iniciado_em=NOW() WHERE id=?",
        [$job['id']]
    );

    try {
        // Processar
        $service = new ServiceCrmBatch();

        $offset = 0;
        $batchSize = 100;
        $total = $db->buscarUm("SELECT COUNT(*) as total FROM clientes")['total'];

        // Atualizar total
        $db->executar(
            "UPDATE crm_fila SET total_registros=? WHERE id=?",
            [$total, $job['id']]
        );

        do {
            $clientes = $db->buscarTodos(
                "SELECT * FROM clientes LIMIT ? OFFSET ?",
                [$batchSize, $offset]
            );

            foreach ($clientes as $cliente) {
                // Sincronizar...

                // Atualizar progresso
                $processados = $offset + 1;
                $progresso = (int)(($processados / $total) * 100);

                $db->executar(
                    "UPDATE crm_fila SET registros_processados=?, progresso=? WHERE id=?",
                    [$processados, $progresso, $job['id']]
                );
            }

            $offset += $batchSize;

        } while (count($clientes) === $batchSize);

        // Marcar como concluído
        $db->executar(
            "UPDATE crm_fila SET status='concluido', finalizado_em=NOW() WHERE id=?",
            [$job['id']]
        );

    } catch (\Exception $e) {
        // Marcar como erro
        $db->executar(
            "UPDATE crm_fila SET status='erro', erro=? WHERE id=?",
            [$e->getMessage(), $job['id']]
        );
    }
}
```

### Frontend (Progresso em Tempo Real)

```jsx
// React
function SyncProgress({ jobId }) {
    const [progresso, setProgresso] = useState(0);
    const [status, setStatus] = useState('pendente');

    useEffect(() => {
        const interval = setInterval(async () => {
            const response = await fetch(`/api/crm/fila/${jobId}`);
            const data = await response.json();

            setProgresso(data.progresso);
            setStatus(data.status);

            if (data.status === 'concluido' || data.status === 'erro') {
                clearInterval(interval);
            }
        }, 2000); // Poll a cada 2s

        return () => clearInterval(interval);
    }, [jobId]);

    return (
        <div>
            <h3>Sincronizando...</h3>
            <div className="progress-bar">
                <div style={{ width: `${progresso}%` }}>{progresso}%</div>
            </div>
            <p>Status: {status}</p>
        </div>
    );
}
```

---

## 📈 ESTIMATIVA DE TEMPO

### Cálculo

```
Total de registros: 10.000
Batch size: 100
Requests necessários: 10.000 / 100 = 100

Tempo por request: ~2s (média)
Delay entre requests: 1s (rate limiting)

Tempo total = 100 × (2s + 1s) = 300s = 5 minutos
```

### Otimizações

| Método | Tempo | Observação |
|--------|-------|------------|
| **Individual (1 por request)** | 10.000 × 2s = **5,5 horas** | ❌ Muito lento |
| **Batch 100 (sem bulk API)** | 100 × 2s = **5 minutos** | ✅ Aceitável |
| **Batch 100 (com bulk API)** | 100 × 0.5s = **1 minuto** | ✅✅ Ótimo |
| **Paralelo (4 workers)** | 100 × 2s / 4 = **1,5 minutos** | ✅✅ Excelente |

---

## 🎯 RESUMO - CHECKLIST

### ✅ Sincronização Ecletech → CRM

- [ ] Buscar clientes em lotes (LIMIT/OFFSET)
- [ ] Processar 100 por vez
- [ ] Delay de 100ms entre requisições
- [ ] Retry com backoff se erro 429
- [ ] Log de progresso
- [ ] Usar bulk API se disponível

### ✅ Sincronização CRM → Ecletech

- [ ] Buscar páginas do CRM (page/limit)
- [ ] Processar até total_paginas
- [ ] Verificar duplicação (email/CPF)
- [ ] Delay de 1s entre páginas
- [ ] Atualizar external_id

### ✅ Rate Limiting

- [ ] Implementar RateLimiter
- [ ] Respeitar limites da API (100/min)
- [ ] Retry com backoff exponencial
- [ ] Monitorar headers (X-RateLimit-Remaining)

### ✅ Processamento Assíncrono

- [ ] Criar tabela crm_fila
- [ ] Worker em background
- [ ] Atualizar progresso
- [ ] Interface para acompanhar

---

## 📝 COMANDO CLI

```bash
# Sincronizar todos os clientes (batch)
php cli/crm-sync-batch.php --entity=cliente --loja=10

# Com limite personalizado
php cli/crm-sync-batch.php --entity=cliente --loja=10 --batch-size=50

# Apenas criar (ignorar atualizações)
php cli/crm-sync-batch.php --entity=cliente --loja=10 --apenas-novos

# Em background (fila)
php cli/crm-sync-batch.php --entity=cliente --loja=10 --async
```

---

**Documento:** CRM_SINCRONIZACAO_BATCH.md
**Versão:** 1.0
**Data:** Janeiro 2025
