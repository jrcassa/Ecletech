<?php

namespace App\Controllers\Whatsapp;

use App\Controllers\BaseController;

use App\Models\Whatsapp\ModelWhatsappBaileys;
use App\Models\Whatsapp\ModelWhatsappConfiguracao;
use App\Helpers\AuxiliarResposta;

/**
 * Controller para gerenciar conexão com WhatsApp
 */
class ControllerWhatsappConexao extends BaseController
{
    private ModelWhatsappBaileys $baileys;
    private ModelWhatsappConfiguracao $config;

    public function __construct()
    {
        $this->baileys = new ModelWhatsappBaileys();
        $this->config = new ModelWhatsappConfiguracao();
    }

    /**
     * Verifica status da instância
     */
    public function status(): void
    {
        try {
            $response = $this->baileys->infoInstancia();
            $info = json_decode($response, true);

            // Debug: verifica se json_decode falhou
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Tenta extrair a URL da configuração para debug
                $apiConfig = $this->config->obter('api_base_url', 'não configurada');
                $instanceToken = $this->config->obter('instancia_token', 'não configurado');
                $urlDebug = $apiConfig . '/instance/info?key=' . substr($instanceToken, 0, 10) . '...';

                throw new \Exception('Erro ao decodificar resposta JSON: ' . json_last_error_msg() . '. URL: ' . $urlDebug . '. Resposta: ' . substr($response, 0, 200));
            }

            // Debug: verifica se resposta é null
            if ($info === null) {
                throw new \Exception('Resposta da API é null. Resposta bruta: ' . substr($response, 0, 200));
            }

            $resultado = [
                'conectado' => false,
                'status' => 'desconectado',
                'qr_code' => null,
                'telefone' => null,
                'nome' => null
            ];

            // Verifica se conectado
            if (isset($info['error']) && $info['error'] == false) {
                if (isset($info['instance_data']['phone_connected']) && $info['instance_data']['phone_connected'] == true) {
                    $resultado['conectado'] = true;
                    $resultado['status'] = 'conectado';

                    // Extrai telefone
                    if (isset($info['instance_data']['user']['id'])) {
                        preg_match('/^(\d+):/', $info['instance_data']['user']['id'], $matches);
                        $resultado['telefone'] = $matches[1] ?? null;
                    }

                    $resultado['nome'] = $info['instance_data']['user']['name'] ?? null;

                    // Salva configurações
                    $this->config->salvar('instancia_status', 'conectado');
                    $this->config->salvar('instancia_telefone', $resultado['telefone']);
                    $this->config->salvar('instancia_nome', $resultado['nome']);
                } else {
                    // Aguardando QR code
                    $resultado['status'] = 'qrcode';

                    // Tenta obter QR code em base64
                    $qrResponse = $this->baileys->status_instancia();
                    $qrData = json_decode($qrResponse, true);

                    // Verifica se conseguiu obter o QR code
                    if (isset($qrData['qrcode']) && !empty($qrData['qrcode'])) {
                        $resultado['qr_code'] = $qrData['qrcode'];
                    } else if (isset($qrData['base64'])) {
                        // Formato alternativo: base64
                        $resultado['qr_code'] = $qrData['base64'];
                    } else {
                        // Se não conseguiu via qrbase64, tenta endpoint antigo
                        $qrDataAntigo = json_decode($this->baileys->statusInstancia(), true);
                        $resultado['qr_code'] = $qrDataAntigo['qrcode'] ?? null;
                    }

                    $this->config->salvar('instancia_status', 'qrcode');
                }
            }

            $this->sucesso($resultado, 'Status obtido');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Cria nova instância
     */
    public function criar(): void
    {
        try {
            $response = json_decode($this->baileys->criarInstancia(), true);

            if (isset($response['error']) && $response['error'] === false) {
                $this->config->salvar('instancia_status', 'qrcode');

                $this->sucesso(
                    ['response' => $response],
                    'Instância criada com sucesso'
                );
            } else {
                $this->erro($response['message'] ?? 'Erro ao criar instância', 400);
            }

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Desconecta instância
     */
    public function desconectar(): void
    {
        try {
            $this->baileys->logoutInstancia();

            $this->config->salvar('instancia_status', 'desconectado');
            $this->config->salvar('instancia_telefone', '');
            $this->config->salvar('instancia_nome', '');

            $this->sucesso(null, 'Instância desconectada com sucesso');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém QR code
     */
    public function qrcode(): void
    {
        try {
            // Tenta obter QR code em base64
            $response = $this->baileys->status_instancia();
            $data = json_decode($response, true);

            $qrCode = null;

            // Verifica diferentes formatos de resposta
            if (isset($data['qrcode']) && !empty($data['qrcode'])) {
                $qrCode = $data['qrcode'];
            } else if (isset($data['base64'])) {
                $qrCode = $data['base64'];
            } else {
                // Tenta endpoint antigo como fallback
                $dataAntigo = json_decode($this->baileys->statusInstancia(), true);
                $qrCode = $dataAntigo['qrcode'] ?? null;
            }

            if ($qrCode) {
                $this->sucesso(['qr_code' => $qrCode], 'QR Code obtido');
            } else {
                $this->erro('QR Code não disponível', 400);
            }

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }
}
