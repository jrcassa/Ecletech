<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppWebhook
{
    private $conn;
    private $table = 'whatsapp_webhooks';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Registra webhook recebido
     */
    public function registrar($tipo, $payload, $ipOrigem = null, $userAgent = null)
    {
        $query = "INSERT INTO {$this->table} SET
            tipo = :tipo,
            payload = :payload,
            ip_origem = :ip_origem,
            user_agent = :user_agent";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':payload', $payload);
        $stmt->bindParam(':ipOrigem', $ipOrigem);
        $stmt->bindParam(':user_agent', $userAgent);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Marca webhook como processado
     */
    public function marcarProcessado($id, $sucesso = true, $erro = null)
    {
        $query = "UPDATE {$this->table} SET
            processado = :processado,
            processado_em = NOW(),
            erro_processamento = :erro
            WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $processado = $sucesso ? 1 : 0;
        $stmt->bindParam(':processado', $processado);
        $stmt->bindParam(':erro', $erro);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    /**
     * Buscar webhooks não processados
     */
    public function buscarNaoProcessados($limite = 100)
    {
        $query = "SELECT * FROM {$this->table}
            WHERE processado = 0
            ORDER BY criado_em ASC
            LIMIT :limite";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar webhooks com filtros
     */
    public function listar($filtros = [], $limite = 50, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filtros['tipo'])) {
            $query .= " AND tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        if (isset($filtros['processado'])) {
            $query .= " AND processado = :processado";
            $params[':processado'] = $filtros['processado'];
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
}
