<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Models\Email\ModelEmailConfiguracao;
use App\Services\Email\ServiceEmailEntidade;

/**
 * Controller para gerenciar configurações de email
 */
class ControllerEmailConfiguracao extends BaseController
{
    private ModelEmailConfiguracao $model;
    private ServiceEmailEntidade $entidadeService;

    public function __construct()
    {
        $this->model = new ModelEmailConfiguracao();
        $this->entidadeService = new ServiceEmailEntidade();
    }

    /**
     * GET /email/config
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

            $this->sucesso($configs);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar configurações');
        }
    }

    /**
     * GET /email/config/{chave}
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

            $this->sucesso($config);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter configuração');
        }
    }

    /**
     * POST /email/config/salvar
     * Salva uma configuração
     */
    public function salvar(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['chave'])) {
                $this->erro('Chave da configuração é obrigatória');
                return;
            }

            if (!isset($dados['valor'])) {
                $this->erro('Valor da configuração é obrigatório');
                return;
            }

            $sucesso = $this->model->salvar($dados['chave'], $dados['valor']);

            if ($sucesso) {
                $this->sucesso(['mensagem' => 'Configuração salva com sucesso']);
            } else {
                $this->erro('Erro ao salvar configuração');
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao salvar configuração');
        }
    }

    /**
     * POST /email/config/sincronizar-entidade
     * Sincroniza uma entidade
     */
    public function sincronizarEntidade(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['tipo']) || empty($dados['id'])) {
                $this->erro('Tipo e ID da entidade são obrigatórios');
                return;
            }

            $resultado = $this->entidadeService->sincronizarEntidade($dados['tipo'], (int) $dados['id']);
            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao sincronizar entidade');
        }
    }

    /**
     * POST /email/config/sincronizar-lote
     * Sincroniza entidades em lote
     */
    public function sincronizarLote(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['tipo'])) {
                $this->erro('Tipo de entidade é obrigatório');
                return;
            }

            $limit = $dados['limit'] ?? 100;

            $resultado = $this->entidadeService->sincronizarLote($dados['tipo'], $limit);
            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao sincronizar lote');
        }
    }

    /**
     * GET /email/config/categorias
     * Lista categorias disponíveis
     */
    public function categorias(): void
    {
        try {
            $categorias = $this->model->listarCategorias();

            $this->sucesso([
                'categorias' => $categorias,
                'total' => count($categorias)
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar categorias');
        }
    }
}
