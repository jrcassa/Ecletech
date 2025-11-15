<?php

/**
 * Script cron para sincronização CRM (100 itens/minuto)
 *
 * Uso: * * * * * /usr/bin/php /var/www/ecletech/cron/crm_sync.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ServiceCrmCron;

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
