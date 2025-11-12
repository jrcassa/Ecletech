<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para gerenciar webhooks recebidos
 */
class ModelWhatsappWebhook
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona webhook
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');
        return $this->db->inserir('whatsapp_webhooks', $dados);
    }

    /**
     * Busca webhook por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_webhooks WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca webhooks não processados
     */
    public function buscarNaoProcessados(int $limit = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_webhooks
             WHERE processado = FALSE
             ORDER BY criado_em ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca webhooks por message_id
     */
    public function buscarPorMessageId(string $messageId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_webhooks
             WHERE message_id = ?
             ORDER BY criado_em DESC",
            [$messageId]
        );
    }

    /**
     * Marca webhook como processado
     */
    public function marcarProcessado(int $id, bool $sucesso, ?string $erro = null): void
    {
        $this->db->atualizar(
            'whatsapp_webhooks',
            [
                'processado' => $sucesso,
                'erro' => $erro,
                'processado_em' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );
    }

    /**
     * Atualiza webhook
     */
    public function atualizar(int $id, array $dados): void
    {
        $this->db->atualizar('whatsapp_webhooks', $dados, 'id = ?', [$id]);
    }

    /**
     * Remove webhooks antigos
     */
    public function limparAntigos(int $dias): int
    {
        return $this->db->executar(
            "DELETE FROM whatsapp_webhooks
             WHERE processado = TRUE
             AND erro IS NULL
             AND criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Conta webhooks não processados
     */
    public function contarNaoProcessados(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM whatsapp_webhooks WHERE processado = FALSE"
        );
        return $resultado['total'] ?? 0;
    }
}
