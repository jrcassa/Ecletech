<?php

namespace Services\Whatsapp;

use Models\Whatsapp\WhatsAppQueue;
use Models\Whatsapp\WhatsAppConfiguracao;
use Models\Whatsapp\WhatsAppHistorico;

class WhatsAppRetryService
{
    private $conn;
    private $queueModel;
    private $historicoModel;
    private $config;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->queueModel = new WhatsAppQueue($db);
        $this->historicoModel = new WhatsAppHistorico($db);
        $this->config = new WhatsAppConfiguracao($db);
    }

    /**
     * Verifica se mensagem deve ser reprocessada
     *
     * @param int $queueId ID da mensagem na fila
     * @return bool
     */
    public function deveReprocessar($queueId)
    {
        $mensagem = $this->queueModel->buscarPorId($queueId);

        if (!$mensagem) {
            return false;
        }

        // Não reprocessa se já foi enviada com sucesso
        if ($mensagem['status_code'] >= 2) {
            return false;
        }

        // Verifica se atingiu limite de tentativas
        $maxTentativas = $this->config->obter('retry_max_tentativas', 3);
        if ($mensagem['tentativas'] >= $maxTentativas) {
            // Marca como falha definitiva
            $this->marcarFalhaDefinitiva($queueId);
            return false;
        }

        // Verifica se passou tempo mínimo desde última tentativa
        $tempoMinimo = $this->calcularTempoEspera($mensagem['tentativas']);
        $ultimaTentativa = strtotime($mensagem['ultima_tentativa'] ?? $mensagem['criado_em']);
        $agora = time();

        if (($agora - $ultimaTentativa) < $tempoMinimo) {
            return false; // Ainda não é hora de tentar novamente
        }

        return true;
    }

    /**
     * Calcula tempo de espera usando backoff exponencial
     *
     * @param int $tentativa Número da tentativa (0, 1, 2, ...)
     * @return int Segundos a esperar
     */
    public function calcularTempoEspera($tentativa)
    {
        // Configurações de backoff
        $baseDelay = $this->config->obter('retry_base_delay', 60); // 60 segundos
        $maxDelay = $this->config->obter('retry_max_delay', 3600); // 1 hora
        $multiplicador = $this->config->obter('retry_multiplicador', 2);

        // Backoff exponencial: base_delay * (multiplicador ^ tentativa)
        $delay = $baseDelay * pow($multiplicador, $tentativa);

        // Limita ao delay máximo
        if ($delay > $maxDelay) {
            $delay = $maxDelay;
        }

        // Adiciona jitter aleatório para evitar thundering herd
        if ($this->config->obter('retry_usar_jitter', true)) {
            $jitter = rand(0, $delay * 0.1); // 10% de variação
            $delay += $jitter;
        }

        return (int) $delay;
    }

    /**
     * Calcula próxima data/hora de tentativa
     *
     * @param int $tentativas Número de tentativas já realizadas
     * @return string Data/hora no formato MySQL
     */
    public function calcularProximaTentativa($tentativas)
    {
        $tempoEspera = $this->calcularTempoEspera($tentativas);
        $proximaTentativa = time() + $tempoEspera;

        return date('Y-m-d H:i:s', $proximaTentativa);
    }

    /**
     * Registra tentativa de envio
     *
     * @param int $queueId
     * @param bool $sucesso
     * @param string|null $erro
     * @param array|null $response
     */
    public function registrarTentativa($queueId, $sucesso, $erro = null, $response = null)
    {
        $mensagem = $this->queueModel->buscarPorId($queueId);

        if (!$mensagem) {
            return;
        }

        $novasTentativas = $mensagem['tentativas'] + 1;

        if ($sucesso) {
            // Atualiza para enviado
            $this->queueModel->atualizarStatus($queueId, 2, null); // 2 = Enviado
            $this->queueModel->atualizar($queueId, [
                'tentativas' => $novasTentativas,
                'ultima_tentativa' => date('Y-m-d H:i:s'),
                'erro' => null
            ]);
        } else {
            // Registra erro e agenda próxima tentativa
            $maxTentativas = $this->config->obter('retry_max_tentativas', 3);

            if ($novasTentativas >= $maxTentativas) {
                // Última tentativa falhou - marca como falha definitiva
                $this->marcarFalhaDefinitiva($queueId, $erro);
            } else {
                // Agenda próxima tentativa
                $proximaTentativa = $this->calcularProximaTentativa($novasTentativas);

                $this->queueModel->atualizar($queueId, [
                    'tentativas' => $novasTentativas,
                    'ultima_tentativa' => date('Y-m-d H:i:s'),
                    'proxima_tentativa' => $proximaTentativa,
                    'erro' => $erro,
                    'status_code' => 0 // Erro
                ]);
            }
        }

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => $sucesso ? 'retry_sucesso' : 'retry_erro',
            'dados' => json_encode([
                'tentativa' => $novasTentativas,
                'sucesso' => $sucesso,
                'erro' => $erro,
                'response' => $response
            ])
        ]);
    }

    /**
     * Marca mensagem como falha definitiva
     *
     * @param int $queueId
     * @param string|null $ultimoErro
     */
    private function marcarFalhaDefinitiva($queueId, $ultimoErro = null)
    {
        $this->queueModel->atualizarStatus($queueId, 0, $ultimoErro); // 0 = Erro
        $this->queueModel->atualizar($queueId, [
            'proxima_tentativa' => null
        ]);

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => 'falha_definitiva',
            'dados' => json_encode([
                'erro' => $ultimoErro,
                'tentativas_realizadas' => $this->queueModel->buscarPorId($queueId)['tentativas'] ?? 0
            ])
        ]);
    }

    /**
     * Busca mensagens prontas para retry
     *
     * @param int $limit
     * @return array
     */
    public function buscarProntasParaRetry($limit = 50)
    {
        $maxTentativas = $this->config->obter('retry_max_tentativas', 3);

        $query = "SELECT * FROM whatsapp_queue
                  WHERE status_code = 0
                  AND tentativas < :max_tentativas
                  AND (proxima_tentativa IS NULL OR proxima_tentativa <= NOW())
                  ORDER BY prioridade DESC, criado_em ASC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':max_tentativas', $maxTentativas, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Reprocessa uma mensagem específica manualmente
     *
     * @param int $queueId
     * @return bool
     */
    public function reprocessarManual($queueId)
    {
        $mensagem = $this->queueModel->buscarPorId($queueId);

        if (!$mensagem) {
            throw new \Exception("Mensagem não encontrada");
        }

        // Reset tentativas se configurado
        if ($this->config->obter('retry_resetar_tentativas_manual', false)) {
            $this->queueModel->atualizar($queueId, [
                'tentativas' => 0,
                'proxima_tentativa' => date('Y-m-d H:i:s'),
                'status_code' => 1, // Pendente
                'erro' => null
            ]);
        } else {
            // Apenas agenda para agora
            $this->queueModel->atualizar($queueId, [
                'proxima_tentativa' => date('Y-m-d H:i:s'),
                'status_code' => 1 // Pendente
            ]);
        }

        // Registra no histórico
        $this->historicoModel->adicionar([
            'queue_id' => $queueId,
            'tipo_evento' => 'reprocessamento_manual',
            'dados' => json_encode([
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);

        return true;
    }

    /**
     * Limpa mensagens antigas com falha definitiva
     *
     * @param int $diasAntigos Dias para considerar como antigo
     * @return int Número de registros removidos
     */
    public function limparMensagensAntigas($diasAntigos = null)
    {
        if ($diasAntigos === null) {
            $diasAntigos = $this->config->obter('retry_limpar_apos_dias', 30);
        }

        $query = "DELETE FROM whatsapp_queue
                  WHERE status_code = 0
                  AND tentativas >= :max_tentativas
                  AND criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':max_tentativas' => $this->config->obter('retry_max_tentativas', 3),
            ':dias' => $diasAntigos
        ]);

        return $stmt->rowCount();
    }

    /**
     * Obtém estatísticas de retry
     *
     * @return array
     */
    public function obterEstatisticas()
    {
        // Total de mensagens com erro
        $stmtErro = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue WHERE status_code = 0");
        $totalErros = $stmtErro->fetch(\PDO::FETCH_ASSOC)['total'];

        // Mensagens aguardando retry
        $stmtRetry = $this->conn->query("SELECT COUNT(*) as total FROM whatsapp_queue
                                         WHERE status_code = 0
                                         AND proxima_tentativa IS NOT NULL
                                         AND proxima_tentativa > NOW()");
        $aguardandoRetry = $stmtRetry->fetch(\PDO::FETCH_ASSOC)['total'];

        // Mensagens com falha definitiva
        $maxTentativas = $this->config->obter('retry_max_tentativas', 3);
        $stmtFalha = $this->conn->prepare("SELECT COUNT(*) as total FROM whatsapp_queue
                                           WHERE status_code = 0
                                           AND tentativas >= :max_tentativas");
        $stmtFalha->execute([':max_tentativas' => $maxTentativas]);
        $falhaDefinitiva = $stmtFalha->fetch(\PDO::FETCH_ASSOC)['total'];

        // Mensagens prontas para retry agora
        $stmtProntas = $this->conn->prepare("SELECT COUNT(*) as total FROM whatsapp_queue
                                             WHERE status_code = 0
                                             AND tentativas < :max_tentativas
                                             AND (proxima_tentativa IS NULL OR proxima_tentativa <= NOW())");
        $stmtProntas->execute([':max_tentativas' => $maxTentativas]);
        $prontasAgora = $stmtProntas->fetch(\PDO::FETCH_ASSOC)['total'];

        return [
            'total_erros' => $totalErros,
            'aguardando_retry' => $aguardandoRetry,
            'falha_definitiva' => $falhaDefinitiva,
            'prontas_para_retry' => $prontasAgora
        ];
    }
}
