<?php

namespace App\Controllers\S3;

use App\Controllers\BaseController;
use App\Models\S3\ModelS3Cliente;
use App\Models\S3\ModelS3Configuracao;

/**
 * Controller para verificar status e conexão do S3
 */
class ControllerS3Status extends BaseController
{
    private ModelS3Cliente $cliente;
    private ModelS3Configuracao $modelConfig;

    public function __construct()
    {
        $this->modelConfig = new ModelS3Configuracao();
    }

    /**
     * GET /s3/status
     * Verifica status geral do S3
     */
    public function status(): void
    {
        try {
            // Verifica se está configurado
            $configurado = $this->modelConfig->estaConfigurado();

            if (!$configurado) {
                $this->sucesso([
                    'configurado' => false,
                    'mensagem' => 'S3 não está configurado. Configure as credenciais primeiro.',
                    'faltantes' => $this->modelConfig->validarConfiguracoesObrigatorias()
                ]);
                return;
            }

            // Verifica se está habilitado
            $habilitado = (bool)$this->modelConfig->obter('aws_s3_status', 0);

            if (!$habilitado) {
                $this->sucesso([
                    'configurado' => true,
                    'habilitado' => false,
                    'mensagem' => 'S3 está configurado mas desabilitado'
                ]);
                return;
            }

            $this->sucesso([
                'configurado' => true,
                'habilitado' => true,
                'mensagem' => 'S3 está configurado e habilitado'
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao verificar status');
        }
    }

    /**
     * POST /s3/testar-conexao
     * Testa conexão real com S3
     */
    public function testarConexao(): void
    {
        try {
            // Verifica se está configurado primeiro
            if (!$this->modelConfig->estaConfigurado()) {
                $this->badRequest('S3 não está configurado');
                return;
            }

            // Inicializa cliente e testa conexão
            $this->cliente = new ModelS3Cliente();
            $resultado = $this->cliente->testarConexao();

            if ($resultado['sucesso']) {
                $this->sucesso([
                    'sucesso' => true,
                    'mensagem' => $resultado['mensagem'],
                    'bucket_padrao' => $this->cliente->obterBucketPadrao()
                ]);
            } else {
                $this->erro($resultado['mensagem'], 500, [
                    'codigo_erro' => $resultado['codigo_erro'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao testar conexão');
        }
    }

    /**
     * GET /s3/info
     * Obtém informações gerais do S3 configurado
     */
    public function info(): void
    {
        try {
            $configs = $this->modelConfig->obterTodas();

            // Oculta credenciais sensíveis
            if (isset($configs['aws_access_key_id']) && !empty($configs['aws_access_key_id'])) {
                $configs['aws_access_key_id'] = substr($configs['aws_access_key_id'], 0, 4) . '***';
            }

            if (isset($configs['aws_secret_access_key'])) {
                $configs['aws_secret_access_key'] = '***';
            }

            $this->sucesso([
                'configurado' => $this->modelConfig->estaConfigurado(),
                'configuracoes' => $configs
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter informações');
        }
    }

    /**
     * POST /s3/habilitar
     * Habilita o serviço S3
     */
    public function habilitar(): void
    {
        try {
            $sucesso = $this->modelConfig->salvar('aws_s3_status', '1');

            if ($sucesso) {
                $this->sucesso([
                    'mensagem' => 'S3 habilitado com sucesso'
                ]);
            } else {
                $this->erro('Erro ao habilitar S3', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao habilitar S3');
        }
    }

    /**
     * POST /s3/desabilitar
     * Desabilita o serviço S3
     */
    public function desabilitar(): void
    {
        try {
            $sucesso = $this->modelConfig->salvar('aws_s3_status', '0');

            if ($sucesso) {
                $this->sucesso([
                    'mensagem' => 'S3 desabilitado com sucesso'
                ]);
            } else {
                $this->erro('Erro ao desabilitar S3', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao desabilitar S3');
        }
    }

    /**
     * GET /s3/health
     * Health check completo do sistema S3
     */
    public function health(): void
    {
        try {
            $health = [
                'status' => 'ok',
                'timestamp' => date('Y-m-d H:i:s'),
                'checks' => []
            ];

            // Check 1: Configuração
            $configurado = $this->modelConfig->estaConfigurado();
            $health['checks']['configuracao'] = [
                'status' => $configurado ? 'ok' : 'erro',
                'mensagem' => $configurado ?
                    'Configurações OK' :
                    'Configurações incompletas'
            ];

            if (!$configurado) {
                $health['status'] = 'erro';
                $this->sucesso($health);
                return;
            }

            // Check 2: Habilitado
            $habilitado = (bool)$this->modelConfig->obter('aws_s3_status', 0);
            $health['checks']['habilitado'] = [
                'status' => $habilitado ? 'ok' : 'aviso',
                'mensagem' => $habilitado ?
                    'Serviço habilitado' :
                    'Serviço desabilitado'
            ];

            // Check 3: Conexão S3
            try {
                $this->cliente = new ModelS3Cliente();
                $testeConexao = $this->cliente->testarConexao();

                $health['checks']['conexao'] = [
                    'status' => $testeConexao['sucesso'] ? 'ok' : 'erro',
                    'mensagem' => $testeConexao['mensagem']
                ];

                if (!$testeConexao['sucesso']) {
                    $health['status'] = 'erro';
                }
            } catch (\Exception $e) {
                $health['checks']['conexao'] = [
                    'status' => 'erro',
                    'mensagem' => $e->getMessage()
                ];
                $health['status'] = 'erro';
            }

            // Status geral
            if ($health['status'] === 'ok' && !$habilitado) {
                $health['status'] = 'aviso';
            }

            $this->sucesso($health);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao verificar health');
        }
    }
}
