<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Services\Email\ServiceEmail;

/**
 * Controller para o painel de gerenciamento de emails
 */
class ControllerEmailPainel extends BaseController
{
    private ServiceEmail $service;

    public function __construct()
    {
        $this->service = new ServiceEmail();
    }

    /**
     * GET /email/painel/dashboard
     * Retorna dados para o dashboard
     */
    public function dashboard(): void
    {
        try {
            $stats = $this->service->obterEstatisticas();

            $this->sucesso($stats);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao carregar dashboard');
        }
    }

    /**
     * POST /email/painel/processar
     * Processa fila manualmente
     */
    public function processar(): void
    {
        try {
            $dados = $this->obterDados();
            $limit = $dados['limit'] ?? null;

            $resultado = $this->service->processarFila($limit);

            $this->sucesso([
                'mensagem' => 'Fila processada com sucesso',
                'processados' => $resultado['processados'],
                'enviados' => $resultado['enviados'],
                'erros' => $resultado['erros']
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao processar fila');
        }
    }
}
