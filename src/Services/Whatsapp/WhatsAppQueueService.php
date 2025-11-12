<?php

namespace Services\Whatsapp;

use Models\Whatsapp\WhatsAppQueue;
use Models\Whatsapp\WhatsAppConfiguracao;
use Models\Whatsapp\WhatsAppHistorico;
use Models\Whatsapp\WhatsAppSenderBaileys;
use Services\Whatsapp\WhatsAppEntidadeService;
use Services\Whatsapp\WhatsAppRetryService;

class WhatsAppQueueService
{
    private $conn;
    private $queueModel;
    private $historicoModel;
    private $config;
    private $sender;
    private $entidadeService;
    private $retryService;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->queueModel = new WhatsAppQueue($db);
        $this->historicoModel = new WhatsAppHistorico($db);
        $this->config = new WhatsAppConfiguracao($db);
        $this->sender = new WhatsAppSenderBaileys($db);
        $this->entidadeService = new WhatsAppEntidadeService($db);
        $this->retryService = new WhatsAppRetryService($db);
    }

    /**
     * Adiciona mensagem à fila
     *
     * @param array $dados Dados da mensagem
     * @return int ID da mensagem na fila
     */
    public function adicionar($dados)
    {
        // Valida dados obrigatórios
        if (empty($dados['destinatario'])) {
            throw new \Exception('Destinatário é obrigatório');
        }

        if (empty($dados['tipo'])) {
            throw new \Exception('Tipo de mensagem é obrigatório');
        }

        // Resolve entidade para número
        $destino = $this->entidadeService->resolverDestinatario($dados['destinatario']);

        // Valida número
        if (!$this->validarNumero($destino['numero'])) {
            throw new \Exception('Número de WhatsApp inválido: ' . $destino['numero']);
        }

        // Prepara dados da fila
        $dadosFila = [
            'tipo_entidade' => $destino['tipo_entidade'],
            'entidade_id' => $destino['entidade_id'],
            'entidade_nome' => $destino['nome'],
            'tipo_mensagem' => $dados['tipo'],
            'destinatario' => $destino['numero'],
            'destinatario_nome' => $destino['nome'],
            'conteudo' => $dados['mensagem'] ?? $dados['conteudo'] ?? null,
            'arquivo_url' => $dados['arquivo_url'] ?? null,
            'arquivo_base64' => $dados['arquivo_base64'] ?? null,
            'arquivo_nome' => $dados['arquivo_nome'] ?? null,
            'prioridade' => $dados['prioridade'] ?? 5,
            'agendado_para' => $dados['agendado_para'] ?? null,
            'metadata' => isset($dados['metadata']) ? json_encode($dados['metadata']) : null,
            'status_code' => 1, // Pendente
            'tentativas' => 0
        ];

        // Verifica se deve enviar imediatamente ou agendar
        if (empty($dadosFila['agendado_para']) && $this->config->obter('fila_envio_imediato', false)) {
            $dadosFila['agendado_para'] = date('Y-m-d H:i:s');
        }

        // Adiciona à fila
        $queueId = $this->queueModel->adicionar($dadosFila);

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => 'adicionado_fila',
            'dados' => json_encode([
                'destinatario' => $destino['numero'],
                'tipo' => $dados['tipo'],
                'prioridade' => $dadosFila['prioridade']
            ])
        ]);

        return $queueId;
    }

    /**
     * Processa a fila de mensagens
     *
     * @param int $limit Limite de mensagens a processar
     * @return array Resultado do processamento
     */
    public function processar($limit = null)
    {
        if ($limit === null) {
            $limit = $this->config->obter('fila_lote_tamanho', 10);
        }

        // Verifica se pode processar (horário permitido, limites, etc)
        if (!$this->podeProcessar()) {
            return [
                'processadas' => 0,
                'sucesso' => 0,
                'erro' => 0,
                'motivo' => 'Processamento bloqueado por limites ou horário'
            ];
        }

        // Busca mensagens pendentes
        $mensagens = $this->buscarPendentes($limit);

        $resultado = [
            'processadas' => 0,
            'sucesso' => 0,
            'erro' => 0,
            'detalhes' => []
        ];

        foreach ($mensagens as $mensagem) {
            // Verifica limites novamente antes de cada envio
            if (!$this->verificarLimites()) {
                $resultado['motivo'] = 'Limite de envios atingido';
                break;
            }

            // Processa a mensagem
            $envioResultado = $this->processarMensagem($mensagem);

            $resultado['processadas']++;

            if ($envioResultado['sucesso']) {
                $resultado['sucesso']++;
            } else {
                $resultado['erro']++;
            }

            $resultado['detalhes'][] = $envioResultado;

            // Aplica delay anti-ban
            $this->aplicarDelay();
        }

        return $resultado;
    }

    /**
     * Processa uma mensagem individual
     *
     * @param array $mensagem
     * @return array
     */
    private function processarMensagem($mensagem)
    {
        try {
            $response = null;
            $messageId = null;

            // Envia conforme tipo
            switch ($mensagem['tipo_mensagem']) {
                case 'text':
                    $response = $this->sender->sendText(
                        $mensagem['destinatario'],
                        $mensagem['conteudo']
                    );
                    break;

                case 'image':
                case 'pdf':
                case 'audio':
                case 'video':
                case 'document':
                    $response = $this->sender->sendFile(
                        $mensagem['destinatario'],
                        $mensagem['tipo_mensagem'],
                        $mensagem['arquivo_url'] ?? null,
                        $mensagem['arquivo_base64'] ?? null,
                        $mensagem['conteudo'] ?? null, // caption
                        $mensagem['arquivo_nome'] ?? null
                    );
                    break;

                default:
                    throw new \Exception('Tipo de mensagem não suportado: ' . $mensagem['tipo_mensagem']);
            }

            // Decodifica resposta
            $responseData = json_decode($response, true);

            // Verifica sucesso
            if (isset($responseData['error']) && $responseData['error'] === false) {
                // Extrai message_id da resposta
                $messageId = $responseData['data']['key']['id'] ?? null;

                // Atualiza fila como enviado
                $this->queueModel->atualizarStatus($mensagem['id'], 2, null, $messageId); // 2 = Enviado

                // Registra envio bem-sucedido na entidade
                if ($mensagem['tipo_entidade'] && $mensagem['entidade_id']) {
                    $this->entidadeService->registrarEnvio(
                        $mensagem['tipo_entidade'],
                        $mensagem['entidade_id']
                    );
                }

                // Registra no histórico
                $this->historicoModel->adicionar([
                    'queue_id' => $mensagem['id'],
                    'message_id' => $messageId,
                    'tipo_evento' => 'enviado',
                    'dados' => json_encode([
                        'response' => $responseData,
                        'timestamp' => date('Y-m-d H:i:s')
                    ])
                ]);

                return [
                    'sucesso' => true,
                    'queue_id' => $mensagem['id'],
                    'message_id' => $messageId,
                    'destinatario' => $mensagem['destinatario']
                ];
            } else {
                // Erro no envio
                $erro = $responseData['message'] ?? 'Erro desconhecido ao enviar mensagem';

                // Registra tentativa com erro
                $this->retryService->registrarTentativa($mensagem['id'], false, $erro, $responseData);

                return [
                    'sucesso' => false,
                    'queue_id' => $mensagem['id'],
                    'erro' => $erro,
                    'destinatario' => $mensagem['destinatario']
                ];
            }
        } catch (\Exception $e) {
            // Registra tentativa com erro
            $this->retryService->registrarTentativa($mensagem['id'], false, $e->getMessage());

            return [
                'sucesso' => false,
                'queue_id' => $mensagem['id'],
                'erro' => $e->getMessage(),
                'destinatario' => $mensagem['destinatario']
            ];
        }
    }

    /**
     * Busca mensagens pendentes para processar
     *
     * @param int $limit
     * @return array
     */
    private function buscarPendentes($limit)
    {
        // Busca mensagens pendentes (status 1) e agendadas para agora
        $query = "SELECT * FROM whatsapp_queue
                  WHERE status_code = 1
                  AND (agendado_para IS NULL OR agendado_para <= NOW())
                  ORDER BY prioridade DESC, criado_em ASC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se pode processar fila
     *
     * @return bool
     */
    private function podeProcessar()
    {
        // Verifica horário permitido
        if (!$this->verificarHorarioPermitido()) {
            return false;
        }

        // Verifica limites de envio
        if (!$this->verificarLimites()) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se está em horário permitido
     *
     * @return bool
     */
    private function verificarHorarioPermitado()
    {
        if (!$this->config->obter('antiban_respeitar_horario', true)) {
            return true;
        }

        $horaAtual = (int) date('H');
        $horaInicio = $this->config->obter('antiban_hora_inicio', 8);
        $horaFim = $this->config->obter('antiban_hora_fim', 22);

        return ($horaAtual >= $horaInicio && $horaAtual < $horaFim);
    }

    /**
     * Verifica limites de envio
     *
     * @return bool
     */
    private function verificarLimites()
    {
        return $this->config->verificarLimites();
    }

    /**
     * Aplica delay anti-ban entre mensagens
     */
    private function aplicarDelay()
    {
        $intervalo = $this->config->obterIntervaloAleatorio();
        sleep($intervalo);
    }

    /**
     * Valida número de WhatsApp
     *
     * @param string $numero
     * @return bool
     */
    private function validarNumero($numero)
    {
        // Remove caracteres não numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);

        // Valida comprimento
        $minLength = $this->config->obter('validacao_numero_min_length', 10);
        $maxLength = $this->config->obter('validacao_numero_max_length', 15);

        return (strlen($numero) >= $minLength && strlen($numero) <= $maxLength);
    }

    /**
     * Obtém estatísticas da fila
     *
     * @return array
     */
    public function obterEstatisticas()
    {
        $stats = [];

        // Total por status
        $query = "SELECT status_code, COUNT(*) as total FROM whatsapp_queue GROUP BY status_code";
        $stmt = $this->conn->query($query);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stats['por_status'][$row['status_code']] = $row['total'];
        }

        // Pendentes
        $stats['pendentes'] = $this->queueModel->contarPendentes();

        // Agendadas
        $stmtAgendadas = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue
                                             WHERE agendado_para > NOW()");
        $stats['agendadas'] = $stmtAgendadas->fetch(\PDO::FETCH_ASSOC)['total'];

        // Enviadas hoje
        $stmtHoje = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue
                                        WHERE status_code >= 2
                                        AND DATE(enviado_em) = CURDATE()");
        $stats['enviadas_hoje'] = $stmtHoje->fetch(\PDO::FETCH_ASSOC)['total'];

        // Erros
        $stmtErros = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue WHERE status_code = 0");
        $stats['erros'] = $stmtErros->fetch(\PDO::FETCH_ASSOC)['total'];

        return $stats;
    }

    /**
     * Cancela mensagem agendada
     *
     * @param int $queueId
     * @return bool
     */
    public function cancelar($queueId)
    {
        $mensagem = $this->queueModel->buscarPorId($queueId);

        if (!$mensagem) {
            throw new \Exception('Mensagem não encontrada');
        }

        // Só pode cancelar se ainda estiver pendente
        if ($mensagem['status_code'] != 1) {
            throw new \Exception('Só é possível cancelar mensagens pendentes');
        }

        // Remove da fila
        $query = "DELETE FROM whatsapp_queue WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $queueId]);

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => 'cancelado',
            'dados' => json_encode([
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);

        return true;
    }

    /**
     * Limpa mensagens antigas da fila
     *
     * @param int $dias Dias de retenção
     * @return int Número de registros removidos
     */
    public function limparAntigas($dias = null)
    {
        if ($dias === null) {
            $dias = $this->config->obter('fila_retencao_dias', 30);
        }

        $query = "DELETE FROM whatsapp_queue
                  WHERE status_code IN (2, 3, 4)
                  AND criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':dias' => $dias]);

        return $stmt->rowCount();
    }
}
