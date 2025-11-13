<?php

namespace App\Services\Whatsapp;

use App\Models\Whatsapp\ModelWhatsappQueue;
use App\Models\Whatsapp\ModelWhatsappConfiguracao;
use App\Models\Whatsapp\ModelWhatsappHistorico;
use App\Models\Whatsapp\ModelWhatsappBaileys;
use App\Models\Whatsapp\ModelWhatsappWebhook;
use App\Services\Whatsapp\ServiceWhatsappEntidade;
use App\Core\BancoDados;
use App\Helpers\AuxiliarWhatsapp;

/**
 * Service principal para gerenciar todo o sistema WhatsApp
 */
class ServiceWhatsapp
{
    private BancoDados $db;
    private ModelWhatsappQueue $queueModel;
    private ModelWhatsappConfiguracao $configModel;
    private ModelWhatsappHistorico $historicoModel;
    private ?ModelWhatsappBaileys $baileys = null;
    private ServiceWhatsappEntidade $entidadeService;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->queueModel = new ModelWhatsappQueue();
        $this->configModel = new ModelWhatsappConfiguracao();
        $this->historicoModel = new ModelWhatsappHistorico();
        // Baileys é instanciado sob demanda (lazy loading)
        $this->entidadeService = new ServiceWhatsappEntidade();
    }

    /**
     * Obtém instância do Baileys (lazy loading)
     */
    private function getBaileys(): ModelWhatsappBaileys
    {
        if ($this->baileys === null) {
            $this->baileys = new ModelWhatsappBaileys();
        }
        return $this->baileys;
    }

    /**
     * Envia mensagem (via fila ou direto)
     *
     * @param array $dados Dados da mensagem
     * @return array Resultado do envio
     */
    public function enviarMensagem(array $dados): array
    {
        try {
            // Resolve destinatário
            $destino = $this->entidadeService->resolverDestinatario($dados['destinatario']);

            // Valida número
            if (!AuxiliarWhatsapp::validarNumero($destino['numero'])) {
                throw new \Exception('Número de WhatsApp inválido');
            }

            // Determina modo de envio
            // Prioridade: 1) Parâmetro explícito, 2) Configuração do sistema, 3) Padrão (fila)
            $modoEnvio = $dados['modo_envio'] ??
                         $this->configModel->obter('modo_envio_padrao', 'fila');

            // Prepara dados completos
            $dadosCompletos = [
                'tipo_entidade' => $destino['tipo_entidade'],
                'entidade_id' => $destino['entidade_id'],
                'entidade_nome' => $destino['nome'],
                'tipo_mensagem' => $dados['tipo'],
                'destinatario' => $destino['numero'],
                'conteudo' => $dados['mensagem'] ?? $dados['conteudo'] ?? null,
                'arquivo_url' => $dados['arquivo_url'] ?? null,
                'arquivo_base64' => $dados['arquivo_base64'] ?? null,
                'arquivo_nome' => $dados['arquivo_nome'] ?? null,
                'prioridade' => $dados['prioridade'] ?? 'normal',
                'agendado_para' => $dados['agendado_para'] ?? null,
                'metadata' => isset($dados['metadata']) ? json_encode($dados['metadata']) : null
            ];

            // Executa envio conforme modo
            if ($modoEnvio === 'direto') {
                return $this->enviarDireto($dadosCompletos);
            } else {
                return $this->enviarViaFila($dadosCompletos);
            }

        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia mensagem via fila (assíncrono)
     *
     * @param array $dados Dados da mensagem
     * @return array Resultado do envio
     */
    private function enviarViaFila(array $dados): array
    {
        // Prepara dados da fila com mapeamento explícito para o schema do banco
        $dadosFila = [
            // Entidade
            'tipo_entidade' => $dados['tipo_entidade'] ?? null,
            'entidade_id' => $dados['entidade_id'] ?? null,
            'entidade_nome' => $dados['entidade_nome'] ?? null,

            // Tipo e destino
            'tipo_mensagem' => $dados['tipo_mensagem'],
            'destinatario' => $dados['destinatario'],

            // Conteúdo (mapeia 'conteudo' -> 'mensagem')
            'mensagem' => $dados['conteudo'] ?? null,
            'arquivo_url' => $dados['arquivo_url'] ?? null,
            'arquivo_base64' => $dados['arquivo_base64'] ?? null,
            'arquivo_nome' => $dados['arquivo_nome'] ?? null,
            'dados_extras' => $dados['metadata'] ?? null, // mapeia 'metadata' -> 'dados_extras'

            // Controle
            'prioridade' => $dados['prioridade'] ?? 'normal',
            'status' => 'pendente',
            'tentativas' => 0,
            'status_code' => 1,

            // Agendamento
            'agendado_para' => $dados['agendado_para'] ?? null
        ];

        // Adiciona à fila
        $queueId = $this->queueModel->adicionar($dadosFila);

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => 'adicionado_fila',
            'tipo_entidade' => $dados['tipo_entidade'] ?? null,
            'entidade_id' => $dados['entidade_id'] ?? null,
            'entidade_nome' => $dados['entidade_nome'] ?? null,
            'destinatario' => $dados['destinatario'],
            'tipo_mensagem' => $dados['tipo_mensagem'],
            'status' => 'pendente',
            'status_code' => 1,
            'mensagem' => json_encode([
                'destinatario' => $dados['destinatario'],
                'tipo' => $dados['tipo_mensagem']
            ])
        ]);

        return [
            'sucesso' => true,
            'mensagem' => 'Mensagem adicionada à fila',
            'modo' => 'fila',
            'queue_id' => $queueId
        ];
    }

    /**
     * Envia mensagem diretamente (síncrono)
     *
     * @param array $dados Dados da mensagem
     * @return array Resultado do envio
     */
    private function enviarDireto(array $dados): array
    {
        $response = null;

        // Envia conforme tipo
        switch ($dados['tipo_mensagem']) {
            case 'text':
                $response = $this->getBaileys()->sendText(
                    $dados['destinatario'],
                    $dados['conteudo']
                );
                break;

            case 'image':
            case 'pdf':
            case 'audio':
            case 'video':
            case 'document':
                $response = $this->getBaileys()->sendFile(
                    $dados['destinatario'],
                    $dados['tipo_mensagem'],
                    $dados['arquivo_url'] ?? null,
                    $dados['arquivo_base64'] ?? null,
                    $dados['conteudo'] ?? null,
                    $dados['arquivo_nome'] ?? null
                );
                break;

            default:
                throw new \Exception('Tipo de mensagem não suportado');
        }

        $responseData = json_decode($response, true);

        // Verifica sucesso
        if (isset($responseData['error']) && $responseData['error'] === false) {
            $messageId = $responseData['data']['key']['id'] ?? null;

            // Registra envio direto na entidade
            $this->entidadeService->registrarEnvio(
                $dados['tipo_entidade'],
                $dados['entidade_id']
            );

            // Registra no histórico (sem queue_id)
            $this->historicoModel->adicionar([
                'queue_id' => null,
                'message_id' => $messageId,
                'tipo_evento' => 'enviado_direto',
                'tipo_entidade' => $dados['tipo_entidade'] ?? null,
                'entidade_id' => $dados['entidade_id'] ?? null,
                'entidade_nome' => $dados['entidade_nome'] ?? null,
                'destinatario' => $dados['destinatario'],
                'tipo_mensagem' => $dados['tipo_mensagem'],
                'status' => 'enviado',
                'status_code' => 2,
                'mensagem' => json_encode([
                    'response' => $responseData,
                    'conteudo' => $dados['conteudo']
                ])
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Mensagem enviada diretamente',
                'modo' => 'direto',
                'message_id' => $messageId,
                'dados' => $responseData['data'] ?? null
            ];
        } else {
            $erro = $responseData['message'] ?? 'Erro desconhecido ao enviar mensagem';

            // Registra erro no histórico
            $this->historicoModel->adicionar([
                'queue_id' => null,
                'message_id' => null,
                'tipo_evento' => 'erro_envio_direto',
                'tipo_entidade' => $dados['tipo_entidade'] ?? null,
                'entidade_id' => $dados['entidade_id'] ?? null,
                'entidade_nome' => $dados['entidade_nome'] ?? null,
                'destinatario' => $dados['destinatario'],
                'tipo_mensagem' => $dados['tipo_mensagem'],
                'status' => 'erro',
                'status_code' => 0,
                'mensagem' => json_encode([
                    'erro' => $erro,
                    'response' => $responseData
                ])
            ]);

            throw new \Exception($erro);
        }
    }

    /**
     * Processa fila de mensagens
     */
    public function processarFila(int $limit = 10): array
    {
        $mensagens = $this->queueModel->buscarPendentes($limit);

        $resultado = [
            'processadas' => 0,
            'sucesso' => 0,
            'erro' => 0,
            'detalhes' => []
        ];

        foreach ($mensagens as $mensagem) {
            $envioResultado = $this->processarMensagem($mensagem);

            $resultado['processadas']++;

            if ($envioResultado['sucesso']) {
                $resultado['sucesso']++;
            } else {
                $resultado['erro']++;
            }

            $resultado['detalhes'][] = $envioResultado;

            // Delay anti-ban
            $this->aplicarDelay();
        }

        return $resultado;
    }

    /**
     * Processa mensagem individual
     */
    private function processarMensagem(array $mensagem): array
    {
        try {
            $response = null;

            // Envia conforme tipo
            switch ($mensagem['tipo_mensagem']) {
                case 'text':
                    $response = $this->getBaileys()->sendText(
                        $mensagem['destinatario'],
                        $mensagem['mensagem']  // Campo 'mensagem' no banco, não 'conteudo'
                    );
                    break;

                case 'image':
                case 'pdf':
                case 'audio':
                case 'video':
                case 'document':
                    $response = $this->getBaileys()->sendFile(
                        $mensagem['destinatario'],
                        $mensagem['tipo_mensagem'],
                        $mensagem['arquivo_url'] ?? null,
                        $mensagem['arquivo_base64'] ?? null,
                        $mensagem['mensagem'] ?? null,  // Campo 'mensagem' no banco, não 'conteudo'
                        $mensagem['arquivo_nome'] ?? null
                    );
                    break;

                default:
                    throw new \Exception('Tipo de mensagem não suportado');
            }

            $responseData = json_decode($response, true);

            // Verifica sucesso
            if (isset($responseData['error']) && $responseData['error'] === false) {
                $messageId = $responseData['data']['key']['id'] ?? null;

                // Registra envio
                $this->entidadeService->registrarEnvio(
                    $mensagem['tipo_entidade'],
                    $mensagem['entidade_id']
                );

                // Registra no histórico ANTES de deletar (para manter referência do queue_id)
                $this->historicoModel->adicionar([
                    'queue_id' => $mensagem['id'],
                    'message_id' => $messageId,
                    'tipo_evento' => 'enviado',
                    'tipo_entidade' => $mensagem['tipo_entidade'] ?? null,
                    'entidade_id' => $mensagem['entidade_id'] ?? null,
                    'entidade_nome' => $mensagem['entidade_nome'] ?? null,
                    'destinatario' => $mensagem['destinatario'],
                    'tipo_mensagem' => $mensagem['tipo_mensagem'],
                    'status' => 'enviado',
                    'status_code' => 2,
                    'mensagem' => json_encode(['response' => $responseData])
                ]);

                // Remove da fila após envio bem-sucedido
                // O queue_id no histórico será setado para NULL automaticamente (ON DELETE SET NULL)
                $this->queueModel->deletar($mensagem['id']);

                return [
                    'sucesso' => true,
                    'queue_id' => $mensagem['id'],
                    'message_id' => $messageId
                ];
            } else {
                $erro = $responseData['message'] ?? 'Erro desconhecido';
                $this->registrarErro($mensagem['id'], $erro);

                return [
                    'sucesso' => false,
                    'queue_id' => $mensagem['id'],
                    'erro' => $erro
                ];
            }

        } catch (\Exception $e) {
            $this->registrarErro($mensagem['id'], $e->getMessage());

            return [
                'sucesso' => false,
                'queue_id' => $mensagem['id'],
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Registra erro e agenda retry
     */
    private function registrarErro(int $queueId, string $erro): void
    {
        $mensagem = $this->queueModel->buscarPorId($queueId);
        $novasTentativas = $mensagem['tentativas'] + 1;
        $maxTentativas = $this->configModel->obter('retry_max_tentativas', 3);

        if ($novasTentativas >= $maxTentativas) {
            // Falha definitiva - registra no histórico e remove da fila
            $this->historicoModel->adicionar([
                'queue_id' => $queueId,
                'message_id' => null,
                'tipo_evento' => 'erro_envio',
                'tipo_entidade' => $mensagem['tipo_entidade'] ?? null,
                'entidade_id' => $mensagem['entidade_id'] ?? null,
                'entidade_nome' => $mensagem['entidade_nome'] ?? null,
                'destinatario' => $mensagem['destinatario'],
                'tipo_mensagem' => $mensagem['tipo_mensagem'],
                'status' => 'erro',
                'status_code' => 0,
                'mensagem' => json_encode([
                    'erro' => $erro,
                    'tentativas' => $novasTentativas
                ])
            ]);

            // Remove da fila após erro definitivo
            // O queue_id no histórico será setado para NULL automaticamente (ON DELETE SET NULL)
            $this->queueModel->deletar($queueId);
        } else {
            // Agenda retry - mantém como pendente para nova tentativa
            $this->queueModel->atualizar($queueId, [
                'tentativas' => $novasTentativas,
                'erro_mensagem' => $erro,
                'status_code' => 0,
                'status' => 'pendente'
            ]);
        }
    }

    /**
     * Calcula tempo de backoff exponencial
     */
    private function calcularBackoff(int $tentativa): int
    {
        $base = $this->configModel->obter('retry_base_delay', 60);
        $multiplicador = $this->configModel->obter('retry_multiplicador', 2);
        return $base * pow($multiplicador, $tentativa);
    }

    /**
     * Aplica delay anti-ban
     */
    private function aplicarDelay(): void
    {
        $min = $this->configModel->obter('antiban_delay_min', 3);
        $max = $this->configModel->obter('antiban_delay_max', 7);
        sleep(rand($min, $max));
    }

    /**
     * Processa webhook
     */
    public function processarWebhook(array $payload): array
    {
        $webhookModel = new ModelWhatsappWebhook();

        try {
            // Armazena webhook
            $webhookId = $webhookModel->adicionar([
                'payload' => json_encode($payload),
                'processado' => false
            ]);

            // Extrai informações
            $info = $this->extrairInfoWebhook($payload);

            if (!$info) {
                $webhookModel->marcarProcessado($webhookId, true, 'Webhook não relevante');
                return ['sucesso' => true, 'processado' => false];
            }

            // Atualiza status no histórico (não mais na fila, pois mensagens enviadas são deletadas)
            $statusCode = AuxiliarWhatsapp::webhookParaStatusCode($info['status']);

            // Registra atualização de status no histórico
            if ($statusCode === 3 || $statusCode === 4) {
                // Busca evento original de envio para copiar informações
                $eventoOriginal = $this->historicoModel->buscarPorMessageId($info['message_id']);
                $dadosOriginais = !empty($eventoOriginal) ? $eventoOriginal[0] : null;

                $this->historicoModel->adicionar([
                    'queue_id' => null,
                    'message_id' => $info['message_id'],
                    'tipo_evento' => $statusCode === 3 ? 'entregue' : 'lido',
                    'tipo_entidade' => $dadosOriginais['tipo_entidade'] ?? null,
                    'entidade_id' => $dadosOriginais['entidade_id'] ?? null,
                    'entidade_nome' => $dadosOriginais['entidade_nome'] ?? null,
                    'destinatario' => $dadosOriginais['destinatario'] ?? null,
                    'tipo_mensagem' => $dadosOriginais['tipo_mensagem'] ?? null,
                    'status' => $statusCode === 3 ? 'entregue' : 'lido',
                    'status_code' => $statusCode,
                    'mensagem' => json_encode(['webhook_data' => $payload['data'] ?? null])
                ]);
            }

            $webhookModel->marcarProcessado($webhookId, true);
            $webhookModel->atualizar($webhookId, [
                'message_id' => $info['message_id'],
                'status' => $info['status']
            ]);

            return ['sucesso' => true, 'processado' => true];

        } catch (\Exception $e) {
            if (isset($webhookId)) {
                $webhookModel->marcarProcessado($webhookId, false, $e->getMessage());
            }
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Extrai informações do webhook
     */
    private function extrairInfoWebhook(array $payload): ?array
    {
        if (!isset($payload['event']) || !isset($payload['data'])) {
            return null;
        }

        $messageId = $payload['data']['key']['id'] ?? $payload['data']['id'] ?? null;
        $status = $payload['data']['update']['status'] ?? $payload['data']['status'] ?? null;

        if (!$messageId || !$status) {
            return null;
        }

        return [
            'message_id' => $messageId,
            'status' => $status
        ];
    }

    /**
     * Obtém estatísticas
     */
    public function obterEstatisticas(): array
    {
        // Verifica se está configurado
        $statusConfig = $this->verificarConfiguracao();

        $stats = [
            'configuracao' => $statusConfig,
            // Pendentes: contagem da fila (mensagens ainda não enviadas)
            'pendentes' => $this->queueModel->contarPendentes(),
            // Estatísticas do histórico (últimas 24 horas)
            'erro' => $this->historicoModel->contarErrosUltimas24h(),
            'enviado' => $this->historicoModel->contarPorStatusUltimas24h(2),
            'entregue' => $this->historicoModel->contarPorStatusUltimas24h(3),
            'lido' => $this->historicoModel->contarPorStatusUltimas24h(4)
        ];

        return $stats;
    }

    /**
     * Verifica se a API está configurada
     */
    public function verificarConfiguracao(): array
    {
        // Tenta instanciar baileys e verificar
        try {
            $baileys = $this->getBaileys();
            return $baileys->estaConfigurado();
        } catch (\Exception $e) {
            return [
                'configurado' => false,
                'api_url_configurada' => false,
                'token_configurado' => false,
                'mensagem' => 'Execute a migration para criar as configurações: database/migrations/2025_01_12_create_whatsapp_tables.sql'
            ];
        }
    }
}
