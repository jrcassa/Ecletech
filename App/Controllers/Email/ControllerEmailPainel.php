<?php

namespace App\Controllers\Email;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Email\ServiceEmail;

/**
 * Controller para o painel de gerenciamento de emails
 */
class ControllerEmailPainel extends Controller
{
    private ServiceEmail $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ServiceEmail();
    }

    /**
     * GET /email/painel/dashboard
     * Retorna dados para o dashboard
     */
    public function dashboard(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar painel', 403);
        }

        $stats = $this->service->obterEstatisticas();

        return $this->sucesso($stats);
    }

    /**
     * POST /email/painel/processar
     * Processa fila manualmente
     */
    public function processar(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.alterar')) {
            return $this->erro('Sem permissão para processar fila', 403);
        }

        $dados = $request->getBody();
        $limit = $dados['limit'] ?? null;

        $resultado = $this->service->processarFila($limit);

        return $this->sucesso([
            'mensagem' => 'Fila processada com sucesso',
            'processados' => $resultado['processados'],
            'enviados' => $resultado['enviados'],
            'erros' => $resultado['erros']
        ]);
    }
}
