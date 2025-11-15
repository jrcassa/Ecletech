<?php

/**
 * Script cron para sincronização completa de clientes
 *
 * Uso: 0 2 * * * /usr/bin/php /var/www/ecletech/cron/crm_sync_full_clientes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\ModelCrmSyncQueue;
use App\Models\Cliente\ModelCliente;
use App\Models\ModelCrmIntegracao;

try {
    $queueModel = new ModelCrmSyncQueue();
    $integracaoModel = new ModelCrmIntegracao();

    // Busca todas as lojas com CRM ativo
    $integracoes = $integracaoModel->listarAtivas();

    $totalEnfileirados = 0;

    foreach ($integracoes as $integracao) {
        $idLoja = $integracao['id_loja'];

        // Verifica se o model existe
        if (!class_exists('App\\Models\\Cliente\\ModelCliente')) {
            echo "[AVISO] ModelCliente não encontrado, pulando loja #{$idLoja}\n";
            continue;
        }

        $clienteModel = new ModelCliente();

        // Busca todos os clientes da loja
        // Nota: Ajustar método conforme implementação real do ModelCliente
        $sql = "SELECT id FROM clientes WHERE id_loja = ? AND deletado_em IS NULL";
        $clientes = $clienteModel->buscarTodos($idLoja);

        $enfileirados = 0;
        foreach ($clientes as $cliente) {
            // Enfileira com baixa prioridade (0) pois é sincronização em lote
            $queueModel->enfileirar(
                $idLoja,
                'cliente',
                $cliente['id'],
                'ecletech_para_crm',
                0
            );
            $enfileirados++;
        }

        $totalEnfileirados += $enfileirados;

        echo sprintf(
            "[%s] Loja #%d: %d clientes enfileirados\n",
            date('Y-m-d H:i:s'),
            $idLoja,
            $enfileirados
        );
    }

    // Estimativa de tempo (100/min)
    $minutos = ceil($totalEnfileirados / 100);

    echo sprintf(
        "[%s] Total: %d clientes enfileirados\n",
        date('Y-m-d H:i:s'),
        $totalEnfileirados
    );

    echo sprintf("Tempo estimado: ~%d minutos\n", $minutos);

    exit(0);
} catch (\Exception $e) {
    echo sprintf("[%s] ERRO: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    exit(1);
}
