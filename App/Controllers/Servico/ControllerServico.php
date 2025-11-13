<?php

namespace App\Controllers\Servico;

use App\Controllers\BaseController;
use App\Models\Servico\ModelServico;
use App\Services\Servico\ServiceServico;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar serviços
 */
class ControllerServico extends BaseController
{
    private ModelServico $model;
    private ServiceServico $service;

    public function __construct()
    {
        $this->model = new ModelServico();
        $this->service = new ServiceServico();
    }

    /**
     * Lista serviços com filtros e paginação
     */
    public function listar(): void
    {
        try {
            // Filtros
            $filtros = [
                'busca' => $_GET['busca'] ?? null,
                'ativo' => isset($_GET['ativo']) ? (bool) $_GET['ativo'] : null,
                'ordenacao' => $_GET['ordenacao'] ?? 'nome',
                'direcao' => $_GET['direcao'] ?? 'ASC'
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 20);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            // Busca dados
            $servicos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $servicos,
                $total,
                $paginaAtual,
                $porPagina,
                'Serviços listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista serviços ativos (para uso em selects)
     */
    public function listarAtivos(): void
    {
        try {
            $servicos = $this->model->listarAtivos();
            $this->sucesso($servicos, 'Serviços ativos listados com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca serviço por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $servico = $this->model->buscarPorId((int) $id);

            if (!$servico) {
                $this->naoEncontrado('Serviço não encontrado');
                return;
            }

            $this->sucesso($servico, 'Serviço encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria novo serviço
     */
    public function criar(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'codigo' => 'obrigatorio|max:100',
                'nome' => 'obrigatorio|max:255',
                'valor_venda' => 'opcional|decimal',
                'external_id' => 'opcional|max:50',
                'external_codigo' => 'opcional|max:100',
                'observacoes' => 'opcional',
                'ativo' => 'opcional|booleano'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Validações de negócio adicionais
            $errosNegocio = $this->service->validarDados($dados);
            if (!empty($errosNegocio)) {
                $this->validacao($errosNegocio);
                return;
            }

            // Adiciona criador
            $dados['criado_por'] = $usuarioLogado['id'];

            // Cria serviço via service
            $id = $this->service->criar($dados);

            $this->criado(['id' => $id], 'Serviço criado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza serviço
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Verifica se serviço existe
            $servico = $this->model->buscarPorId((int) $id);
            if (!$servico) {
                $this->naoEncontrado('Serviço não encontrado');
                return;
            }

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'codigo' => 'opcional|max:100',
                'nome' => 'opcional|max:255',
                'valor_venda' => 'opcional|decimal',
                'external_id' => 'opcional|max:50',
                'external_codigo' => 'opcional|max:100',
                'observacoes' => 'opcional',
                'ativo' => 'opcional|booleano'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Validações de negócio adicionais
            $errosNegocio = $this->service->validarDados($dados, true);
            if (!empty($errosNegocio)) {
                $this->validacao($errosNegocio);
                return;
            }

            // Adiciona atualizador
            $dados['atualizado_por'] = $usuarioLogado['id'];

            // Atualiza via service
            $this->service->atualizar((int) $id, $dados);

            $this->sucesso(['id' => (int) $id], 'Serviço atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Ativa/Desativa serviço
     */
    public function alterarStatus(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Verifica se serviço existe
            $servico = $this->model->buscarPorId((int) $id);
            if (!$servico) {
                $this->naoEncontrado('Serviço não encontrado');
                return;
            }

            // Validação
            if (!isset($dados['ativo'])) {
                $this->erro('Campo ativo é obrigatório');
                return;
            }

            $this->model->alterarStatus((int) $id, (bool) $dados['ativo']);

            $mensagem = $dados['ativo'] ? 'Serviço ativado com sucesso' : 'Serviço desativado com sucesso';
            $this->sucesso(['id' => (int) $id], $mensagem);
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta serviço (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $servico = $this->model->buscarPorId((int) $id);
            if (!$servico) {
                $this->naoEncontrado('Serviço não encontrado');
                return;
            }

            $this->service->deletar((int) $id);

            $this->sucesso([], 'Serviço deletado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Restaura serviço deletado
     */
    public function restaurar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $this->model->restaurar((int) $id);

            $this->sucesso(['id' => (int) $id], 'Serviço restaurado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas gerais
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();
            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Importa ou atualiza serviço de sistema externo
     */
    public function importar(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'external_id' => 'obrigatorio|max:50',
                'codigo' => 'obrigatorio|max:100',
                'nome' => 'obrigatorio|max:255',
                'valor_venda' => 'opcional|decimal',
                'external_codigo' => 'opcional|max:100',
                'observacoes' => 'opcional'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Adiciona criador
            $dados['criado_por'] = $usuarioLogado['id'];
            $dados['atualizado_por'] = $usuarioLogado['id'];

            // Importa via service
            $resultado = $this->service->importarOuAtualizar($dados);

            if ($resultado['acao'] === 'criado') {
                $this->criado($resultado, 'Serviço importado com sucesso');
            } else {
                $this->sucesso($resultado, 'Serviço atualizado com sucesso');
            }
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
