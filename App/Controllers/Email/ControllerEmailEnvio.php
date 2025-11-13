<?php

namespace App\Controllers\Email;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Email\ServiceEmail;
use App\Models\Email\ModelEmailQueue;
use App\Models\Email\ModelEmailHistorico;

/**
 * Controller para gerenciar envio de emails
 */
class ControllerEmailEnvio extends Controller
{
    private ServiceEmail $service;
    private ModelEmailQueue $queueModel;
    private ModelEmailHistorico $historicoModel;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ServiceEmail();
        $this->queueModel = new ModelEmailQueue();
        $this->historicoModel = new ModelEmailHistorico();
    }

    /**
     * POST /email/enviar
     * Envia um email
     */
    public function enviar(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.alterar')) {
            return $this->erro('Sem permissão para enviar emails', 403);
        }

        // Valida dados obrigatórios
        $dados = $request->getBody();

        if (empty($dados['destinatario'])) {
            return $this->erro('Destinatário é obrigatório');
        }

        if (empty($dados['assunto'])) {
            return $this->erro('Assunto é obrigatório');
        }

        if (empty($dados['corpo_html']) && empty($dados['corpo_texto'])) {
            return $this->erro('Corpo do email (HTML ou texto) é obrigatório');
        }

        // Envia email
        $resultado = $this->service->enviarEmail($dados);

        if ($resultado['sucesso']) {
            return $this->sucesso($resultado);
        } else {
            return $this->erro($resultado['erro'] ?? 'Erro ao enviar email');
        }
    }

    /**
     * GET /email/fila
     * Lista fila de emails
     */
    public function listarFila(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar fila de emails', 403);
        }

        $status = $request->get('status');
        $limit = (int) $request->get('limit', 50);
        $offset = (int) $request->get('offset', 0);

        if ($status !== null) {
            $emails = $this->queueModel->buscarPorStatus((int) $status, $limit, $offset);
        } else {
            $emails = $this->queueModel->listar($limit, $offset);
        }

        return $this->sucesso([
            'emails' => $emails,
            'total' => count($emails),
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * DELETE /email/fila/{id}
     * Cancela email da fila
     */
    public function cancelarFila(Request $request, array $params): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.alterar')) {
            return $this->erro('Sem permissão para cancelar emails', 403);
        }

        $id = (int) $params['id'];

        $email = $this->queueModel->buscarPorId($id);

        if (!$email) {
            return $this->erro('Email não encontrado na fila', 404);
        }

        // Deleta da fila
        $this->queueModel->deletar($id);

        return $this->sucesso(['mensagem' => 'Email cancelado com sucesso']);
    }

    /**
     * GET /email/estatisticas
     * Retorna estatísticas do sistema
     */
    public function estatisticas(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar estatísticas', 403);
        }

        $stats = $this->service->obterEstatisticas();

        return $this->sucesso($stats);
    }

    /**
     * GET /email/historico
     * Lista histórico de emails
     */
    public function historico(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar histórico', 403);
        }

        $filtros = [
            'data_inicio' => $request->get('data_inicio'),
            'data_fim' => $request->get('data_fim'),
            'status_code' => $request->get('status_code'),
            'destinatario_email' => $request->get('destinatario')
        ];

        // Remove filtros vazios
        $filtros = array_filter($filtros, fn($v) => $v !== null && $v !== '');

        $limit = (int) $request->get('limit', 50);
        $offset = (int) $request->get('offset', 0);

        $historico = $this->historicoModel->buscar($filtros, $limit, $offset);
        $total = $this->historicoModel->contar($filtros);

        return $this->sucesso([
            'historico' => $historico,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}
