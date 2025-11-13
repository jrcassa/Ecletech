<?php

namespace App\Models\Email;

use App\Core\BancoDados;

/**
 * Model para gerenciar fila de emails
 * Padrão: Segue estrutura do ModelWhatsappQueue
 */
class ModelEmailQueue
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona email à fila
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');

        // Gera tracking_code único se não fornecido
        if (!isset($dados['tracking_code'])) {
            $dados['tracking_code'] = md5(uniqid(rand(), true));
        }

        return $this->db->inserir('email_queue', $dados);
    }

    /**
     * Busca email por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_queue WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca email por message_id
     */
    public function buscarPorMessageId(string $messageId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_queue WHERE message_id = ?",
            [$messageId]
        );
    }

    /**
     * Busca email por tracking_code
     */
    public function buscarPorTrackingCode(string $trackingCode): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_queue WHERE tracking_code = ?",
            [$trackingCode]
        );
    }

    /**
     * Busca emails pendentes para envio
     */
    public function buscarPendentes(int $limit = 20): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_queue
             WHERE status = 1
             AND (agendado_para IS NULL OR agendado_para <= NOW())
             ORDER BY
                CASE prioridade
                    WHEN 'urgente' THEN 1
                    WHEN 'alta' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'baixa' THEN 4
                END,
                criado_em ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca emails por status
     */
    public function buscarPorStatus(int $status, int $limit = 50, int $offset = 0): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_queue
             WHERE status = ?
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            [$status, $limit, $offset]
        );
    }

    /**
     * Conta emails pendentes
     */
    public function contarPendentes(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM email_queue
             WHERE status = 1
             AND (agendado_para IS NULL OR agendado_para <= NOW())"
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta emails por status
     */
    public function contarPorStatus(int $status): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM email_queue WHERE status = ?",
            [$status]
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta emails processando
     */
    public function contarProcessando(): int
    {
        return $this->contarPorStatus(2);
    }

    /**
     * Conta emails enviados hoje
     */
    public function contarEnviadosHoje(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM email_queue
             WHERE status = 3
             AND DATE(enviado_em) = CURDATE()"
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta emails com erro
     */
    public function contarComErro(): int
    {
        return $this->contarPorStatus(0);
    }

    /**
     * Atualiza status do email
     */
    public function atualizarStatus(int $id, int $status, ?string $erro = null, ?string $messageId = null): void
    {
        $dados = [
            'status' => $status,
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        if ($status === 3) { // Enviado
            $dados['enviado_em'] = date('Y-m-d H:i:s');
        }

        if ($erro !== null) {
            $dados['ultimo_erro'] = $erro;
        }

        if ($messageId !== null) {
            $dados['message_id'] = $messageId;
        }

        $this->db->atualizar('email_queue', $dados, 'id = ?', [$id]);
    }

    /**
     * Incrementa tentativas
     */
    public function incrementarTentativas(int $id): void
    {
        $this->db->executar(
            "UPDATE email_queue SET tentativas = tentativas + 1, atualizado_em = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Atualiza email
     */
    public function atualizar(int $id, array $dados): void
    {
        $dados['atualizado_em'] = date('Y-m-d H:i:s');
        $this->db->atualizar('email_queue', $dados, 'id = ?', [$id]);
    }

    /**
     * Remove email da fila
     */
    public function deletar(int $id): void
    {
        $this->db->deletar('email_queue', 'id = ?', [$id]);
    }

    /**
     * Remove emails antigos já processados
     */
    public function limparAntigas(int $dias): int
    {
        return $this->db->executar(
            "DELETE FROM email_queue
             WHERE status IN (0, 3)
             AND criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Busca emails prontos para retry
     */
    public function buscarProntasParaRetry(int $limit = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_queue
             WHERE status = 0
             AND tentativas < max_tentativas
             ORDER BY
                CASE prioridade
                    WHEN 'urgente' THEN 1
                    WHEN 'alta' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'baixa' THEN 4
                END,
                criado_em ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca emails agendados para o futuro
     */
    public function buscarAgendados(): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_queue
             WHERE status = 1
             AND agendado_para > NOW()
             ORDER BY agendado_para ASC"
        );
    }

    /**
     * Lista todos os emails da fila com paginação
     */
    public function listar(int $limit = 50, int $offset = 0, ?int $status = null): array
    {
        $where = $status !== null ? "WHERE status = ?" : "";
        $params = $status !== null ? [$status, $limit, $offset] : [$limit, $offset];

        return $this->db->buscarTodos(
            "SELECT * FROM email_queue
             $where
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }
}
