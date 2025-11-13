<?php

namespace App\Services\Email;

use App\Models\Email\ModelEmailQueue;
use App\Models\Email\ModelEmailConfiguracao;
use App\Models\Email\ModelEmailHistorico;
use App\Models\Email\ModelEmailSMTP;
use App\Models\Email\ModelEmailEntidade;
use App\Models\Email\ModelEmailLog;
use App\Services\Email\ServiceEmailEntidade;
use App\Core\BancoDados;
use App\Helpers\AuxiliarEmail;

/**
 * Service principal para gerenciar todo o sistema de Email
 * Padrão: Segue estrutura do ServiceWhatsapp
 */
class ServiceEmail
{
    private BancoDados $db;
    private ModelEmailQueue $queueModel;
    private ModelEmailConfiguracao $configModel;
    private ModelEmailHistorico $historicoModel;
    private ModelEmailLog $logModel;
    private ?ModelEmailSMTP $smtp = null;
    private ServiceEmailEntidade $entidadeService;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->queueModel = new ModelEmailQueue();
        $this->configModel = new ModelEmailConfiguracao();
        $this->historicoModel = new ModelEmailHistorico();
        $this->logModel = new ModelEmailLog();
        // SMTP é instanciado sob demanda (lazy loading)
        $this->entidadeService = new ServiceEmailEntidade();
    }

    /**
     * Escreve log detalhado em arquivo para debug
     */
    private function logToFile(string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        $logFile = $logDir . '/email.log';

        // Cria diretório se não existir
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // Formata mensagem
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $logEntry = "[{$timestamp}] {$message}{$contextStr}\n";

        // Escreve no arquivo
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Obtém instância do SMTP (lazy loading)
     */
    private function getSMTP(): ModelEmailSMTP
    {
        if ($this->smtp === null) {
            $this->smtp = new ModelEmailSMTP();
        }
        return $this->smtp;
    }

    /**
     * Envia email (via fila ou direto)
     */
    public function enviarEmail(array $dados): array
    {
        // LOG: Início do envio
        $this->logToFile('=== INÍCIO ENVIO EMAIL ===', [
            'destinatario' => $dados['destinatario'] ?? 'N/A',
            'assunto' => $dados['assunto'] ?? 'N/A',
            'modo_envio' => $dados['modo_envio'] ?? 'auto',
            'tem_corpo_html' => !empty($dados['corpo_html']),
            'tem_corpo_texto' => !empty($dados['corpo_texto']),
            'corpo' => !empty($dados['corpo']) ? 'SIM' : 'NÃO'
        ]);

        try {
            // Resolve destinatário (pode ser entidade ou email direto)
            $destino = $this->entidadeService->resolverDestinatario($dados['destinatario']);

            $this->logToFile('Destinatário resolvido', [
                'email' => $destino['email'],
                'nome' => $destino['nome'] ?? 'N/A',
                'tipo_entidade' => $destino['tipo_entidade'] ?? 'N/A'
            ]);

            // Valida email
            if (!AuxiliarEmail::validarEmail($destino['email'])) {
                throw new \Exception('Email do destinatário inválido');
            }

            // Verifica se está bloqueado
            if ($destino['bloqueado'] ?? false) {
                throw new \Exception('Destinatário bloqueado: ' . ($destino['motivo_bloqueio'] ?? 'sem motivo'));
            }

            // Determina modo de envio
            $modoEnvio = $dados['modo_envio'] ?? $this->configModel->obter('modo_envio', 'fila');

            // Prepara dados completos
            $dadosCompletos = [
                // Entidade
                'tipo_entidade' => $destino['tipo_entidade'] ?? null,
                'entidade_id' => $destino['entidade_id'] ?? null,
                'entidade_nome' => $destino['nome'] ?? null,

                // Destinatário
                'destinatario_email' => $destino['email'],
                'destinatario_nome' => $destino['nome'] ?? null,
                'cc' => isset($dados['cc']) ? json_encode($dados['cc']) : null,
                'bcc' => isset($dados['bcc']) ? json_encode($dados['bcc']) : null,
                'reply_to' => $dados['reply_to'] ?? null,

                // Conteúdo
                'assunto' => $dados['assunto'],
                'corpo_texto' => $dados['corpo_texto'] ?? null,
                'corpo_html' => $dados['corpo_html'] ?? null,
                'template' => $dados['template'] ?? null,
                'dados_template' => isset($dados['dados_template']) ? json_encode($dados['dados_template']) : null,

                // Anexos
                'anexos' => isset($dados['anexos']) ? json_encode($dados['anexos']) : null,

                // Controle
                'prioridade' => $dados['prioridade'] ?? $this->configModel->obter('fila_prioridade_padrao', 'normal'),
                'agendado_para' => $dados['agendado_para'] ?? null,
                'dados_extras' => isset($dados['dados_extras']) ? json_encode($dados['dados_extras']) : null,

                // IP e User Agent
                'ip_origem' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            // Processa template se fornecido
            if (!empty($dadosCompletos['template'])) {
                $dadosCompletos = $this->processarTemplate($dadosCompletos);
            }

            // Injeta tracking code se habilitado
            $trackingHabilitado = $this->configModel->obter('tracking_habilitado', true);
            $this->logToFile('Verificando tracking', [
                'tracking_habilitado' => $trackingHabilitado ? 'SIM' : 'NÃO',
                'tem_corpo_html' => !empty($dadosCompletos['corpo_html']),
                'tem_corpo_texto' => !empty($dadosCompletos['corpo_texto'])
            ]);

            if ($trackingHabilitado) {
                $dadosCompletos['tracking_code'] = md5(uniqid(rand(), true));
                $this->logToFile('Tracking code gerado', [
                    'tracking_code' => $dadosCompletos['tracking_code']
                ]);

                // SEMPRE converte para HTML estruturado quando tracking está habilitado
                if (empty($dadosCompletos['corpo_html']) && !empty($dadosCompletos['corpo_texto'])) {
                    $dadosCompletos['corpo_html'] = $this->converterTextoParaHtmlEstruturado($dadosCompletos['corpo_texto']);
                    $this->logToFile('Converteu corpo_texto para HTML estruturado', [
                        'corpo_html_gerado' => substr($dadosCompletos['corpo_html'], 0, 200) . '...'
                    ]);
                } elseif (!empty($dadosCompletos['corpo_html'])) {
                    // Se já tem HTML mas não tem estrutura completa, envolve em estrutura HTML
                    if (stripos($dadosCompletos['corpo_html'], '<!DOCTYPE') === false &&
                        stripos($dadosCompletos['corpo_html'], '<html') === false) {
                        $dadosCompletos['corpo_html'] = $this->envolverEmHtmlEstruturado($dadosCompletos['corpo_html'], $dadosCompletos['assunto'] ?? 'Email');
                        $this->logToFile('Envolveu HTML parcial em estrutura completa', [
                            'corpo_html_preview' => substr($dadosCompletos['corpo_html'], 0, 200) . '...'
                        ]);
                    }
                }

                // Injeta pixel e links de tracking
                $this->logToFile('Antes de injetar tracking', [
                    'corpo_html_length' => strlen($dadosCompletos['corpo_html'] ?? ''),
                    'corpo_html_preview' => substr($dadosCompletos['corpo_html'] ?? '', 0, 200)
                ]);

                $dadosCompletos = $this->injetarTracking($dadosCompletos);

                $this->logToFile('Depois de injetar tracking', [
                    'corpo_html_length' => strlen($dadosCompletos['corpo_html'] ?? ''),
                    'corpo_html_preview' => substr($dadosCompletos['corpo_html'] ?? '', 0, 200),
                    'corpo_html_final_500_chars' => substr($dadosCompletos['corpo_html'] ?? '', -500)
                ]);
            } else {
                $this->logToFile('Tracking DESABILITADO - email será enviado sem tracking');
            }

            // Executa envio conforme modo
            $this->logToFile('Modo de envio selecionado', [
                'modo' => $modoEnvio,
                'tracking_code_final' => $dadosCompletos['tracking_code'] ?? 'NENHUM'
            ]);

            if ($modoEnvio === 'direto') {
                $resultado = $this->enviarDireto($dadosCompletos);
                $this->logToFile('=== FIM ENVIO EMAIL (direto) ===', [
                    'sucesso' => $resultado['sucesso'],
                    'tracking_code' => $resultado['tracking_code'] ?? 'N/A'
                ]);
                return $resultado;
            } else {
                $resultado = $this->enviarViaFila($dadosCompletos);
                $this->logToFile('=== FIM ENVIO EMAIL (fila) ===', [
                    'sucesso' => $resultado['sucesso'],
                    'queue_id' => $resultado['queue_id'] ?? 'N/A',
                    'tracking_code' => $resultado['tracking_code'] ?? 'N/A'
                ]);
                return $resultado;
            }

        } catch (\Exception $e) {
            $this->logToFile('ERRO ao enviar email', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->logModel->adicionar([
                'tipo' => 'envio_erro',
                'nivel' => 'error',
                'mensagem' => 'Erro ao enviar email: ' . $e->getMessage(),
                'contexto' => json_encode($dados)
            ]);

            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia email via fila (assíncrono)
     */
    private function enviarViaFila(array $dados): array
    {
        // Adiciona à fila
        $queueId = $this->queueModel->adicionar([
            'tipo_entidade' => $dados['tipo_entidade'],
            'entidade_id' => $dados['entidade_id'],
            'entidade_nome' => $dados['entidade_nome'],
            'destinatario_email' => $dados['destinatario_email'],
            'destinatario_nome' => $dados['destinatario_nome'],
            'cc' => $dados['cc'],
            'bcc' => $dados['bcc'],
            'reply_to' => $dados['reply_to'],
            'assunto' => $dados['assunto'],
            'corpo_texto' => $dados['corpo_texto'],
            'corpo_html' => $dados['corpo_html'],
            'template' => $dados['template'],
            'dados_template' => $dados['dados_template'],
            'anexos' => $dados['anexos'],
            'prioridade' => $dados['prioridade'],
            'status' => 1, // pendente
            'tentativas' => 0,
            'agendado_para' => $dados['agendado_para'],
            'tracking_code' => $dados['tracking_code'] ?? null,
            'dados_extras' => $dados['dados_extras'],
            'ip_origem' => $dados['ip_origem'],
            'user_agent' => $dados['user_agent']
        ]);

        $this->logModel->adicionar([
            'tipo' => 'fila_adicionar',
            'nivel' => 'info',
            'mensagem' => 'Email adicionado à fila',
            'contexto' => json_encode(['queue_id' => $queueId, 'destinatario' => $dados['destinatario_email']]),
            'queue_id' => $queueId
        ]);

        return [
            'sucesso' => true,
            'mensagem' => 'Email adicionado à fila',
            'modo' => 'fila',
            'queue_id' => $queueId,
            'tracking_code' => $dados['tracking_code'] ?? null
        ];
    }

    /**
     * Envia email diretamente (síncrono)
     */
    private function enviarDireto(array $dados): array
    {
        try {
            // Envia via SMTP
            $resultado = $this->getSMTP()->enviar($dados);

            if ($resultado['sucesso']) {
                // Registra no histórico
                $this->historicoModel->adicionar([
                    'message_id' => $resultado['message_id'],
                    'tracking_code' => $dados['tracking_code'] ?? null,
                    'tipo_evento' => 'enviado_direto',
                    'tipo_entidade' => $dados['tipo_entidade'],
                    'entidade_id' => $dados['entidade_id'],
                    'entidade_nome' => $dados['entidade_nome'],
                    'destinatario_email' => $dados['destinatario_email'],
                    'destinatario_nome' => $dados['destinatario_nome'],
                    'cc' => $dados['cc'],
                    'bcc' => $dados['bcc'],
                    'assunto' => $dados['assunto'],
                    'corpo_resumo' => AuxiliarEmail::resumirTexto($dados['corpo_html'] ?? $dados['corpo_texto'], 200),
                    'template' => $dados['template'],
                    'anexos_count' => $dados['anexos'] ? count(json_decode($dados['anexos'], true)) : 0,
                    'status' => 'enviado',
                    'status_code' => 2,
                    'smtp_response' => $resultado['smtp_response'],
                    'ip_origem' => $dados['ip_origem'],
                    'data_enviado' => date('Y-m-d H:i:s')
                ]);

                // Registra envio na entidade
                if ($dados['tipo_entidade'] && $dados['entidade_id']) {
                    $this->entidadeService->registrarEnvio($dados['tipo_entidade'], $dados['entidade_id']);
                }

                return [
                    'sucesso' => true,
                    'mensagem' => 'Email enviado com sucesso',
                    'modo' => 'direto',
                    'message_id' => $resultado['message_id'],
                    'tracking_code' => $dados['tracking_code'] ?? null,
                    'destinatario' => $dados['destinatario_email']
                ];
            } else {
                throw new \Exception($resultado['erro']);
            }

        } catch (\Exception $e) {
            // Registra erro no histórico
            $this->historicoModel->adicionar([
                'tracking_code' => $dados['tracking_code'] ?? null,
                'tipo_evento' => 'erro_envio',
                'tipo_entidade' => $dados['tipo_entidade'],
                'entidade_id' => $dados['entidade_id'],
                'destinatario_email' => $dados['destinatario_email'],
                'assunto' => $dados['assunto'],
                'status' => 'erro',
                'status_code' => 0,
                'erro_mensagem' => $e->getMessage()
            ]);

            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Processa fila de emails
     */
    public function processarFila(int $limit = null): array
    {
        $limit = $limit ?? $this->configModel->obter('cron_limite_mensagens', 20);

        // Busca emails pendentes
        $emails = $this->queueModel->buscarPendentes($limit);

        $processados = 0;
        $enviados = 0;
        $erros = 0;

        foreach ($emails as $email) {
            $processados++;

            // Marca como processando
            $this->queueModel->atualizarStatus($email['id'], 2); // processando

            $this->logToFile('=== PROCESSANDO EMAIL DA FILA ===', [
                'queue_id' => $email['id'],
                'destinatario' => $email['destinatario_email'],
                'assunto' => $email['assunto'],
                'tracking_code' => $email['tracking_code'] ?? 'NENHUM',
                'tem_corpo_html' => !empty($email['corpo_html']),
                'tem_corpo_texto' => !empty($email['corpo_texto'])
            ]);

            try {
                // Envia via SMTP
                $resultado = $this->getSMTP()->enviar($email);

                $this->logToFile('Resultado envio SMTP da fila', [
                    'queue_id' => $email['id'],
                    'sucesso' => $resultado['sucesso'],
                    'message_id' => $resultado['message_id'] ?? 'N/A'
                ]);

                if ($resultado['sucesso']) {
                    $enviados++;

                    // Atualiza status para enviado
                    $this->queueModel->atualizarStatus($email['id'], 3, null, $resultado['message_id']);

                    // Registra no histórico
                    $this->historicoModel->adicionar([
                        'message_id' => $resultado['message_id'],
                        'tracking_code' => $email['tracking_code'],
                        'tipo_evento' => 'enviado',
                        'tipo_entidade' => $email['tipo_entidade'],
                        'entidade_id' => $email['entidade_id'],
                        'entidade_nome' => $email['entidade_nome'],
                        'destinatario_email' => $email['destinatario_email'],
                        'destinatario_nome' => $email['destinatario_nome'],
                        'cc' => $email['cc'],
                        'bcc' => $email['bcc'],
                        'assunto' => $email['assunto'],
                        'corpo_resumo' => AuxiliarEmail::resumirTexto($email['corpo_html'] ?? $email['corpo_texto'], 200),
                        'template' => $email['template'],
                        'anexos_count' => $email['anexos'] ? count(json_decode($email['anexos'], true)) : 0,
                        'status' => 'enviado',
                        'status_code' => 2,
                        'smtp_response' => $resultado['smtp_response'],
                        'data_enviado' => date('Y-m-d H:i:s')
                    ]);

                    // Registra envio na entidade
                    if ($email['tipo_entidade'] && $email['entidade_id']) {
                        $this->entidadeService->registrarEnvio($email['tipo_entidade'], $email['entidade_id']);
                    }

                    // Remove da fila após sucesso
                    $this->queueModel->deletar($email['id']);

                } else {
                    throw new \Exception($resultado['erro']);
                }

            } catch (\Exception $e) {
                $erros++;

                // Incrementa tentativas
                $this->queueModel->incrementarTentativas($email['id']);

                // Verifica se atingiu máximo de tentativas
                $email = $this->queueModel->buscarPorId($email['id']);

                if ($email['tentativas'] >= $email['max_tentativas']) {
                    // Move para histórico como erro definitivo
                    $this->historicoModel->adicionar([
                        'tracking_code' => $email['tracking_code'],
                        'tipo_evento' => 'erro_envio',
                        'tipo_entidade' => $email['tipo_entidade'],
                        'entidade_id' => $email['entidade_id'],
                        'destinatario_email' => $email['destinatario_email'],
                        'assunto' => $email['assunto'],
                        'status' => 'erro',
                        'status_code' => 0,
                        'erro_mensagem' => $e->getMessage(),
                        'smtp_response' => $email['smtp_response']
                    ]);

                    // Remove da fila
                    $this->queueModel->deletar($email['id']);
                } else {
                    // Volta para pendente para retry
                    $this->queueModel->atualizarStatus($email['id'], 1, $e->getMessage());
                }

                $this->logModel->adicionar([
                    'tipo' => 'envio_erro',
                    'nivel' => 'error',
                    'mensagem' => 'Erro ao enviar email da fila: ' . $e->getMessage(),
                    'contexto' => json_encode(['queue_id' => $email['id'], 'tentativas' => $email['tentativas']]),
                    'queue_id' => $email['id']
                ]);
            }

            // Delay entre envios (anti-spam)
            $intervalo = $this->configModel->obter('fila_intervalo_entre_envios', 2);
            if ($intervalo > 0 && $processados < count($emails)) {
                sleep($intervalo);
            }
        }

        return [
            'processados' => $processados,
            'enviados' => $enviados,
            'erros' => $erros
        ];
    }

    /**
     * Obtém estatísticas do sistema
     */
    public function obterEstatisticas(): array
    {
        return [
            'fila' => [
                'pendentes' => $this->queueModel->contarPendentes(),
                'processando' => $this->queueModel->contarProcessando(),
                'enviados_hoje' => $this->queueModel->contarEnviadosHoje(),
                'com_erro' => $this->queueModel->contarComErro()
            ],
            'historico_24h' => [
                'enviados' => $this->historicoModel->contarPorStatusUltimas24h(2),
                'bounces' => $this->historicoModel->contarBouncesUltimas24h(),
                'abertos' => $this->historicoModel->contarAbertosUltimas24h(),
                'clicados' => $this->historicoModel->contarCliquesUltimas24h(),
                'erros' => $this->historicoModel->contarErrosUltimas24h()
            ],
            'smtp' => $this->getSMTP()->obterInformacoes()
        ];
    }

    /**
     * Converte texto simples para HTML estruturado completo
     */
    private function converterTextoParaHtmlEstruturado(string $texto): string
    {
        // Escapa HTML e converte quebras de linha
        $textoFormatado = nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
        {$textoFormatado}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Envolve HTML parcial em estrutura HTML completa
     */
    private function envolverEmHtmlEstruturado(string $htmlParcial, string $titulo = 'Email'): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo}</title>
</head>
<body>
{$htmlParcial}
</body>
</html>
HTML;
    }

    /**
     * Processa template de email
     */
    private function processarTemplate(array $dados): array
    {
        if (!$this->configModel->obter('templates_habilitados', true)) {
            return $dados;
        }

        $templateDir = $this->configModel->obter('templates_diretorio', 'App/Views/Email/');
        $templateFile = rtrim($templateDir, '/') . '/' . $dados['template'] . '.php';

        if (!file_exists($templateFile)) {
            throw new \Exception("Template não encontrado: {$dados['template']}");
        }

        // Extrai variáveis do template
        $vars = $dados['dados_template'] ? json_decode($dados['dados_template'], true) : [];

        // Renderiza template
        ob_start();
        extract($vars);
        include $templateFile;
        $html = ob_get_clean();

        $dados['corpo_html'] = $html;

        return $dados;
    }

    /**
     * Injeta pixel e links de tracking no HTML
     */
    private function injetarTracking(array $dados): array
    {
        $this->logToFile('>>> INÍCIO injetarTracking()', [
            'tem_corpo_html' => !empty($dados['corpo_html']),
            'tem_tracking_code' => !empty($dados['tracking_code']),
            'tracking_code' => $dados['tracking_code'] ?? 'VAZIO'
        ]);

        if (empty($dados['corpo_html']) || empty($dados['tracking_code'])) {
            $this->logToFile('ATENÇÃO: Tracking NÃO injetado - corpo_html ou tracking_code vazio', [
                'corpo_html_vazio' => empty($dados['corpo_html']),
                'tracking_code_vazio' => empty($dados['tracking_code'])
            ]);
            return $dados;
        }

        $html = $dados['corpo_html'];
        $trackingCode = $dados['tracking_code'];

        // Obtém URL base da API
        $apiBaseUrl = $this->obterUrlBaseApi();
        $this->logToFile('URL base API obtida', [
            'api_base_url' => $apiBaseUrl,
            'api_url_env' => $_ENV['API_URL'] ?? 'NÃO DEFINIDO'
        ]);

        // Injeta pixel de rastreamento (se habilitado)
        $pixelHabilitado = $this->configModel->obter('tracking_pixel_habilitado', true);
        $this->logToFile('Config tracking_pixel_habilitado', [
            'habilitado' => $pixelHabilitado ? 'SIM' : 'NÃO'
        ]);

        if ($pixelHabilitado) {
            $pixelUrl = "{$apiBaseUrl}/email/track/open/{$trackingCode}";
            $pixel = "<img src=\"{$pixelUrl}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none;\" />";

            $this->logToFile('Pixel criado', [
                'pixel_url' => $pixelUrl,
                'pixel_html' => $pixel
            ]);

            // Tenta injetar antes do fechamento do body
            if (stripos($html, '</body>') !== false) {
                $html = str_ireplace('</body>', $pixel . '</body>', $html);
                $this->logToFile('Pixel injetado antes de </body>');
            }
            // Se não tiver </body>, injeta no final do HTML
            else if (stripos($html, '</html>') !== false) {
                $html = str_ireplace('</html>', $pixel . '</html>', $html);
                $this->logToFile('Pixel injetado antes de </html>');
            }
            // Se não tiver nenhuma tag de fechamento, injeta no final
            else {
                $html .= $pixel;
                $this->logToFile('Pixel injetado no final do HTML (sem tags de fechamento)');
            }
        } else {
            $this->logToFile('Pixel NÃO injetado - tracking_pixel_habilitado está FALSE');
        }

        // Converte links para tracking (se habilitado)
        if ($this->configModel->obter('tracking_links_habilitado', true)) {
            // Encontra todos os links
            $html = preg_replace_callback(
                '/<a\s+href=["\']([^"\']+)["\']([^>]*)>/i',
                function($matches) use ($trackingCode, $apiBaseUrl) {
                    $originalUrl = $matches[1];

                    // Não rastreia links internos ou âncoras
                    if (strpos($originalUrl, '#') === 0 || strpos($originalUrl, 'mailto:') === 0) {
                        return $matches[0];
                    }

                    $trackingUrl = $apiBaseUrl . "/email/track/click/{$trackingCode}?url=" . urlencode($originalUrl);
                    return "<a href=\"{$trackingUrl}\"{$matches[2]}>";
                },
                $html
            );
        }

        $dados['corpo_html'] = $html;

        $this->logToFile('<<< FIM injetarTracking()', [
            'html_final_length' => strlen($html),
            'html_final_preview' => substr($html, 0, 300),
            'html_final_ultimos_500' => substr($html, -500)
        ]);

        // Log para debug (apenas em desenvolvimento)
        if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("[EMAIL DEBUG] Tracking injetado - Code: {$trackingCode}, URL Pixel: {$apiBaseUrl}/email/track/open/{$trackingCode}");
        }

        return $dados;
    }

    /**
     * Obtém URL base da API para tracking
     * Usa API_URL e detecta automaticamente o ambiente
     */
    private function obterUrlBaseApi(): string
    {
        $apiUrl = $_ENV['API_URL'] ?? 'http://localhost';
        $apiUrl = rtrim($apiUrl, '/');

        // Detecta ambiente
        // Desenvolvimento: localhost/127.0.0.1/porta customizada → /public_html/api
        // Produção: domínio real → /api
        if (
            strpos($apiUrl, 'localhost') !== false ||
            strpos($apiUrl, '127.0.0.1') !== false ||
            preg_match('/:\d{4,5}$/', $apiUrl) // Porta customizada (ex: :8080)
        ) {
            // Ambiente de desenvolvimento
            return $apiUrl . '/public_html/api';
        } else {
            // Ambiente de produção
            return $apiUrl . '/api';
        }
    }
}
