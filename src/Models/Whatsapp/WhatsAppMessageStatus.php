<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppMessageStatus
{
    private $conn;
    private $table = 'whatsapp_message_status';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Cria ou atualiza status da mensagem
     */
    public function atualizarStatus($messageId, $dados)
    {
        // Verifica se já existe
        $query = "SELECT id, status_code FROM {$this->table} WHERE message_id = :message_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':message_id', $messageId);
        $stmt->execute();
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            // UPDATE - só atualiza se o novo status for maior (evolução)
            if (isset($dados['status_code']) && $dados['status_code'] <= $existe['status_code']) {
                // Não regride status
                return $existe['id'];
            }

            $query = "UPDATE {$this->table} SET";
            $fields = [];

            if (isset($dados['status_code'])) $fields[] = "status_code = :status_code";
            if (isset($dados['status_nome'])) $fields[] = "status_nome = :status_nome";
            if (isset($dados['remote_jid'])) $fields[] = "remote_jid = :remote_jid";
            if (isset($dados['queue_id'])) $fields[] = "queue_id = :queue_id";
            if (isset($dados['webhook_id'])) $fields[] = "webhook_id = :webhook_id";

            // Atualiza timestamps conforme status
            if (isset($dados['status_code'])) {
                switch ($dados['status_code']) {
                    case 2: // ENVIADO
                        $fields[] = "data_envio = NOW()";
                        break;
                    case 3: // ENTREGUE
                        $fields[] = "data_entrega = NOW()";
                        break;
                    case 4: // LIDO
                        $fields[] = "data_leitura = NOW()";
                        break;
                }
            }

            $query .= " " . implode(", ", $fields) . " WHERE message_id = :message_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $messageId);

        } else {
            // INSERT
            $query = "INSERT INTO {$this->table} SET
                message_id = :message_id,
                status_code = :status_code,
                status_nome = :status_nome,
                remote_jid = :remote_jid,
                queue_id = :queue_id,
                webhook_id = :webhook_id";

            // Adiciona timestamp conforme status
            if (isset($dados['status_code'])) {
                switch ($dados['status_code']) {
                    case 2: $query .= ", data_envio = NOW()"; break;
                    case 3: $query .= ", data_entrega = NOW()"; break;
                    case 4: $query .= ", data_leitura = NOW()"; break;
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $messageId);
        }

        // Bind params
        if (isset($dados['status_code'])) $stmt->bindParam(':status_code', $dados['status_code']);
        if (isset($dados['status_nome'])) $stmt->bindParam(':status_nome', $dados['status_nome']);
        if (isset($dados['remote_jid'])) $stmt->bindParam(':remote_jid', $dados['remote_jid']);
        if (isset($dados['queue_id'])) $stmt->bindParam(':queue_id', $dados['queue_id']);
        if (isset($dados['webhook_id'])) $stmt->bindParam(':webhook_id', $dados['webhook_id']);

        if ($stmt->execute()) {
            return $existe ? $existe['id'] : $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Busca status por message_id
     */
    public function buscarPorMessageId($messageId)
    {
        $query = "SELECT * FROM {$this->table} WHERE message_id = :message_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':message_id', $messageId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca status por queue_id
     */
    public function buscarPorQueueId($queueId)
    {
        $query = "SELECT * FROM {$this->table} WHERE queue_id = :queue_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':queue_id', $queueId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
