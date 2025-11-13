<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Services\Email\ServiceEmail;
use App\Services\ACL\ServiceACL;

/**
 * Controller para o painel de gerenciamento de emails
 */
class ControllerEmailPainel extends BaseController
{
    private ServiceEmail $service;
    private ServiceACL $acl;

    public function __construct()
    {
        $this->service = new ServiceEmail();
        $this->acl = new ServiceACL();
    }

    /**
     * GET /email/painel/dashboard
     * Retorna dados para o dashboard
     */
    public function dashboard(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar painel');
                return;
            }

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
            // Valida permissão
            if (!$this->acl->temPermissao('email.alterar')) {
                $this->proibido('Sem permissão para processar fila');
                return;
            }

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
