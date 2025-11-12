<?php

namespace App\Controllers\Whatsapp;

use App\Models\Whatsapp\ModelWhatsappBaileys;
use App\Models\Whatsapp\ModelWhatsappConfiguracao;
use App\Helpers\AuxiliarResposta;

/**
 * Controller para gerenciar conexão com WhatsApp
 */
class ControllerWhatsappConexao
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
            $info = json_decode($this->baileys->infoInstancia(), true);

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
                    $qrData = json_decode($this->baileys->statusInstancia(), true);
                    $resultado['qr_code'] = $qrData['qrcode'] ?? null;

                    $this->config->salvar('instancia_status', 'qrcode');
                }
            }

            AuxiliarResposta::sucesso($resultado, 'Status obtido');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
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

                AuxiliarResposta::sucesso(
                    ['response' => $response],
                    'Instância criada com sucesso'
                );
            } else {
                AuxiliarResposta::erro($response['message'] ?? 'Erro ao criar instância', 400);
            }

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
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

            AuxiliarResposta::sucesso(null, 'Instância desconectada com sucesso');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém QR code
     */
    public function qrcode(): void
    {
        try {
            $data = json_decode($this->baileys->statusInstancia(), true);

            if (isset($data['qrcode'])) {
                AuxiliarResposta::sucesso(['qr_code' => $data['qrcode']], 'QR Code obtido');
            } else {
                AuxiliarResposta::erro('QR Code não disponível', 400);
            }

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }
}
