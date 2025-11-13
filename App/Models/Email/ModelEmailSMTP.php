<?php

namespace App\Models\Email;

use App\Core\BancoDados;
use App\Helpers\ErrorLogger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Model para integração com PHPMailer
 * Gerencia envio de emails via SMTP
 * Padrão: Similar ao ModelWhatsappBaileys
 */
class ModelEmailSMTP
{
    private PHPMailer $mailer;
    private ModelEmailConfiguracao $config;

    public function __construct()
    {
        $this->config = new ModelEmailConfiguracao();
        $this->inicializarMailer();
    }

    /**
     * Inicializa PHPMailer com configurações
     */
    private function inicializarMailer(): void
    {
        $this->mailer = new PHPMailer(true); // true = throw exceptions

        try {
            // Configurações SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config->obter('smtp_host', '');
            $this->mailer->Port = $this->config->obter('smtp_port', 587);
            $this->mailer->SMTPSecure = $this->config->obter('smtp_secure', 'tls');
            $this->mailer->SMTPAuth = $this->config->obter('smtp_auth', true);
            $this->mailer->Username = $this->config->obter('smtp_usuario', '');
            $this->mailer->Password = $this->config->obter('smtp_senha', '');
            $this->mailer->Timeout = $this->config->obter('smtp_timeout', 30);

            // Debug (0=off, 1=client, 2=server, 3=connection, 4=low-level)
            $debugLevel = $this->config->obter('smtp_debug', 0);
            $this->mailer->SMTPDebug = $debugLevel;

            // Charset e encoding
            $this->mailer->CharSet = $this->config->obter('charset', 'UTF-8');
            $this->mailer->Encoding = $this->config->obter('encoding', 'base64');

            // Remetente padrão
            $fromEmail = $this->config->obter('from_email', '');
            $fromName = $this->config->obter('from_name', 'Sistema');

            if (!empty($fromEmail)) {
                $this->mailer->setFrom($fromEmail, $fromName);
            }

            // Reply-to padrão (se configurado)
            $replyToEmail = $this->config->obter('reply_to_email', '');
            $replyToName = $this->config->obter('reply_to_name', '');

            if (!empty($replyToEmail)) {
                $this->mailer->addReplyTo($replyToEmail, $replyToName);
            }

        } catch (Exception $e) {
            // Log error (silent failure)
            ErrorLogger::log($e, 'email', 'alto', [
                'contexto' => 'inicializacao_phpmailer',
                'smtp_host' => $this->config->obter('smtp_host', ''),
                'smtp_port' => $this->config->obter('smtp_port', 587)
            ]);
            error_log('Erro ao inicializar PHPMailer: ' . $e->getMessage());
        }
    }

