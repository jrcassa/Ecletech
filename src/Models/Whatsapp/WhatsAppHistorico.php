<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppHistorico
{
    private $conn;
    private $table = 'whatsapp_historico';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Adiciona registro no histórico
     */
    public function adicionar($dados)
    {
        $query = "INSERT INTO {$this->table} SET
            queue_id = :queue_id,
            tipo_entidade = :tipo_entidade,
            entidade_id = :entidade_id,
            entidade_nome = :entidade_nome,
            tipo_mensagem = :tipo_mensagem,
            destinatario = :destinatario,
            mensagem = :mensagem,
            message_id = :message_id,
            api_remote_jid = :api_remote_jid,
            api_response = :api_response,
            api_status = :api_status,
            status = :status,
            status_code = :status_code,
            tempo_envio = :tempo_envio";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':queue_id', $dados['queue_id']);
        $stmt->bindParam(':tipo_entidade', $dados['tipo_entidade']);
        $stmt->bindParam(':entidade_id', $dados['entidade_id']);
        $stmt->bindParam(':entidade_nome', $dados['entidade_nome']);
        $stmt->bindParam(':tipo_mensagem', $dados['tipo_mensagem']);
        $stmt->bindParam(':destinatario', $dados['destinatario']);
        $stmt->bindParam(':mensagem', $dados['mensagem']);
        $stmt->bindParam(':message_id', $dados['message_id']);
        $stmt->bindParam(':api_remote_jid', $dados['api_remote_jid']);
        $stmt->bindParam(':api_response', $dados['api_response']);
        $stmt->bindParam(':api_status', $dados['api_status']);
        $stmt->bindParam(':status', $dados['status']);
        $stmt->bindParam(':status_code', $dados['status_code']);
        $stmt->bindParam(':tempo_envio', $dados['tempo_envio']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Listar histórico com filtros
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

        if (!empty($filtros['destinatario'])) {
            $query .= " AND destinatario LIKE :destinatario";
            $params[':destinatario'] = '%' . $filtros['destinatario'] . '%';
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
     * Contar total com filtros
     */
    public function contar($filtros = [])
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filtros['status'])) {
            $query .= " AND status = :status";
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['tipo_entidade'])) {
            $query .= " AND tipo_entidade = :tipo_entidade";
            $params[':tipo_entidade'] = $filtros['tipo_entidade'];
        }

        if (!empty($filtros['data_inicio'])) {
            $query .= " AND criado_em >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $query .= " AND criado_em <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'];
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Obter estatísticas gerais
     */
    public function obterEstatisticas($periodo = 'hoje')
    {
        $condicaoPeriodo = '';

        switch ($periodo) {
            case 'hoje':
                $condicaoPeriodo = "DATE(criado_em) = CURDATE()";
                break;
            case 'semana':
                $condicaoPeriodo = "criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $condicaoPeriodo = "MONTH(criado_em) = MONTH(NOW()) AND YEAR(criado_em) = YEAR(NOW())";
                break;
            default:
                $condicaoPeriodo = "1=1";
        }

        $query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
            SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
            AVG(tempo_envio) as tempo_medio,
            SUM(CASE WHEN status_code = 4 THEN 1 ELSE 0 END) as lidos
            FROM {$this->table}
            WHERE {$condicaoPeriodo}";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
