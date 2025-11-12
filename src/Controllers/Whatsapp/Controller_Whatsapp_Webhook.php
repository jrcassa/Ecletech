<?php
// Webhook não usa sessão - recebe POST externo da API
ob_start();

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/autoload.php";

use Config\Database;
use Services\Whatsapp\WhatsAppService;
use Models\Callback\Callback;

// BASE DE DADOS
$database = new Database();
$conn = $database->getConnection();

// Resposta padrão
$retorno_json = ['status' => 'ok'];

try {
    // ============================================
    // WEBHOOK - RECEBE POST DA API WHATSAPP
    // ============================================

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        // Instancia WhatsApp Service
        $WhatsAppService = new WhatsAppService($conn);

        // Lê payload bruto
        $payload_raw = file_get_contents('php://input');
        $payload = json_decode($payload_raw, true);

        // Valida JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $retorno_json['status'] = 'erro';
            $retorno_json['mensagem'] = 'Payload JSON inválido';

            // Log do erro
            error_log('WhatsApp Webhook - JSON inválido: ' . $payload_raw);
        } else {
            // Valida assinatura se configurado
            $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;

            if ($signature) {
                $webhookService = $WhatsAppService->webhookService ?? new \Services\Whatsapp\WhatsAppWebhookService($conn);
                $valido = $webhookService->validarAssinatura($payload_raw, $signature);

                if (!$valido) {
                    $retorno_json['status'] = 'erro';
                    $retorno_json['mensagem'] = 'Assinatura inválida';

                    // Log de segurança
                    error_log('WhatsApp Webhook - Assinatura inválida. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));

                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode($retorno_json);
                    exit;
                }
            }

            // Processa webhook
            $resultado = $WhatsAppService->processarWebhook($payload);

            if ($resultado['sucesso']) {
                $retorno_json['status'] = 'ok';
                $retorno_json['processado'] = $resultado['processado'] ?? false;

                if ($resultado['processado']) {
                    $retorno_json['webhook_id'] = $resultado['webhook_id'] ?? null;
                    $retorno_json['message_id'] = $resultado['message_id'] ?? null;
                }
            } else {
                $retorno_json['status'] = 'erro';
                $retorno_json['mensagem'] = $resultado['erro'] ?? 'Erro ao processar webhook';

                // Log do erro
                error_log('WhatsApp Webhook - Erro ao processar: ' . ($resultado['erro'] ?? 'desconhecido'));
            }
        }

        // HTTP 200 mesmo com erro para evitar retentativas desnecessárias da API
        http_response_code(200);
    }

    // ============================================
    // MÉTODO GET - VALIDAÇÃO DO WEBHOOK
    // ============================================

    elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Algumas APIs de webhook usam GET para validação inicial
        $challenge = $_GET['challenge'] ?? null;

        if ($challenge) {
            // Retorna challenge para validação
            echo $challenge;
            exit;
        } else {
            $retorno_json['status'] = 'ok';
            $retorno_json['mensagem'] = 'Webhook WhatsApp ativo';
        }
    }

    // ============================================
    // OUTROS MÉTODOS
    // ============================================

    else {
        $retorno_json['status'] = 'erro';
        $retorno_json['mensagem'] = 'Método não suportado';
        http_response_code(405);
    }

} catch (\Throwable $e) {
    // Registra erro
    try {
        $Callback = new Callback($conn);
        $Callback->adicionaCallback(0, $e, [
            'webhook' => true,
            'payload' => $payload_raw ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'desconhecido'
        ]);
    } catch (\Exception $callbackError) {
        // Se não conseguir registrar callback, apenas loga
        error_log('WhatsApp Webhook - Erro ao registrar callback: ' . $callbackError->getMessage());
    }

    $retorno_json['status'] = 'erro';
    $retorno_json['mensagem'] = 'Erro interno: ' . $e->getMessage();

    // Log do erro
    error_log('WhatsApp Webhook - Erro fatal: ' . $e->getMessage());

    http_response_code(500);
}

header('Content-Type: application/json');
echo json_encode($retorno_json);
ob_end_flush();