    /**
     * Testa conexão SMTP
     */
    public function testarConexao(): array
    {
        try {
            // Conecta ao servidor SMTP
            $this->mailer->smtpConnect();

            // Se chegou aqui, conectou com sucesso
            return [
                'sucesso' => true,
                'mensagem' => 'Conexão SMTP estabelecida com sucesso',
                'servidor' => $this->config->obter('smtp_host'),
                'porta' => $this->config->obter('smtp_port'),
                'secure' => $this->config->obter('smtp_secure')
            ];

        } catch (Exception $e) {
            ErrorLogger::log($e, 'email', 'alto', [
                'contexto' => 'teste_conexao_smtp',
                'servidor' => $this->config->obter('smtp_host'),
                'porta' => $this->config->obter('smtp_port')
            ]);
            return [
                'sucesso' => false,
                'mensagem' => 'Falha na conexão SMTP',
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia email
     */
    public function enviar(array $dados): array
    {
        try {
            // Reinicializa mailer para limpar dados anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();
            $this->mailer->clearReplyTos();
            $this->mailer->clearCustomHeaders();

            // Remetente (pode ser sobrescrito)
            if (!empty($dados['from_email'])) {
                $this->mailer->setFrom(
                    $dados['from_email'],
                    $dados['from_name'] ?? $this->config->obter('from_name')
                );
            }

            // Destinatário principal (obrigatório)
            if (empty($dados['destinatario_email'])) {
                throw new Exception('Email do destinatário é obrigatório');
            }

            $this->mailer->addAddress(
                $dados['destinatario_email'],
                $dados['destinatario_nome'] ?? ''
            );

            // CC (se houver)
            if (!empty($dados['cc'])) {
                $ccList = is_string($dados['cc']) ? json_decode($dados['cc'], true) : $dados['cc'];
                if (is_array($ccList)) {
                    foreach ($ccList as $cc) {
                        if (is_array($cc)) {
                            $this->mailer->addCC($cc['email'], $cc['nome'] ?? '');
                        } else {
                            $this->mailer->addCC($cc);
                        }
                    }
                }
            }

            // BCC (se houver)
            if (!empty($dados['bcc'])) {
                $bccList = is_string($dados['bcc']) ? json_decode($dados['bcc'], true) : $dados['bcc'];
                if (is_array($bccList)) {
                    foreach ($bccList as $bcc) {
                        if (is_array($bcc)) {
                            $this->mailer->addBCC($bcc['email'], $bcc['nome'] ?? '');
                        } else {
                            $this->mailer->addBCC($bcc);
                        }
                    }
                }
            }

            // Reply-To customizado (se fornecido)
            if (!empty($dados['reply_to'])) {
                $this->mailer->addReplyTo($dados['reply_to'], $dados['reply_to_name'] ?? '');
            }

            // Assunto (obrigatório)
            if (empty($dados['assunto'])) {
                throw new Exception('Assunto do email é obrigatório');
            }
            $this->mailer->Subject = $dados['assunto'];

            // Corpo do email
            $htmlHabilitado = $this->config->obter('html_habilitado', true);

            if ($htmlHabilitado && !empty($dados['corpo_html'])) {
                // Email HTML
                $this->mailer->isHTML(true);
                $this->mailer->Body = $dados['corpo_html'];
                $this->mailer->AltBody = $dados['corpo_texto'] ?? strip_tags($dados['corpo_html']);
            } elseif (!empty($dados['corpo_texto'])) {
                // Email texto plano
                $this->mailer->isHTML(false);
                $this->mailer->Body = $dados['corpo_texto'];
            } else {
                throw new Exception('Corpo do email (HTML ou texto) é obrigatório');
            }

            // Anexos (se houver)
            if (!empty($dados['anexos'])) {
                $anexosList = is_string($dados['anexos']) ? json_decode($dados['anexos'], true) : $dados['anexos'];

                if (is_array($anexosList)) {
                    foreach ($anexosList as $anexo) {
                        if (is_array($anexo) && !empty($anexo['caminho'])) {
                            $this->mailer->addAttachment(
                                $anexo['caminho'],
                                $anexo['nome'] ?? basename($anexo['caminho'])
                            );
                        }
                    }
                }
            }

            // Headers customizados para tracking
            if (!empty($dados['tracking_code'])) {
                $this->mailer->addCustomHeader('X-Tracking-Code', $dados['tracking_code']);
            }

            // Envia o email
            $enviado = $this->mailer->send();

            if ($enviado) {
                $messageId = $this->mailer->getLastMessageID();

                return [
                    'sucesso' => true,
                    'message_id' => $messageId,
                    'destinatario' => $dados['destinatario_email'],
                    'smtp_response' => 'Email enviado com sucesso'
                ];
            } else {
                throw new Exception('Falha ao enviar email (resposta negativa do servidor)');
            }

        } catch (Exception $e) {
            ErrorLogger::log($e, 'email', 'alto', [
                'contexto' => 'envio_email',
                'destinatario' => $dados['destinatario_email'] ?? null,
                'assunto' => $dados['assunto'] ?? null,
                'smtp_error' => $this->mailer->ErrorInfo
            ]);
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'smtp_error' => $this->mailer->ErrorInfo,
                'destinatario' => $dados['destinatario_email'] ?? null
            ];
        }
    }

    /**
     * Obtém informações da configuração SMTP atual
     */
    public function obterInformacoes(): array
    {
        return [
            'host' => $this->config->obter('smtp_host'),
            'port' => $this->config->obter('smtp_port'),
            'secure' => $this->config->obter('smtp_secure'),
            'auth' => $this->config->obter('smtp_auth'),
            'usuario' => $this->config->obter('smtp_usuario'),
            'from_email' => $this->config->obter('from_email'),
            'from_name' => $this->config->obter('from_name'),
            'charset' => $this->config->obter('charset'),
            'encoding' => $this->config->obter('encoding')
        ];
    }

    /**
     * Valida configurações SMTP
     */
    public function validarConfiguracoes(): array
    {
        $erros = [];

        if (empty($this->config->obter('smtp_host'))) {
            $erros[] = 'SMTP Host não configurado';
        }

        if (empty($this->config->obter('smtp_port'))) {
            $erros[] = 'SMTP Port não configurado';
        }

        if ($this->config->obter('smtp_auth') && empty($this->config->obter('smtp_usuario'))) {
            $erros[] = 'Autenticação SMTP habilitada mas usuário não configurado';
        }

        if ($this->config->obter('smtp_auth') && empty($this->config->obter('smtp_senha'))) {
            $erros[] = 'Autenticação SMTP habilitada mas senha não configurada';
        }

        if (empty($this->config->obter('from_email'))) {
            $erros[] = 'Email do remetente (from_email) não configurado';
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros
        ];
    }

    /**
     * Obtém última mensagem de erro
     */
    public function obterUltimoErro(): string
    {
        return $this->mailer->ErrorInfo;
    }
}
