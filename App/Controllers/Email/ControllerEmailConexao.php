<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Models\Email\ModelEmailSMTP;
use App\Models\Email\ModelEmailConfiguracao;
use App\Services\ACL\ServiceACL;

/**
 * Controller para gerenciar conexão SMTP
 */
class ControllerEmailConexao extends BaseController
{
    private ModelEmailSMTP $smtp;
    private ModelEmailConfiguracao $config;
    private ServiceACL $acl;

    public function __construct()
    {
        $this->smtp = new ModelEmailSMTP();
        $this->config = new ModelEmailConfiguracao();
        $this->acl = new ServiceACL();
    }

    /**
     * GET /email/conexao/status
     * Retorna status da conexão SMTP
     */
    public function status(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar status');
                return;
            }

            // Valida configurações
            $validacao = $this->smtp->validarConfiguracoes();

            $info = $this->smtp->obterInformacoes();

            $this->sucesso([
                'configurado' => $validacao['valido'],
                'erros' => $validacao['erros'],
                'info' => [
                    'host' => $info['host'],
                    'port' => $info['port'],
                    'secure' => $info['secure'],
                    'from_email' => $info['from_email'],
                    'from_name' => $info['from_name']
                ]
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao verificar status');
        }
    }

    /**
     * POST /email/conexao/testar
     * Testa conexão SMTP
     */
    public function testar(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.alterar')) {
                $this->proibido('Sem permissão para testar conexão');
                return;
            }

            $resultado = $this->smtp->testarConexao();

            if ($resultado['sucesso']) {
                $this->sucesso($resultado, 'Conexão testada com sucesso');
            } else {
                $this->erro($resultado['mensagem'], 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao testar conexão');
        }
    }

    /**
     * GET /email/conexao/info
     * Retorna informações da configuração SMTP
     */
    public function info(): void
    {
        try {
            // Valida permissão
            if (!$this->acl->temPermissao('email.acessar')) {
                $this->proibido('Sem permissão para acessar informações');
                return;
            }

            $info = $this->smtp->obterInformacoes();

            // Remove senha da resposta
            unset($info['senha']);

            $this->sucesso($info);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter informações');
        }
    }
}
