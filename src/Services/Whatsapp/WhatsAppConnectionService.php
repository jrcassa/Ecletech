<?php

namespace Services\Whatsapp;

use Models\Whatsapp\WhatsAppSenderBaileys;
use Models\Whatsapp\WhatsAppConfiguracao;
use Models\Whatsapp\WhatsAppHistorico;

class WhatsAppConnectionService
{
    private $conn;
    private $sender;
    private $config;
    private $historicoModel;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->sender = new WhatsAppSenderBaileys($db);
        $this->config = new WhatsAppConfiguracao($db);
        $this->historicoModel = new WhatsAppHistorico($db);
    }

    /**
     * Verifica status completo da instância
     *
     * @return array
     */
    public function verificarStatus()
    {
        try {
            $info = json_decode($this->sender->info_instancia(), true);

            $resultado = [
                'sucesso' => true,
                'conectado' => false,
                'status' => 'desconectado',
                'qr_code' => null,
                'telefone' => null,
                'nome' => null,
                'info_completa' => $info
            ];

            // Verifica se instância existe e está conectada
            if (isset($info['error']) && $info['error'] == false) {
                if (isset($info['instance_data']['phone_connected']) && $info['instance_data']['phone_connected'] == true) {
                    // CONECTADO
                    $resultado['conectado'] = true;
                    $resultado['status'] = 'conectado';

                    // Extrai informações do usuário
                    if (isset($info['instance_data']['user']['id'])) {
                        preg_match('/^(\d+):/', $info['instance_data']['user']['id'], $matches);
                        $resultado['telefone'] = $matches[1] ?? null;
                    }

                    if (isset($info['instance_data']['user']['name'])) {
                        $resultado['nome'] = $info['instance_data']['user']['name'];
                    }

                    // Atualiza configurações
                    $this->atualizarConfiguracoesConectado($resultado['telefone'], $resultado['nome']);
                } else {
                    // AGUARDANDO QR CODE
                    $resultado['status'] = 'qrcode';

                    // Busca QR code
                    $qrData = json_decode($this->sender->status_instancia(), true);
                    if (isset($qrData['qrcode'])) {
                        $resultado['qr_code'] = $qrData['qrcode'];
                    }

                    // Atualiza configuração
                    $this->config->salvar('instancia_status', 'qrcode');
                }
            } elseif (isset($info['error']) && $info['error'] == true && $info['message'] == 'invalid key supplied') {
                // INSTÂNCIA NÃO EXISTE
                $resultado['status'] = 'nao_criada';
            } else {
                // ERRO DESCONHECIDO
                $resultado['status'] = 'erro';
                $resultado['erro'] = $info['message'] ?? 'Erro desconhecido';
            }

            return $resultado;
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'status' => 'erro'
            ];
        }
    }

    /**
     * Cria nova instância
     *
     * @return array
     */
    public function criarInstancia()
    {
        try {
            $response = json_decode($this->sender->cria_instancia(), true);

            if (isset($response['error']) && $response['error'] == false) {
                // Instância criada com sucesso
                $this->config->salvar('instancia_status', 'qrcode');
                $this->config->salvar('instancia_data_criacao', date('Y-m-d H:i:s'));

                // Registra no histórico
                $this->historicoModel->adicionar([
                    'queue_id' => null,
                    'message_id' => null,
                    'tipo_evento' => 'instancia_criada',
                    'dados' => json_encode([
                        'timestamp' => date('Y-m-d H:i:s'),
                        'response' => $response
                    ])
                ]);

                // Busca QR code
                $qrData = json_decode($this->sender->status_instancia(), true);

                return [
                    'sucesso' => true,
                    'mensagem' => 'Instância criada com sucesso',
                    'qr_code' => $qrData['qrcode'] ?? null,
                    'response' => $response
                ];
            } else {
                return [
                    'sucesso' => false,
                    'erro' => $response['message'] ?? 'Erro ao criar instância',
                    'response' => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Desconecta instância (logout)
     *
     * @return array
     */
    public function desconectar()
    {
        try {
            $this->sender->logout_instancia();

            // Atualiza configurações
            $this->config->salvar('instancia_status', 'desconectado');
            $this->config->salvar('instancia_telefone', '');
            $this->config->salvar('instancia_nome', '');
            $this->config->salvar('instancia_data_desconexao', date('Y-m-d H:i:s'));

            // Registra no histórico
            $this->historicoModel->adicionar([
                'queue_id' => null,
                'message_id' => null,
                'tipo_evento' => 'instancia_desconectada',
                'dados' => json_encode([
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Instância desconectada com sucesso'
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém QR Code atual
     *
     * @return array
     */
    public function obterQrCode()
    {
        try {
            $data = json_decode($this->sender->status_instancia(), true);

            if (isset($data['qrcode'])) {
                return [
                    'sucesso' => true,
                    'qr_code' => $data['qrcode'],
                    'base64' => $data['qrcode']['base64'] ?? null
                ];
            } else {
                return [
                    'sucesso' => false,
                    'erro' => 'QR Code não disponível. A instância pode já estar conectada.'
                ];
            }
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica se instância está conectada
     *
     * @return bool
     */
    public function estaConectado()
    {
        $status = $this->verificarStatus();
        return $status['conectado'] ?? false;
    }

    /**
     * Monitora saúde da conexão
     *
     * @return array
     */
    public function monitorarSaude()
    {
        $status = $this->verificarStatus();

        $saude = [
            'conectado' => $status['conectado'] ?? false,
            'status' => $status['status'] ?? 'desconhecido',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Verifica última mensagem enviada
        $stmtUltimo = $this->conn->query("SELECT MAX(enviado_em) as ultimo_envio FROM whatsapp_queue
                                          WHERE status_code >= 2");
        $ultimoEnvio = $stmtUltimo->fetch(\PDO::FETCH_ASSOC)['ultimo_envio'];

        if ($ultimoEnvio) {
            $saude['ultimo_envio'] = $ultimoEnvio;
            $saude['tempo_desde_ultimo_envio'] = $this->calcularTempoDesde($ultimoEnvio);
        }

        // Verifica taxa de sucesso das últimas 100 mensagens
        $stmtTaxa = $this->conn->query("SELECT
                                        SUM(CASE WHEN status_code >= 2 THEN 1 ELSE 0 END) as sucesso,
                                        COUNT(*) as total
                                        FROM (
                                            SELECT status_code FROM whatsapp_queue
                                            WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                            ORDER BY id DESC
                                            LIMIT 100
                                        ) as recentes");
        $taxa = $stmtTaxa->fetch(\PDO::FETCH_ASSOC);

        if ($taxa && $taxa['total'] > 0) {
            $saude['taxa_sucesso_24h'] = round(($taxa['sucesso'] / $taxa['total']) * 100, 2);
            $saude['total_enviadas_24h'] = $taxa['total'];
        }

        // Verifica se há erros recentes
        $stmtErros = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue
                                         WHERE status_code = 0
                                         AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $errosRecentes = $stmtErros->fetch(\PDO::FETCH_ASSOC)['total'];

        $saude['erros_ultima_hora'] = $errosRecentes;

        // Determina saúde geral
        if (!$saude['conectado']) {
            $saude['saude_geral'] = 'critica';
            $saude['mensagem'] = 'Instância desconectada';
        } elseif ($errosRecentes > 10) {
            $saude['saude_geral'] = 'degradada';
            $saude['mensagem'] = 'Muitos erros recentes';
        } elseif (isset($saude['taxa_sucesso_24h']) && $saude['taxa_sucesso_24h'] < 90) {
            $saude['saude_geral'] = 'degradada';
            $saude['mensagem'] = 'Taxa de sucesso abaixo do esperado';
        } else {
            $saude['saude_geral'] = 'saudavel';
            $saude['mensagem'] = 'Tudo funcionando normalmente';
        }

        return $saude;
    }

    /**
     * Atualiza configurações quando conectado
     *
     * @param string|null $telefone
     * @param string|null $nome
     */
    private function atualizarConfiguracoesConectado($telefone, $nome)
    {
        $this->config->salvar('instancia_status', 'conectado');

        if ($telefone) {
            $this->config->salvar('instancia_telefone', $telefone);
        }

        if ($nome) {
            $this->config->salvar('instancia_nome', $nome);
        }

        // Atualiza data de conexão apenas se mudou de desconectado para conectado
        $statusAnterior = $this->config->obter('instancia_status');
        if ($statusAnterior != 'conectado') {
            $this->config->salvar('instancia_data_conexao', date('Y-m-d H:i:s'));

            // Registra no histórico
            $this->historicoModel->adicionar([
                'queue_id' => null,
                'message_id' => null,
                'tipo_evento' => 'instancia_conectada',
                'dados' => json_encode([
                    'telefone' => $telefone,
                    'nome' => $nome,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
        }
    }

    /**
     * Calcula tempo desde uma data
     *
     * @param string $data
     * @return string
     */
    private function calcularTempoDesde($data)
    {
        $timestamp = strtotime($data);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . ' segundos';
        } elseif ($diff < 3600) {
            return round($diff / 60) . ' minutos';
        } elseif ($diff < 86400) {
            return round($diff / 3600) . ' horas';
        } else {
            return round($diff / 86400) . ' dias';
        }
    }

    /**
     * Reiniciar instância (logout + criar)
     *
     * @return array
     */
    public function reiniciar()
    {
        // Desconecta
        $desconexao = $this->desconectar();

        if (!$desconexao['sucesso']) {
            return $desconexao;
        }

        // Aguarda um pouco
        sleep(2);

        // Cria novamente
        return $this->criarInstancia();
    }

    /**
     * Obtém informações detalhadas da instância
     *
     * @return array
     */
    public function obterInformacoes()
    {
        $info = json_decode($this->sender->info_instancia(), true);

        return [
            'sucesso' => true,
            'info' => $info,
            'configuracoes' => [
                'status' => $this->config->obter('instancia_status'),
                'telefone' => $this->config->obter('instancia_telefone'),
                'nome' => $this->config->obter('instancia_nome'),
                'data_criacao' => $this->config->obter('instancia_data_criacao'),
                'data_conexao' => $this->config->obter('instancia_data_conexao'),
                'data_desconexao' => $this->config->obter('instancia_data_desconexao')
            ]
        ];
    }
}
