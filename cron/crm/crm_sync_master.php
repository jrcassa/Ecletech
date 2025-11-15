<?php

/**
 * MASTER CRON - Gerenciador de Agendamentos CRM
 *
 * Execução: A cada 1 minuto
 * Função: Verifica agendamentos prontos e cria batches para processamento
 *
 * Adicione no crontab:
 * * * * * * php /caminho/para/Ecletech/cron/crm/crm_sync_master.php >> /var/log/crm_master.log 2>&1
 */

// Carrega autoloader
require __DIR__ . '/../../vendor/autoload.php';

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../../App/';
    $tamanho = strlen($prefixo);

    if (strncmp($prefixo, $classe, $tamanho) !== 0) {
        return;
    }

    $classeRelativa = substr($classe, $tamanho);
    $arquivo = $diretorioBase . str_replace('\\', '/', $classeRelativa) . '.php';

    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

use App\Core\CarregadorEnv;
use App\Services\ServiceCrmScheduler;

try {
    $inicio = microtime(true);
    $timestamp = date('Y-m-d H:i:s');

    echo "========================================\n";
    echo "CRM SYNC MASTER - {$timestamp}\n";
    echo "========================================\n";

    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    // Cria instância do scheduler
    $scheduler = new ServiceCrmScheduler();

    // Processa agendamentos prontos
    echo "Verificando agendamentos prontos...\n";
    $resultados = $scheduler->processarAgendamentosProntos();

    if (empty($resultados)) {
        echo "✓ Nenhum agendamento pronto para execução\n";
    } else {
        $totalSucesso = 0;
        $totalErro = 0;
        $totalRegistros = 0;

        foreach ($resultados as $resultado) {
            $scheduleId = $resultado['schedule_id'];

            if ($resultado['sucesso']) {
                $registros = $resultado['registros_enfileirados'];
                $batchId = $resultado['batch_id'] ?? 'N/A';

                echo "✓ Schedule #{$scheduleId}: {$registros} registros enfileirados (Batch: {$batchId})\n";

                $totalSucesso++;
                $totalRegistros += $registros;
            } else {
                $erro = $resultado['erro'] ?? 'Erro desconhecido';
                echo "✗ Schedule #{$scheduleId}: ERRO - {$erro}\n";
                $totalErro++;
            }
        }

        echo "\n";
        echo "========================================\n";
        echo "RESUMO\n";
        echo "========================================\n";
        echo "Total de agendamentos processados: " . count($resultados) . "\n";
        echo "  ✓ Sucesso: {$totalSucesso}\n";
        echo "  ✗ Erro: {$totalErro}\n";
        echo "  📦 Total de registros enfileirados: {$totalRegistros}\n";
    }

    $fim = microtime(true);
    $duracao = round(($fim - $inicio) * 1000, 2);

    echo "========================================\n";
    echo "✓ Execução concluída em {$duracao}ms\n";
    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
