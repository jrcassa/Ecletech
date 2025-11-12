<?php

namespace Services\Whatsapp;

use Services\Whatsapp\WhatsAppQueueService;
use Services\Whatsapp\WhatsAppConnectionService;
use Services\Whatsapp\WhatsAppWebhookService;
use Services\Whatsapp\WhatsAppRetryService;
use Services\Whatsapp\WhatsAppEntidadeService;
use Models\Whatsapp\WhatsAppConfiguracao;

/**
 * Serviço principal (Orchestrator) do sistema WhatsApp
 *
 * Este serviço coordena todos os outros services e fornece uma interface unificada
 * para as operações do sistema WhatsApp
 */
class WhatsAppService
{
    private $conn;
    private $queueService;
    private $connectionService;
    private $webhookService;
    private $retryService;
    private $entidadeService;
    private $config;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->config = new WhatsAppConfiguracao($db);

        // Instancia services
        $this->queueService = new WhatsAppQueueService($db);
        $this->connectionService = new WhatsAppConnectionService($db);
        $this->webhookService = new WhatsAppWebhookService($db);
        $this->retryService = new WhatsAppRetryService($db);
        $this->entidadeService = new WhatsAppEntidadeService($db);
    }

    // =========================================================================
    // MÉTODOS DE ENVIO
    // =========================================================================

    /**
     * Envia mensagem de texto
     *
     * @param string|array $destinatario Entidade (cliente:123) ou número
     * @param string $mensagem Texto da mensagem
     * @param array $opcoes Opções adicionais (prioridade, agendamento, metadata)
     * @return array
     */
    public function enviarTexto($destinatario, $mensagem, $opcoes = [])
    {
        return $this->enviar([
            'destinatario' => $destinatario,
            'tipo' => 'text',
            'mensagem' => $mensagem,
            'prioridade' => $opcoes['prioridade'] ?? 5,
            'agendado_para' => $opcoes['agendado_para'] ?? null,
            'metadata' => $opcoes['metadata'] ?? null
        ]);
    }

    /**
     * Envia imagem
     *
     * @param string|array $destinatario
     * @param string $url URL ou base64
     * @param string|null $caption
     * @param array $opcoes
     * @return array
     */
    public function enviarImagem($destinatario, $url, $caption = null, $opcoes = [])
    {
        return $this->enviar([
            'destinatario' => $destinatario,
            'tipo' => 'image',
            'arquivo_url' => $this->isBase64($url) ? null : $url,
            'arquivo_base64' => $this->isBase64($url) ? $url : null,
            'mensagem' => $caption,
            'prioridade' => $opcoes['prioridade'] ?? 5,
            'agendado_para' => $opcoes['agendado_para'] ?? null,
            'metadata' => $opcoes['metadata'] ?? null
        ]);
    }

    /**
     * Envia PDF
     *
     * @param string|array $destinatario
     * @param string $url URL ou base64
     * @param string|null $nome Nome do arquivo
     * @param array $opcoes
     * @return array
     */
    public function enviarPdf($destinatario, $url, $nome = null, $opcoes = [])
    {
        return $this->enviar([
            'destinatario' => $destinatario,
            'tipo' => 'pdf',
            'arquivo_url' => $this->isBase64($url) ? null : $url,
            'arquivo_base64' => $this->isBase64($url) ? $url : null,
            'arquivo_nome' => $nome,
            'prioridade' => $opcoes['prioridade'] ?? 5,
            'agendado_para' => $opcoes['agendado_para'] ?? null,
            'metadata' => $opcoes['metadata'] ?? null
        ]);
    }

    /**
     * Envia arquivo genérico
     *
     * @param string|array $destinatario
     * @param string $tipo Tipo (image, pdf, audio, video, document)
     * @param string $url URL ou base64
     * @param string|null $caption
     * @param string|null $nome
     * @param array $opcoes
     * @return array
     */
    public function enviarArquivo($destinatario, $tipo, $url, $caption = null, $nome = null, $opcoes = [])
    {
        return $this->enviar([
            'destinatario' => $destinatario,
            'tipo' => $tipo,
            'arquivo_url' => $this->isBase64($url) ? null : $url,
            'arquivo_base64' => $this->isBase64($url) ? $url : null,
            'mensagem' => $caption,
            'arquivo_nome' => $nome,
            'prioridade' => $opcoes['prioridade'] ?? 5,
            'agendado_para' => $opcoes['agendado_para'] ?? null,
            'metadata' => $opcoes['metadata'] ?? null
        ]);
    }

    /**
     * Método genérico de envio (adiciona à fila)
     *
     * @param array $dados
     * @return array
     */
    public function enviar($dados)
    {
        try {
            // Verifica se instância está conectada
            if (!$this->connectionService->estaConectado()) {
                throw new \Exception('Instância WhatsApp não está conectada');
            }

            // Adiciona à fila
            $queueId = $this->queueService->adicionar($dados);

            return [
                'sucesso' => true,
                'mensagem' => 'Mensagem adicionada à fila com sucesso',
                'queue_id' => $queueId
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // MÉTODOS DE CONEXÃO
    // =========================================================================

    /**
     * Verifica status da conexão
     *
     * @return array
     */
    public function verificarStatus()
    {
        return $this->connectionService->verificarStatus();
    }

    /**
     * Cria nova instância
     *
     * @return array
     */
    public function criarInstancia()
    {
        return $this->connectionService->criarInstancia();
    }

    /**
     * Desconecta instância
     *
     * @return array
     */
    public function desconectar()
    {
        return $this->connectionService->desconectar();
    }

    /**
     * Obtém QR Code
     *
     * @return array
     */
    public function obterQrCode()
    {
        return $this->connectionService->obterQrCode();
    }

    /**
     * Verifica se está conectado
     *
     * @return bool
     */
    public function estaConectado()
    {
        return $this->connectionService->estaConectado();
    }

    /**
     * Monitora saúde da conexão
     *
     * @return array
     */
    public function monitorarSaude()
    {
        return $this->connectionService->monitorarSaude();
    }

    // =========================================================================
    // MÉTODOS DE FILA
    // =========================================================================

    /**
     * Processa fila de mensagens
     *
     * @param int|null $limit
     * @return array
     */
    public function processarFila($limit = null)
    {
        return $this->queueService->processar($limit);
    }

    /**
     * Obtém estatísticas da fila
     *
     * @return array
     */
    public function obterEstatisticasFila()
    {
        return $this->queueService->obterEstatisticas();
    }

    /**
     * Cancela mensagem agendada
     *
     * @param int $queueId
     * @return array
     */
    public function cancelarMensagem($queueId)
    {
        try {
            $this->queueService->cancelar($queueId);
            return [
                'sucesso' => true,
                'mensagem' => 'Mensagem cancelada com sucesso'
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // MÉTODOS DE WEBHOOK
    // =========================================================================

    /**
     * Processa webhook recebido
     *
     * @param array $payload
     * @return array
     */
    public function processarWebhook($payload)
    {
        return $this->webhookService->processar($payload);
    }

    /**
     * Obtém histórico de status de mensagem
     *
     * @param string $messageId
     * @return array
     */
    public function obterHistoricoMensagem($messageId)
    {
        return $this->webhookService->obterHistoricoMensagem($messageId);
    }

    /**
     * Reprocessa webhooks com erro
     *
     * @param int $limit
     * @return array
     */
    public function reprocessarWebhooks($limit = 50)
    {
        return $this->webhookService->reprocessarComErro($limit);
    }

    // =========================================================================
    // MÉTODOS DE RETRY
    // =========================================================================

    /**
     * Processa mensagens prontas para retry
     *
     * @param int $limit
     * @return array
     */
    public function processarRetry($limit = 50)
    {
        $mensagens = $this->retryService->buscarProntasParaRetry($limit);

        $resultado = [
            'total' => count($mensagens),
            'sucesso' => 0,
            'erro' => 0
        ];

        foreach ($mensagens as $mensagem) {
            if ($this->retryService->deveReprocessar($mensagem['id'])) {
                // Reprocessa via fila
                $this->queueService->processar(1);
                $resultado['sucesso']++;
            }
        }

        return $resultado;
    }

    /**
     * Reprocessa mensagem específica manualmente
     *
     * @param int $queueId
     * @return array
     */
    public function reprocessarMensagem($queueId)
    {
        try {
            $this->retryService->reprocessarManual($queueId);
            return [
                'sucesso' => true,
                'mensagem' => 'Mensagem agendada para reprocessamento'
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas de retry
     *
     * @return array
     */
    public function obterEstatisticasRetry()
    {
        return $this->retryService->obterEstatisticas();
    }

    // =========================================================================
    // MÉTODOS DE ENTIDADES
    // =========================================================================

    /**
     * Sincroniza entidade específica
     *
     * @param string $tipo
     * @param int $id
     * @return array
     */
    public function sincronizarEntidade($tipo, $id)
    {
        try {
            $resultado = $this->entidadeService->sincronizarEntidade($tipo, $id);
            return [
                'sucesso' => true,
                'entidade' => $resultado
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Sincroniza lote de entidades
     *
     * @param string $tipo
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function sincronizarLote($tipo, $limit = 100, $offset = 0)
    {
        return $this->entidadeService->sincronizarLote($tipo, $limit, $offset);
    }

    // =========================================================================
    // MÉTODOS DE MANUTENÇÃO
    // =========================================================================

    /**
     * Executa limpeza geral do sistema
     *
     * @return array
     */
    public function executarLimpeza()
    {
        $resultado = [
            'sucesso' => true,
            'detalhes' => []
        ];

        // Limpa fila
        $filaLimpos = $this->queueService->limparAntigas();
        $resultado['detalhes']['fila'] = [
            'removidos' => $filaLimpos
        ];

        // Limpa retry
        $retryLimpos = $this->retryService->limparMensagensAntigas();
        $resultado['detalhes']['retry'] = [
            'removidos' => $retryLimpos
        ];

        // Limpa webhooks
        $webhooksLimpos = $this->webhookService->limparAntigos();
        $resultado['detalhes']['webhooks'] = [
            'removidos' => $webhooksLimpos
        ];

        $resultado['total_removidos'] = $filaLimpos + $retryLimpos + $webhooksLimpos;

        return $resultado;
    }

    /**
     * Obtém dashboard completo com todas as estatísticas
     *
     * @return array
     */
    public function obterDashboard()
    {
        return [
            'conexao' => $this->monitorarSaude(),
            'fila' => $this->obterEstatisticasFila(),
            'retry' => $this->obterEstatisticasRetry(),
            'webhooks' => $this->webhookService->obterEstatisticas()
        ];
    }

    // =========================================================================
    // MÉTODOS UTILITÁRIOS
    // =========================================================================

    /**
     * Verifica se string é base64
     *
     * @param string $string
     * @return bool
     */
    private function isBase64($string)
    {
        if (!is_string($string)) {
            return false;
        }

        // Verifica se tem prefixo data:
        if (strpos($string, 'data:') === 0) {
            return true;
        }

        // Verifica se é base64 válido
        if (base64_encode(base64_decode($string, true)) === $string) {
            return true;
        }

        return false;
    }

    /**
     * Obtém configuração
     *
     * @param string $chave
     * @param mixed $default
     * @return mixed
     */
    public function obterConfiguracao($chave, $default = null)
    {
        return $this->config->obter($chave, $default);
    }

    /**
     * Salva configuração
     *
     * @param string $chave
     * @param mixed $valor
     * @return bool
     */
    public function salvarConfiguracao($chave, $valor)
    {
        return $this->config->salvar($chave, $valor);
    }
}
