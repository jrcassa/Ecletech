<?php

namespace Services\Whatsapp;

use Models\Whatsapp\WhatsAppWebhook;
use Models\Whatsapp\WhatsAppQueue;
use Models\Whatsapp\WhatsAppHistorico;
use Models\Whatsapp\WhatsAppMessageStatus;
use Models\Whatsapp\WhatsAppConfiguracao;
use Helpers\WhatsAppStatus;

class WhatsAppWebhookService
{
    private $conn;
    private $webhookModel;
    private $queueModel;
    private $historicoModel;
    private $messageStatusModel;
    private $config;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->webhookModel = new WhatsAppWebhook($db);
        $this->queueModel = new WhatsAppQueue($db);
        $this->historicoModel = new WhatsAppHistorico($db);
        $this->messageStatusModel = new WhatsAppMessageStatus($db);
        $this->config = new WhatsAppConfiguracao($db);
    }

    /**
     * Processa webhook recebido
     *
     * @param array $payload Dados do webhook
     * @return array
     */
    public function processar($payload)
    {
        try {
            // Valida payload
            if (empty($payload)) {
                throw new \Exception('Payload vazio');
            }

            // Armazena webhook bruto
            $webhookId = $this->webhookModel->adicionar([
                'payload' => json_encode($payload),
                'processado' => false
            ]);

            // Extrai informações do webhook
            $info = $this->extrairInformacoes($payload);

            if (!$info) {
                // Webhook não relevante (ex: mensagem recebida, não status)
                $this->webhookModel->marcarProcessado($webhookId, true, 'Webhook não relevante para processamento');
                return [
                    'sucesso' => true,
                    'processado' => false,
                    'motivo' => 'Webhook não relevante'
                ];
            }

            // Processa atualização de status
            $resultado = $this->processarAtualizacaoStatus($info);

            // Marca webhook como processado
            $this->webhookModel->marcarProcessado($webhookId, true);

            // Atualiza webhook com message_id e status
            $this->webhookModel->atualizar($webhookId, [
                'message_id' => $info['message_id'],
                'status' => $info['status']
            ]);

            return [
                'sucesso' => true,
                'processado' => true,
                'webhook_id' => $webhookId,
                'message_id' => $info['message_id'],
                'status' => $info['status'],
                'resultado' => $resultado
            ];
        } catch (\Exception $e) {
            // Marca webhook com erro
            if (isset($webhookId)) {
                $this->webhookModel->marcarProcessado($webhookId, false, $e->getMessage());
            }

            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrai informações relevantes do webhook
     *
     * @param array $payload
     * @return array|null
     */
    private function extrairInformacoes($payload)
    {
        // Verifica se é atualização de status de mensagem
        if (!isset($payload['event']) || !isset($payload['data'])) {
            return null;
        }

        $event = $payload['event'];
        $data = $payload['data'];

        // Mapeia eventos para status
        $eventosRelevantes = [
            'messages.update' => true,
            'message.status' => true,
            'message.ack' => true
        ];

        if (!isset($eventosRelevantes[$event])) {
            return null; // Evento não relevante
        }

        // Extrai message_id
        $messageId = null;
        if (isset($data['key']['id'])) {
            $messageId = $data['key']['id'];
        } elseif (isset($data['id'])) {
            $messageId = $data['id'];
        } elseif (isset($data['message']['key']['id'])) {
            $messageId = $data['message']['key']['id'];
        }

        if (!$messageId) {
            return null; // Sem message_id
        }

        // Extrai status
        $status = null;
        if (isset($data['update']['status'])) {
            $status = $data['update']['status'];
        } elseif (isset($data['status'])) {
            $status = $data['status'];
        } elseif (isset($data['ack'])) {
            // Converte ack numérico para status
            $ackMap = [
                0 => 'pending',
                1 => 'sent',
                2 => 'delivered',
                3 => 'read'
            ];
            $status = $ackMap[$data['ack']] ?? null;
        }

        if (!$status) {
            return null; // Sem status
        }

        return [
            'message_id' => $messageId,
            'status' => $status,
            'timestamp' => $data['timestamp'] ?? time(),
            'from' => $data['key']['remoteJid'] ?? $data['from'] ?? null,
            'payload_completo' => $payload
        ];
    }

    /**
     * Processa atualização de status de mensagem
     *
     * @param array $info
     * @return array
     */
    private function processarAtualizacaoStatus($info)
    {
        $messageId = $info['message_id'];
        $status = $info['status'];

        // Converte status do webhook para código numérico
        $statusCode = WhatsAppStatus::webhookParaStatusCode($status);

        // Busca mensagem na fila pelo message_id
        $mensagem = $this->queueModel->buscarPorMessageId($messageId);

        $resultado = [
            'message_id' => $messageId,
            'status_webhook' => $status,
            'status_code' => $statusCode,
            'queue_atualizado' => false,
            'message_status_criado' => false
        ];

        // Atualiza fila se encontrou a mensagem
        if ($mensagem) {
            // Só atualiza se o novo status for "maior" que o atual
            if ($statusCode > $mensagem['status_code']) {
                $updates = [
                    'status_code' => $statusCode
                ];

                // Define timestamps conforme status
                switch ($statusCode) {
                    case 2: // Enviado
                        $updates['enviado_em'] = date('Y-m-d H:i:s', $info['timestamp']);
                        break;
                    case 3: // Entregue
                        $updates['data_entrega'] = date('Y-m-d H:i:s', $info['timestamp']);
                        break;
                    case 4: // Lido
                        $updates['data_leitura'] = date('Y-m-d H:i:s', $info['timestamp']);
                        break;
                }

                $this->queueModel->atualizar($mensagem['id'], $updates);
                $resultado['queue_atualizado'] = true;

                // Registra no histórico
                $this->historicoModel->adicionar([
                    'queue_id' => $mensagem['id'],
                    'message_id' => $messageId,
                    'tipo_evento' => 'status_atualizado',
                    'dados' => json_encode([
                        'status_anterior' => $mensagem['status_code'],
                        'status_novo' => $statusCode,
                        'webhook_data' => $info
                    ])
                ]);
            }
        }

        // Registra em whatsapp_message_status (sempre, mesmo se não encontrou na fila)
        try {
            $this->messageStatusModel->adicionar([
                'message_id' => $messageId,
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s', $info['timestamp']),
                'from' => $info['from'],
                'payload' => json_encode($info['payload_completo'])
            ]);
            $resultado['message_status_criado'] = true;
        } catch (\Exception $e) {
            // Provavelmente duplicado (message_id + status já existe)
            $resultado['message_status_erro'] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Reprocessa webhooks com erro
     *
     * @param int $limit
     * @return array
     */
    public function reprocessarComErro($limit = 50)
    {
        $webhooks = $this->webhookModel->buscarNaoProcessados($limit);

        $resultado = [
            'total' => count($webhooks),
            'sucesso' => 0,
            'erro' => 0,
            'detalhes' => []
        ];

        foreach ($webhooks as $webhook) {
            $payload = json_decode($webhook['payload'], true);
            $processamento = $this->processar($payload);

            if ($processamento['sucesso']) {
                $resultado['sucesso']++;
            } else {
                $resultado['erro']++;
            }

            $resultado['detalhes'][] = [
                'webhook_id' => $webhook['id'],
                'processamento' => $processamento
            ];
        }

        return $resultado;
    }

    /**
     * Obtém histórico de status de uma mensagem
     *
     * @param string $messageId
     * @return array
     */
    public function obterHistoricoMensagem($messageId)
    {
        return $this->messageStatusModel->buscarPorMessageId($messageId);
    }

    /**
     * Obtém estatísticas de webhooks
     *
     * @return array
     */
    public function obterEstatisticas()
    {
        $stats = [];

        // Total de webhooks
        $stmtTotal = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_webhooks");
        $stats['total'] = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];

        // Processados
        $stmtProcessados = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_webhooks WHERE processado = 1");
        $stats['processados'] = $stmtProcessados->fetch(\PDO::FETCH_ASSOC)['total'];

        // Não processados
        $stmtNaoProcessados = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_webhooks WHERE processado = 0");
        $stats['nao_processados'] = $stmtNaoProcessados->fetch(\PDO::FETCH_ASSOC)['total'];

        // Com erro
        $stmtErro = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_webhooks WHERE erro IS NOT NULL");
        $stats['com_erro'] = $stmtErro->fetch(\PDO::FETCH_ASSOC)['total'];

        // Últimas 24h
        $stmt24h = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_webhooks
                                       WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['ultimas_24h'] = $stmt24h->fetch(\PDO::FETCH_ASSOC)['total'];

        // Por status
        $stmtStatus = $this->conn->query("SELECT status, COUNT(*) as total FROM whatsapp_webhooks
                                          WHERE status IS NOT NULL
                                          GROUP BY status");
        $stats['por_status'] = [];
        while ($row = $stmtStatus->fetch(\PDO::FETCH_ASSOC)) {
            $stats['por_status'][$row['status']] = $row['total'];
        }

        return $stats;
    }

    /**
     * Limpa webhooks antigos
     *
     * @param int $dias
     * @return int
     */
    public function limparAntigos($dias = null)
    {
        if ($dias === null) {
            $dias = $this->config->obter('webhook_retencao_dias', 7);
        }

        $query = "DELETE FROM whatsapp_webhooks
                  WHERE processado = 1
                  AND erro IS NULL
                  AND criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':dias' => $dias]);

        return $stmt->rowCount();
    }

    /**
     * Valida assinatura do webhook (se configurado)
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    public function validarAssinatura($payload, $signature)
    {
        $secret = $this->config->obter('webhook_secret');

        if (!$secret) {
            // Se não tem secret configurado, aceita qualquer webhook
            return true;
        }

        // Calcula hash esperado
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Compara de forma segura
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Simula webhook (para testes)
     *
     * @param string $messageId
     * @param string $status
     * @return array
     */
    public function simularWebhook($messageId, $status)
    {
        $payload = [
            'event' => 'messages.update',
            'data' => [
                'key' => [
                    'id' => $messageId,
                    'remoteJid' => '5515999999999@s.whatsapp.net'
                ],
                'update' => [
                    'status' => $status
                ],
                'timestamp' => time()
            ]
        ];

        return $this->processar($payload);
    }
}
