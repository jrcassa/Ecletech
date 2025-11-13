<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para rastrear status de mensagens via webhook
 */
class ModelWhatsappMessageStatus
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona status de mensagem
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');
        return $this->db->inserir('whatsapp_message_status', $dados);
    }

    /**
     * Busca status por message_id
     */
    public function buscarPorMessageId(string $messageId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_message_status
             WHERE message_id = ?
             ORDER BY timestamp DESC",
            [$messageId]
        );
    }

    /**
     * Busca último status de uma mensagem
     */
    public function buscarUltimoStatus(string $messageId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_message_status
             WHERE message_id = ?
             ORDER BY timestamp DESC
             LIMIT 1",
            [$messageId]
        );
    }

    /**
     * Verifica se status já existe (evita duplicatas)
     */
    public function statusExiste(string $messageId, string $status): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM whatsapp_message_status
             WHERE message_id = ? AND status = ?",
            [$messageId, $status]
        );
        return ($resultado['total'] ?? 0) > 0;
    }
}
