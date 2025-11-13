<?php

namespace App\Controllers\Permissao;

use App\Controllers\BaseController;

use App\Models\Colaborador\ModelColaboradorPermission;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Middleware\MiddlewareAcl;

/**
 * Controlador para gerenciar permissões
 */
class ControllerPermissao extends BaseController
{
    private ModelColaboradorPermission $model;

    public function __construct()
    {
        $this->model = new ModelColaboradorPermission();
    }

    /**
     * Lista todas as permissões
     */
    public function listar(): void
    {
        try {
            $filtros = [];

            if (isset($_GET['modulo'])) {
                $filtros['modulo'] = $_GET['modulo'];
            }

            if (isset($_GET['ativo'])) {
                $filtros['ativo'] = (int) $_GET['ativo'];
            }

            if (isset($_GET['busca'])) {
                $filtros['busca'] = $_GET['busca'];
            }

            $permissoes = $this->model->listar($filtros);

            $this->sucesso($permissoes);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Busca uma permissão por ID
     */
    public function buscar(int $id): void
    {
        try {
            $permissao = $this->model->buscarPorId($id);

            if (!$permissao) {
                $this->naoEncontrado('Permissão não encontrada');
                return;
            }

            $this->sucesso($permissao);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Lista permissões agrupadas por módulo
     */
    public function listarPorModulo(): void
    {
        try {
            $permissoes = $this->model->listar(['ativo' => 1]);
            $agrupadas = [];

            foreach ($permissoes as $permissao) {
                $modulo = $permissao['modulo'] ?? 'geral';
                if (!isset($agrupadas[$modulo])) {
                    $agrupadas[$modulo] = [];
                }
                $agrupadas[$modulo][] = $permissao;
            }

            $this->sucesso($agrupadas);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Cria uma nova permissão
     */
    public function criar(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['obrigatorio', 'string', 'max:100'],
                'codigo' => ['obrigatorio', 'string', 'max:100'],
                'descricao' => ['string'],
                'modulo' => ['string', 'max:50'],
                'ativo' => ['inteiro']
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::erroValidacao('Dados inválidos', $erros);
                return;
            }

            // Verifica se o código já existe
            if ($this->model->codigoExiste($dados['codigo'])) {
                AuxiliarResposta::erroValidacao('Código já cadastrado', [
                    'codigo' => 'Este código já está em uso'
                ]);
                return;
            }

            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $id = $this->model->criar($dados, $usuarioId);

            $this->sucesso([
                'id' => $id,
                'mensagem' => 'Permissão criada com sucesso'
            ], 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Atualiza uma permissão
     */
    public function atualizar(int $id): void
    {
        try {
            $permissao = $this->model->buscarPorId($id);

            if (!$permissao) {
                $this->naoEncontrado('Permissão não encontrada');
                return;
            }

            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['string', 'max:100'],
                'codigo' => ['string', 'max:100'],
                'descricao' => ['string'],
                'modulo' => ['string', 'max:50'],
                'ativo' => ['inteiro']
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::erroValidacao('Dados inválidos', $erros);
                return;
            }

            // Verifica se o código já existe (excluindo o próprio registro)
            if (isset($dados['codigo']) && $this->model->codigoExiste($dados['codigo'], $id)) {
                AuxiliarResposta::erroValidacao('Código já cadastrado', [
                    'codigo' => 'Este código já está em uso'
                ]);
                return;
            }

            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $this->model->atualizar($id, $dados, $usuarioId);

            $this->sucesso([
                'mensagem' => 'Permissão atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Deleta uma permissão (soft delete)
     */
    public function deletar(int $id): void
    {
        try {
            $permissao = $this->model->buscarPorId($id);

            if (!$permissao) {
                $this->naoEncontrado('Permissão não encontrada');
                return;
            }

            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $sucesso = $this->model->deletar($id, $usuarioId);

            if ($sucesso) {
                $this->sucesso([
                    'mensagem' => 'Permissão deletada com sucesso'
                ]);
            } else {
                $this->erro('Não foi possível deletar a permissão', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Obtém as permissões do usuário autenticado
     */
    public function obterPermissoesUsuario(): void
    {
        try {
            $middleware = new MiddlewareAcl();
            $permissoes = $middleware->obterPermissoesUsuarioAtual();

            $this->sucesso([
                'permissoes' => $permissoes
            ]);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }
}
