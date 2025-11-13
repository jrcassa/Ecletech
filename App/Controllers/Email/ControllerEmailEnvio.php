<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Services\Email\ServiceEmail;
use App\Models\Email\ModelEmailQueue;
use App\Models\Email\ModelEmailHistorico;
use App\Services\ACL\ServiceACL;

/**
 * Controller para gerenciar envio de emails
 */
class ControllerEmailEnvio extends BaseController
{
    private ServiceEmail $service;
    private ModelEmailQueue $queueModel;
    private ModelEmailHistorico $historicoModel;
    private ServiceACL $acl;

    public function __construct()
    {
        $this->service = new ServiceEmail();
        $this->queueModel = new ModelEmailQueue();
        $this->historicoModel = new ModelEmailHistorico();
        $this->acl = new ServiceACL();
    }

    /**
     * POST /email/enviar
     * Envia um email
     */
    public function enviar(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.alterar')) {
                $this->proibido('Sem permissão para enviar emails');
                return;
            }

            // Valida dados obrigatórios
            $dados = $this->obterDados();

            if (empty($dados['destinatario'])) {
                $this->erro('Destinatário é obrigatório');
                return;
            }

            if (empty($dados['assunto'])) {
                $this->erro('Assunto é obrigatório');
                return;
            }

            if (empty($dados['corpo']) && empty($dados['corpo_html']) && empty($dados['corpo_texto'])) {
                $this->erro('Corpo do email é obrigatório');
                return;
            }

            // Envia email
            $resultado = $this->service->enviarEmail($dados);

            if ($resultado['sucesso']) {
                $this->sucesso($resultado);
            } else {
                $this->erro($resultado['erro'] ?? 'Erro ao enviar email');
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao enviar email');
        }
    }

    /**
     * GET /email/fila
     * Lista fila de emails
     */
    public function listarFila(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar fila de emails');
                return;
            }

            $status = $this->obterParametro('status');
            $limit = (int) $this->obterParametro('limit', 50);
            $offset = (int) $this->obterParametro('offset', 0);

            if ($status !== null) {
                $emails = $this->queueModel->buscarPorStatus((int) $status, $limit, $offset);
            } else {
                $emails = $this->queueModel->listar($limit, $offset);
            }

            $this->sucesso([
                'itens' => $emails,
                'total' => count($emails),
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar fila');
        }
    }

    /**
     * DELETE /email/fila/{id}
     * Cancela email da fila
     */
    public function cancelarFila(int $id): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.alterar')) {
                $this->proibido('Sem permissão para cancelar emails');
                return;
            }

            $email = $this->queueModel->buscarPorId($id);

            if (!$email) {
                $this->naoEncontrado('Email não encontrado na fila');
                return;
            }

            // Deleta da fila
            $this->queueModel->deletar($id);

            $this->sucesso(['mensagem' => 'Email cancelado com sucesso']);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao cancelar email');
        }
    }

    /**
     * GET /email/estatisticas
     * Retorna estatísticas do sistema
     */
    public function estatisticas(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar estatísticas');
                return;
            }

            $stats = $this->service->obterEstatisticas();

            $this->sucesso($stats);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter estatísticas');
        }
    }

    /**
     * GET /email/historico
     * Lista histórico de emails
     */
    public function historico(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar histórico');
                return;
            }

            $filtros = [
                'data_inicio' => $this->obterParametro('data_inicio'),
                'data_fim' => $this->obterParametro('data_fim'),
                'status_code' => $this->obterParametro('status_code'),
                'destinatario_email' => $this->obterParametro('destinatario')
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($v) => $v !== null && $v !== '');

            $limit = (int) $this->obterParametro('limit', 50);
            $offset = (int) $this->obterParametro('offset', 0);

            $historico = $this->historicoModel->buscar($filtros, $limit, $offset);
            $total = $this->historicoModel->contar($filtros);

            $this->sucesso([
                'itens' => $historico,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter histórico');
        }
    }
}
