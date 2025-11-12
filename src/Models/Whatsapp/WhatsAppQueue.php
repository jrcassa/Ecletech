<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppQueue
{
    private $conn;
    private $table = 'whatsapp_queue';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Adiciona mensagem na fila
     */
    public function adicionar($dados)
    {
        $query = "INSERT INTO {$this->table} SET
            tipo_entidade = :tipo_entidade,
            entidade_id = :entidade_id,
            entidade_nome = :entidade_nome,
            tipo_mensagem = :tipo_mensagem,
            destinatario = :destinatario,
            is_grupo = :is_grupo,
            mensagem = :mensagem,
            arquivo_url = :arquivo_url,
            arquivo_base64 = :arquivo_base64,
            arquivo_nome = :arquivo_nome,
            dados_extras = :dados_extras,
            prioridade = :prioridade,
            agendado_para = :agendado_para,
            max_tentativas = :max_tentativas";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':tipo_entidade', $dados['tipo_entidade']);
        $stmt->bindParam(':entidade_id', $dados['entidade_id']);
        $stmt->bindParam(':entidade_nome', $dados['entidade_nome']);
        $stmt->bindParam(':tipo_mensagem', $dados['tipo_mensagem']);
        $stmt->bindParam(':destinatario', $dados['destinatario']);
        $stmt->bindParam(':is_grupo', $dados['is_grupo']);
        $stmt->bindParam(':mensagem', $dados['mensagem']);
        $stmt->bindParam(':arquivo_url', $dados['arquivo_url']);
        $stmt->bindParam(':arquivo_base64', $dados['arquivo_base64']);
        $stmt->bindParam(':arquivo_nome', $dados['arquivo_nome']);
        $stmt->bindParam(':dados_extras', $dados['dados_extras']);
        $stmt->bindParam(':prioridade', $dados['prioridade']);
        $stmt->bindParam(':agendado_para', $dados['agendado_para']);
        $stmt->bindParam(':max_tentativas', $dados['max_tentativas']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Busca mensagens pendentes para processar
     */
    public function buscarPendentes($limite = 50)
    {
        $query = "SELECT * FROM {$this->table}
            WHERE status = 'pendente'
            AND (agendado_para IS NULL OR agendado_para <= NOW())
            ORDER BY
                FIELD(prioridade, 'urgente', 'alta', 'normal', 'baixa'),
                criado_em ASC
            LIMIT :limite";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza status da mensagem
     */
    public function atualizarStatus($id, $status, $dados = [])
    {
        $query = "UPDATE {$this->table} SET
            status = :status,
            tentativas = tentativas + 1,
            atualizado_em = NOW()";

        if (isset($dados['erro_mensagem'])) {
            $query .= ", erro_mensagem = :erro_mensagem";
        }

        if (isset($dados['message_id'])) {
            $query .= ", message_id = :message_id";
        }

        if (isset($dados['api_remote_jid'])) {
            $query .= ", api_remote_jid = :api_remote_jid";
        }

        if (isset($dados['api_response'])) {
            $query .= ", api_response = :api_response";
        }

        if (isset($dados['api_status'])) {
            $query .= ", api_status = :api_status";
        }

        if ($status == 'enviado' || $status == 'erro') {
            $query .= ", processado_em = NOW()";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);

        if (isset($dados['erro_mensagem'])) {
            $stmt->bindParam(':erro_mensagem', $dados['erro_mensagem']);
        }
        if (isset($dados['message_id'])) {
            $stmt->bindParam(':message_id', $dados['message_id']);
        }
        if (isset($dados['api_remote_jid'])) {
            $stmt->bindParam(':api_remote_jid', $dados['api_remote_jid']);
        }
        if (isset($dados['api_response'])) {
            $stmt->bindParam(':api_response', $dados['api_response']);
        }
        if (isset($dados['api_status'])) {
            $stmt->bindParam(':api_status', $dados['api_status']);
        }

        return $stmt->execute();
    }

    /**
     * Busca por message_id (tracking)
     */
    public function buscarPorMessageId($message_id)
    {
        $query = "SELECT * FROM {$this->table} WHERE message_id = :message_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obter estatísticas da fila
     */
    public function obterEstatisticas()
    {
        $query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'processando' THEN 1 ELSE 0 END) as processando,
            SUM(CASE WHEN status = 'enviado' AND DATE(criado_em) = CURDATE() THEN 1 ELSE 0 END) as enviados_hoje,
            SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros
            FROM {$this->table}";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar mensagens com filtros
     */
    public function listar($filtros = [], $limite = 50, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filtros['status'])) {
            $query .= " AND status = :status";
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['tipo_entidade'])) {
            $query .= " AND tipo_entidade = :tipo_entidade";
            $params[':tipo_entidade'] = $filtros['tipo_entidade'];
        }

        if (!empty($filtros['entidade_id'])) {
            $query .= " AND entidade_id = :entidade_id";
            $params[':entidade_id'] = $filtros['entidade_id'];
        }

        if (!empty($filtros['data_inicio'])) {
            $query .= " AND criado_em >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $query .= " AND criado_em <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'];
        }

        $query .= " ORDER BY criado_em DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cancelar mensagem pendente
     */
    public function cancelar($id)
    {
        $query = "UPDATE {$this->table} SET status = 'cancelado' WHERE id = :id AND status = 'pendente'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
