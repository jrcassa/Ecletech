<?php

namespace App\Controllers\Nivel;

use App\Models\Colaborador\ModelColaboradorNivel;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controlador para gerenciar níveis de colaboradores
 */
class ControllerNivel
{
    private ModelColaboradorNivel $model;

    public function __construct()
    {
        $this->model = new ModelColaboradorNivel();
    }

    /**
     * Lista todos os níveis
     */
    public function listar(): void
    {
        try {
            $apenasAtivos = isset($_GET['ativo']) ? (bool) $_GET['ativo'] : false;
            $niveis = $this->model->listar($apenasAtivos);

            AuxiliarResposta::sucesso($niveis);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Busca um nível por ID
     */
    public function buscar(int $id): void
    {
        try {
            $nivel = $this->model->buscarPorId($id);

            if (!$nivel) {
                AuxiliarResposta::naoEncontrado('Nível não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($nivel);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Cria um novo nível
     */
    public function criar(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['obrigatorio', 'string', 'max:100'],
                'codigo' => ['obrigatorio', 'string', 'max:50'],
                'descricao' => ['string'],
                'ordem' => ['inteiro'],
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

            AuxiliarResposta::sucesso([
                'id' => $id,
                'mensagem' => 'Nível criado com sucesso'
            ], 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Atualiza um nível
     */
    public function atualizar(int $id): void
    {
        try {
            $nivel = $this->model->buscarPorId($id);

            if (!$nivel) {
                AuxiliarResposta::naoEncontrado('Nível não encontrado');
                return;
            }

            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['string', 'max:100'],
                'codigo' => ['string', 'max:50'],
                'descricao' => ['string'],
                'ordem' => ['inteiro'],
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

            AuxiliarResposta::sucesso([
                'mensagem' => 'Nível atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Deleta um nível (soft delete)
     */
    public function deletar(int $id): void
    {
        try {
            $nivel = $this->model->buscarPorId($id);

            if (!$nivel) {
                AuxiliarResposta::naoEncontrado('Nível não encontrado');
                return;
            }

            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $sucesso = $this->model->deletar($id, $usuarioId);

            if ($sucesso) {
                AuxiliarResposta::sucesso([
                    'mensagem' => 'Nível deletado com sucesso'
                ]);
            } else {
                AuxiliarResposta::erro('Não foi possível deletar o nível', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }
}
