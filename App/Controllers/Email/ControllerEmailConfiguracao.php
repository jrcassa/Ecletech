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
            return $this->erro('Sem permissão para acessar configurações', 403);
        }

        $categoria = $request->get('categoria');

        if ($categoria) {
            $configs = $this->model->buscarPorCategoria($categoria);
        } else {
            $configs = $this->model->buscarTodas();
        }

        return $this->sucesso([
            'configuracoes' => $configs,
            'total' => count($configs)
        ]);
    }

    /**
     * GET /email/config/{chave}
     * Obtém configuração específica
     */
    public function obter(): void
    {
            return $this->erro('Sem permissão para acessar configurações', 403);
        }

        $chave = $params['chave'];

        $config = $this->model->buscarPorChave($chave);

        if (!$config) {
            return $this->erro('Configuração não encontrada', 404);
        }

        return $this->sucesso($config);
    }

    /**
     * POST /email/config/salvar
     * Salva uma configuração
     */
    public function salvar(): void
    {
            return $this->erro('Sem permissão para alterar configurações', 403);
        }

        $dados = $request->getBody();

        if (empty($dados['chave'])) {
            return $this->erro('Chave da configuração é obrigatória');
        }

        if (!isset($dados['valor'])) {
            return $this->erro('Valor da configuração é obrigatório');
        }

        $sucesso = $this->model->salvar($dados['chave'], $dados['valor']);

        if ($sucesso) {
            return $this->sucesso(['mensagem' => 'Configuração salva com sucesso']);
        } else {
            return $this->erro('Erro ao salvar configuração');
        }
    }

    /**
     * POST /email/config/sincronizar-entidade
     * Sincroniza uma entidade
     */
    public function sincronizarEntidade(): void
    {
            return $this->erro('Sem permissão para sincronizar entidades', 403);
        }

        $dados = $request->getBody();

        if (empty($dados['tipo']) || empty($dados['id'])) {
            return $this->erro('Tipo e ID da entidade são obrigatórios');
        }

        try {
            $resultado = $this->entidadeService->sincronizarEntidade($dados['tipo'], (int) $dados['id']);
            return $this->sucesso($resultado);
        } catch (\Exception $e) {
            return $this->erro($e->getMessage());
        }
    }

    /**
     * POST /email/config/sincronizar-lote
     * Sincroniza entidades em lote
     */
    public function sincronizarLote(): void
    {
            return $this->erro('Sem permissão para sincronizar entidades', 403);
        }

        $dados = $request->getBody();

        if (empty($dados['tipo'])) {
            return $this->erro('Tipo de entidade é obrigatório');
        }

        $limit = $dados['limit'] ?? 100;

        try {
            $resultado = $this->entidadeService->sincronizarLote($dados['tipo'], $limit);
            return $this->sucesso($resultado);
        } catch (\Exception $e) {
            return $this->erro($e->getMessage());
        }
    }

    /**
     * GET /email/config/categorias
     * Lista categorias disponíveis
     */
    public function categorias(): void
    {
            return $this->erro('Sem permissão para acessar configurações', 403);
        }

        $categorias = $this->model->listarCategorias();

        return $this->sucesso([
            'categorias' => $categorias,
            'total' => count($categorias)
        ]);
    }
}
