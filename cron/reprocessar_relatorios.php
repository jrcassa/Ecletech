<?php

/**
 * Cron Job: Reprocessa relatórios com erro de envio
 * Execução: A cada 2 horas
 * Crontab: 0 */2 * * * /usr/bin/php /path/to/Ecletech/cron/reprocessar_relatorios.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Carrega o autoloader
require __DIR__ . '/../vendor/autoload.php';

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../App/';

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
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando reprocessamento de relatórios com erro...\n";

    // Carrega as variáveis de ambiente
    $caminhoEnv = __DIR__ . '/../.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Instancia os models
    $modelLog = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioLog();
    $serviceWhatsapp = new \App\Services\Whatsapp\ServiceWhatsapp();

    // Busca logs com erro das últimas 24h e com menos de 3 tentativas
    $db = \App\Core\BancoDados::obterInstancia()->obterConexao();

    $sql = "SELECT *
            FROM frotas_abastecimentos_relatorios_logs
            WHERE status_envio = 'erro'
            AND tentativas < 3
            AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY criado_em DESC
            LIMIT 10";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $logsErro = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $totalReprocessados = 0;
    $totalSucesso = 0;
    $totalFalha = 0;

    foreach ($logsErro as $log) {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Reprocessando log ID {$log['id']} (tentativa " . ($log['tentativas'] + 1) . ")...\n";

            // Incrementa tentativas
            $sqlUpdate = "UPDATE frotas_abastecimentos_relatorios_logs
                         SET tentativas = tentativas + 1,
                             atualizado_em = NOW()
                         WHERE id = ?";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([$log['id']]);

            // Tenta reenviar
            $serviceWhatsapp->enviarMensagem($log['telefone'], $log['mensagem']);

            // Marca como enviado
            $modelLog->marcarEnviado($log['id']);
            $totalSucesso++;

            echo "[" . date('Y-m-d H:i:s') . "] Log ID {$log['id']} enviado com sucesso!\n";

        } catch (\Exception $e) {
            // Marca erro novamente
            $modelLog->marcarErro($log['id'], $e->getMessage());
            $totalFalha++;

            echo "[" . date('Y-m-d H:i:s') . "] Falha ao reprocessar log ID {$log['id']}: " . $e->getMessage() . "\n";
        }

        $totalReprocessados++;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Reprocessamento concluído!\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total: {$totalReprocessados} | Sucesso: {$totalSucesso} | Falha: {$totalFalha}\n";

    exit(0);

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n";

    // Log do erro
    error_log("Erro no cron de reprocessamento de relatórios: " . $e->getMessage());

    exit(1);
}
