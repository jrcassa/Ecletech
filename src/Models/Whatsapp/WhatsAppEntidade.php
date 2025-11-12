<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppEntidade
{
    private $conn;
    private $table = 'whatsapp_entidades';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Busca entidade por tipo e ID
     */
    public function buscarPorEntidade($tipoEntidade, $entidadeId)
    {
        $query = "SELECT * FROM {$this->table}
                  WHERE tipo_entidade = :tipo
                  AND entidade_id = :id
                  AND ativo = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':tipo' => $tipoEntidade,
            ':id' => $entidadeId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Criar ou atualizar entidade
     */
    public function sincronizar($dados)
    {
        // Verifica se já existe
        $existente = $this->buscarPorEntidade($dados['tipo_entidade'], $dados['entidade_id']);

        if ($existente) {
            // UPDATE
            $query = "UPDATE {$this->table} SET
                numero_whatsapp = :numero,
                numero_formatado = :numero_formatado,
                nome = :nome,
                email = :email,
                ativo = 1
                WHERE tipo_entidade = :tipo AND entidade_id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':numero' => $dados['numero_whatsapp'],
                ':numero_formatado' => $dados['numero_formatado'],
                ':nome' => $dados['nome'],
                ':email' => $dados['email'],
                ':tipo' => $dados['tipo_entidade'],
                ':id' => $dados['entidade_id']
            ]);

            return $existente['id'];
        } else {
            // INSERT
            $query = "INSERT INTO {$this->table} SET
                tipo_entidade = :tipo,
                entidade_id = :entidade_id,
                numero_whatsapp = :numero,
                numero_formatado = :numero_formatado,
                nome = :nome,
                email = :email";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':tipo' => $dados['tipo_entidade'],
                ':entidade_id' => $dados['entidade_id'],
                ':numero' => $dados['numero_whatsapp'],
                ':numero_formatado' => $dados['numero_formatado'],
                ':nome' => $dados['nome'],
                ':email' => $dados['email']
            ]);

            return $this->conn->lastInsertId();
        }
    }

    /**
     * Atualizar estatísticas de envio
     */
    public function registrarEnvio($tipoEntidade, $entidadeId, $sucesso = true)
    {
        $query = "UPDATE {$this->table} SET
            ultimo_envio = NOW(),
            total_envios = total_envios + 1
            WHERE tipo_entidade = :tipo AND entidade_id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':tipo' => $tipoEntidade,
            ':id' => $entidadeId
        ]);
    }

    /**
     * Bloquear entidade
     */
    public function bloquear($tipoEntidade, $entidadeId, $motivo)
    {
        $query = "UPDATE {$this->table} SET
            bloqueado = 1,
            motivo_bloqueio = :motivo
            WHERE tipo_entidade = :tipo AND entidade_id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':motivo' => $motivo,
            ':tipo' => $tipoEntidade,
            ':id' => $entidadeId
        ]);
    }

    /**
     * Desbloquear entidade
     */
    public function desbloquear($tipoEntidade, $entidadeId)
    {
        $query = "UPDATE {$this->table} SET
            bloqueado = 0,
            motivo_bloqueio = NULL
            WHERE tipo_entidade = :tipo AND entidade_id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':tipo' => $tipoEntidade,
            ':id' => $entidadeId
        ]);
    }

    /**
     * Listar entidades por tipo
     */
    public function listarPorTipo($tipoEntidade, $apenasAtivos = true)
    {
        $query = "SELECT * FROM {$this->table} WHERE tipo_entidade = :tipo";

        if ($apenasAtivos) {
            $query .= " AND ativo = 1 AND bloqueado = 0";
        }

        $query .= " ORDER BY nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':tipo' => $tipoEntidade]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas por entidade
     */
    public function obterEstatisticas($tipoEntidade, $entidadeId)
    {
        // Total de mensagens enviadas
        $query = "SELECT
            COUNT(*) as total_mensagens,
            SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
            MAX(processado_em) as ultimo_envio
            FROM whatsapp_queue
            WHERE tipo_entidade = :tipo AND entidade_id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':tipo' => $tipoEntidade,
            ':id' => $entidadeId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Contar entidades por tipo
     */
    public function contarPorTipo($tipoEntidade)
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table}
                  WHERE tipo_entidade = :tipo AND ativo = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':tipo' => $tipoEntidade]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
}
