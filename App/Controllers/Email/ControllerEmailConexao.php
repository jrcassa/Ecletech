<?php

namespace App\Controllers\Email;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Email\ModelEmailSMTP;
use App\Models\Email\ModelEmailConfiguracao;

/**
 * Controller para gerenciar conexão SMTP
 */
class ControllerEmailConexao extends Controller
{
    private ModelEmailSMTP $smtp;
    private ModelEmailConfiguracao $config;

    public function __construct()
    {
        parent::__construct();
        $this->smtp = new ModelEmailSMTP();
        $this->config = new ModelEmailConfiguracao();
    }

    /**
     * GET /email/conexao/status
     * Retorna status da conexão SMTP
     */
    public function status(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar status', 403);
        }

        // Valida configurações
        $validacao = $this->smtp->validarConfiguracoes();

        $info = $this->smtp->obterInformacoes();

        return $this->sucesso([
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
    }

    /**
     * POST /email/conexao/testar
     * Testa conexão SMTP
     */
    public function testar(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.alterar')) {
            return $this->erro('Sem permissão para testar conexão', 403);
        }

        $resultado = $this->smtp->testarConexao();

        if ($resultado['sucesso']) {
            return $this->sucesso($resultado);
        } else {
            return $this->erro($resultado['mensagem'], 500, $resultado);
        }
    }

    /**
     * GET /email/conexao/info
     * Retorna informações da configuração SMTP
     */
    public function info(Request $request): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar informações', 403);
        }

        $info = $this->smtp->obterInformacoes();

        // Remove senha da resposta
        unset($info['senha']);

        return $this->sucesso($info);
    }
}
