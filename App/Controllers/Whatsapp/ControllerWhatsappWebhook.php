<?php

namespace App\Controllers\Whatsapp;

use App\Services\Whatsapp\ServiceWhatsapp;
use App\Helpers\AuxiliarResposta;

/**
 * Controller para receber webhooks da API WhatsApp
 */
class ControllerWhatsappWebhook
{
    private ServiceWhatsapp $service;

    public function __construct()
    {
        $this->service = new ServiceWhatsapp();
    }

    /**
     * Recebe webhook POST da API
     */
    public function receber(): void
    {
        try {
            // Lê payload bruto
            $payload_raw = file_get_contents('php://input');
            $payload = json_decode($payload_raw, true);

            // Valida JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
                exit;
            }

            // Processa webhook
            $resultado = $this->service->processarWebhook($payload);

            // Sempre retorna 200 para evitar retentativas
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'processado' => $resultado['processado'] ?? false
            ]);
            exit;

        } catch (\Exception $e) {
            http_response_code(200); // 200 mesmo com erro
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * GET para validação de webhook
     */
    public function validar(): void
    {
        $challenge = $_GET['challenge'] ?? null;

        if ($challenge) {
            echo $challenge;
            exit;
        }

        echo json_encode(['status' => 'ok', 'message' => 'Webhook ativo']);
        exit;
    }
}
