<?php

/**
 * Cron Job: Gera snapshots diários para relatórios
 * Execução: Todo dia às 2:00
 * Crontab: 0 2 * * * /usr/bin/php /path/to/Ecletech/cron/gerar_snapshots.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Define diretório raiz
define('ROOT_PATH', dirname(__DIR__, 2));

// Carrega o autoloader do Composer (se existir)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

use App\Helpers\ErrorLogger;

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = ROOT_PATH . '/App/';

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

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando geração de snapshots...\n";

    // Carrega as variáveis de ambiente
    $caminhoEnv = ROOT_PATH . '/.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Instancia o service de relatórios
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();

    // Gera snapshot da semana anterior (domingo a sábado)
    $hoje = new DateTime();
    $ultimoDomingo = clone $hoje;
    $ultimoDomingo->modify('last sunday');
    $ultimoSabado = clone $ultimoDomingo;
    $ultimoSabado->modify('+6 days');

    echo "[" . date('Y-m-d H:i:s') . "] Gerando snapshot semanal: " .
        $ultimoDomingo->format('Y-m-d') . " a " . $ultimoSabado->format('Y-m-d') . "\n";

    $snapshotSemanalId = $service->recalcularSnapshot(
        'semanal',
        $ultimoDomingo->format('Y-m-d'),
        $ultimoSabado->format('Y-m-d')
    );

    echo "[" . date('Y-m-d H:i:s') . "] Snapshot semanal gerado: ID {$snapshotSemanalId}\n";

    // Se é dia 1, gera snapshot do mês anterior
    if ($hoje->format('j') == 1) {
        $mesAnterior = clone $hoje;
        $mesAnterior->modify('-1 month');
        $inicioMes = $mesAnterior->format('Y-m-01');
        $fimMes = $mesAnterior->format('Y-m-t');

        echo "[" . date('Y-m-d H:i:s') . "] Gerando snapshot mensal: {$inicioMes} a {$fimMes}\n";

        $snapshotMensalId = $service->recalcularSnapshot(
            'mensal',
            $inicioMes,
            $fimMes
        );

        echo "[" . date('Y-m-d H:i:s') . "] Snapshot mensal gerado: ID {$snapshotMensalId}\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Geração de snapshots concluída com sucesso!\n";

    exit(0);

} catch (\Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'cron',
        'nivel' => 'alto',
        'contexto' => [
            'cron_job' => 'gerar_snapshots',
            'descricao' => 'Erro ao gerar snapshots de relatórios'
        ]
    ]);

    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n";

    // Log do erro
    error_log("Erro no cron de geração de snapshots: " . $e->getMessage());

    exit(1);
}
