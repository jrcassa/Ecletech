<?php

use App\Controllers\Diagnostico\ControllerDiagnostico;

/**
 * Rotas de diagnóstico do sistema
 * ATENÇÃO: Estas rotas devem ser protegidas ou removidas em produção
 */

return function($router) {
    $router->grupo(['prefixo' => 'diagnostico'], function($router) {
        // Diagnóstico CSRF e sessão (não requer autenticação para facilitar debug)
        $router->get('/csrf', [ControllerDiagnostico::class, 'csrf']);

        // Health check geral
        $router->get('/health', [ControllerDiagnostico::class, 'health']);
    });
};
