<?php

namespace App\Controllers\S3;

use App\Controllers\BaseController;
use App\Models\S3\ModelS3Configuracao;

/**
 * Controller para gerenciar configurações do S3
 */
class ControllerS3Configuracao extends BaseController
{
    private ModelS3Configuracao $model;

    public function __construct()
    {
        $this->model = new ModelS3Configuracao();
    }

    /**
     * GET /s3/config
     * Lista todas as configurações
     */
    public function listar(): void
    {
        try {
            $categoria = $this->obterParametro('categoria');

            if ($categoria) {
                $configs = $this->model->buscarPorCategoria($categoria);
            } else {
                $configs = $this->model->buscarTodas();
            }

            // Oculta valores de senha para exibição
            foreach ($configs as &$config) {
                if ($config['tipo'] === 'senha' && !empty($config['valor'])) {
                    $config['valor_mascarado'] = str_repeat('*', 12);
                    unset($config['valor']);
                }
            }

            $this->sucesso($configs);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar configurações');
        }
    }

    /**
     * GET /s3/config/{chave}
     * Obtém configuração específica
     */
    public function obter(string $chave): void
    {
        try {
            $config = $this->model->buscarPorChave($chave);

            if (!$config) {
                $this->naoEncontrado('Configuração não encontrada');
                return;
            }

            // Oculta valores de senha
            if ($config['tipo'] === 'senha' && !empty($config['valor'])) {
                $config['valor_mascarado'] = str_repeat('*', 12);
                unset($config['valor']);
            }

            $this->sucesso($config);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter configuração');
        }
    }

    /**
     * POST /s3/config/salvar
     * Salva ou atualiza uma configuração
     */
    public function salvar(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['chave'])) {
                $this->badRequest('Chave da configuração é obrigatória');
                return;
            }

            if (!isset($dados['valor'])) {
                $this->badRequest('Valor da configuração é obrigatório');
                return;
            }

            $sucesso = $this->model->salvar($dados['chave'], $dados['valor']);

            if ($sucesso) {
                $this->sucesso([
                    'mensagem' => 'Configuração salva com sucesso',
                    'chave' => $dados['chave']
                ]);
            } else {
                $this->erro('Erro ao salvar configuração', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao salvar configuração');
        }
    }

    /**
     * POST /s3/config/salvar-lote
     * Salva múltiplas configurações de uma vez
     */
    public function salvarLote(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['configuracoes']) || !is_array($dados['configuracoes'])) {
                $this->badRequest('Configurações inválidas');
                return;
            }

            $sucesso = $this->model->salvarLote($dados['configuracoes']);

            if ($sucesso) {
                $this->sucesso([
                    'mensagem' => 'Configurações salvas com sucesso',
                    'total' => count($dados['configuracoes'])
                ]);
            } else {
                $this->erro('Erro ao salvar configurações', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao salvar configurações');
        }
    }

    /**
     * GET /s3/config/validar
     * Verifica se configurações obrigatórias estão preenchidas
     */
    public function validar(): void
    {
        try {
            $faltantes = $this->model->validarConfiguracoesObrigatorias();

            $this->sucesso([
                'configurado' => empty($faltantes),
                'faltantes' => $faltantes,
                'mensagem' => empty($faltantes) ?
                    'Todas as configurações obrigatórias estão preenchidas' :
                    'Existem configurações obrigatórias pendentes'
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao validar configurações');
        }
    }

    /**
     * POST /s3/config/limpar-cache
     * Limpa cache de configurações
     */
    public function limparCache(): void
    {
        try {
            $this->model->limparCache();

            $this->sucesso([
                'mensagem' => 'Cache de configurações limpo com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao limpar cache');
        }
    }
}
