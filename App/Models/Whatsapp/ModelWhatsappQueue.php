<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para gerenciar fila de mensagens WhatsApp
 */
class ModelWhatsappQueue
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona mensagem à fila
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');
        return $this->db->inserir('whatsapp_queue', $dados);
    }

    /**
     * Busca mensagem por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_queue WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca mensagem por message_id
     */
    public function buscarPorMessageId(string $messageId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_queue WHERE message_id = ?",
            [$messageId]
        );
    }

    /**
     * Busca mensagens pendentes
     */
    public function buscarPendentes(int $limit = 10): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_queue
             WHERE status_code = 1
             AND (agendado_para IS NULL OR agendado_para <= NOW())
             ORDER BY prioridade DESC, criado_em ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca mensagens por status
     */
    public function buscarPorStatus(int $statusCode, int $limit = 50, int $offset = 0): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_queue
             WHERE status_code = ?
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            [$statusCode, $limit, $offset]
        );
    }

    /**
     * Conta mensagens pendentes
     */
    public function contarPendentes(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM whatsapp_queue
             WHERE status_code = 1
             AND (agendado_para IS NULL OR agendado_para <= NOW())"
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta mensagens por status
     */
    public function contarPorStatus(int $statusCode): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM whatsapp_queue WHERE status_code = ?",
            [$statusCode]
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Atualiza status da mensagem
     */
    public function atualizarStatus(int $id, int $statusCode, ?string $erro = null, ?string $messageId = null): void
    {
        $dados = ['status_code' => $statusCode];

        if ($statusCode === 2) { // Enviado
            $dados['enviado_em'] = date('Y-m-d H:i:s');
        }

        if ($erro !== null) {
            $dados['erro'] = $erro;
        }

        if ($messageId !== null) {
            $dados['message_id'] = $messageId;
        }

        $this->db->atualizar('whatsapp_queue', $dados, 'id = ?', [$id]);
    }

    /**
     * Atualiza mensagem
     */
    public function atualizar(int $id, array $dados): void
    {
        $this->db->atualizar('whatsapp_queue', $dados, 'id = ?', [$id]);
    }

    /**
     * Remove mensagem da fila
     */
    public function deletar(int $id): void
    {
        $this->db->deletar('whatsapp_queue', 'id = ?', [$id]);
    }

    /**
     * Remove mensagens antigas
     */
    public function limparAntigas(int $dias): int
    {
        return $this->db->executar(
            "DELETE FROM whatsapp_queue
             WHERE status_code IN (2, 3, 4)
             AND criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Busca mensagens prontas para retry
     */
    public function buscarProntasParaRetry(int $maxTentativas, int $limit = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_queue
             WHERE status_code = 0
             AND tentativas < ?
             AND (proxima_tentativa IS NULL OR proxima_tentativa <= NOW())
             ORDER BY prioridade DESC, criado_em ASC
             LIMIT ?",
            [$maxTentativas, $limit]
        );
    }
}
