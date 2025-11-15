<?php

/**
 * Script cron para limpeza de registros antigos CRM
 *
 * Uso: 0 3 * * * /usr/bin/php /var/www/ecletech/cron/crm_cleanup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\ModelCrmSyncQueue;
use App\Models\ModelCrmSyncLog;

try {
    $queueModel = new ModelCrmSyncQueue();
    $logModel = new ModelCrmSyncLog();

    // Remove itens processados da fila com mais de 7 dias
    $removidosQueue = $queueModel->limparAntigos(7);

    // Remove logs com mais de 30 dias
    $removidosLog = $logModel->limparAntigos(30);

    echo sprintf(
        "[%s] Limpeza concluída: %d itens da fila, %d logs removidos\n",
        date('Y-m-d H:i:s'),
        $removidosQueue,
        $removidosLog
    );

    exit(0);
} catch (\Exception $e) {
    echo sprintf("[%s] ERRO: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    exit(1);
}
