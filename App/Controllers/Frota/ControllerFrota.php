<?php

namespace App\Controllers\Frota;

use App\Models\Frota\ModelFrota;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar a frota de veículos
 */
class ControllerFrota
{
    private ModelFrota $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelFrota();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os veículos da frota
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'tipo' => $_GET['tipo'] ?? null,
                'status' => $_GET['status'] ?? null,
                'marca' => $_GET['marca'] ?? null,
                'modelo' => $_GET['modelo'] ?? null,
                'busca' => $_GET['busca'] ?? null,
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
            $veiculos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $veiculos,
                $total,
                $paginaAtual,
                $porPagina,
                'Veículos da frota listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um veículo por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);

            if (!$veiculo) {
                AuxiliarResposta::naoEncontrado('Veículo não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($veiculo, 'Veículo encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo veículo na frota
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:100',
                'tipo' => 'obrigatorio|em:motocicleta,automovel,caminhonete,caminhao,onibus,van',
                'placa' => 'obrigatorio|placa',
                'status' => 'em:ativo,inativo,manutencao,reservado,vendido'
            ]);

            // Validações opcionais se os campos estiverem presentes
            if (isset($dados['chassi']) && !empty($dados['chassi'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['chassi' => 'chassi']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['renavam']) && !empty($dados['renavam'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['renavam' => 'renavam']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['ano_fabricacao']) && !empty($dados['ano_fabricacao'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['ano_fabricacao' => 'inteiro']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['ano_modelo']) && !empty($dados['ano_modelo'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['ano_modelo' => 'inteiro']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['quilometragem']) && !empty($dados['quilometragem'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['quilometragem' => 'inteiro']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['data_aquisicao']) && !empty($dados['data_aquisicao'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['data_aquisicao' => 'data']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Normaliza a placa
            $dados['placa'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $dados['placa']));

            // Verifica se a placa já existe
            if ($this->model->placaExiste($dados['placa'])) {
                AuxiliarResposta::conflito('Placa já cadastrada no sistema');
                return;
            }

            // Verifica se o chassi já existe
            if (isset($dados['chassi']) && !empty($dados['chassi'])) {
                $dados['chassi'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $dados['chassi']));
                if ($this->model->chassiExiste($dados['chassi'])) {
                    AuxiliarResposta::conflito('Chassi já cadastrado no sistema');
                    return;
                }
            }

            // Verifica se o RENAVAM já existe
            if (isset($dados['renavam']) && !empty($dados['renavam'])) {
                $dados['renavam'] = preg_replace('/[^0-9]/', '', $dados['renavam']);
                if ($this->model->renavamExiste($dados['renavam'])) {
                    AuxiliarResposta::conflito('RENAVAM já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['usuario_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o veículo
            $id = $this->model->criar($dados);

            $veiculo = $this->model->buscarPorId($id);

            AuxiliarResposta::criado($veiculo, 'Veículo cadastrado na frota com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um veículo da frota
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o veículo existe
            $veiculoExistente = $this->model->buscarPorId((int) $id);
            if (!$veiculoExistente) {
                AuxiliarResposta::naoEncontrado('Veículo não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:100';
            }

            if (isset($dados['tipo'])) {
                $regras['tipo'] = 'obrigatorio|em:motocicleta,automovel,caminhonete,caminhao,onibus,van';
            }

            if (isset($dados['placa'])) {
                $regras['placa'] = 'obrigatorio|placa';
            }

            if (isset($dados['status'])) {
                $regras['status'] = 'em:ativo,inativo,manutencao,reservado,vendido';
            }

            if (isset($dados['chassi']) && !empty($dados['chassi'])) {
                $regras['chassi'] = 'chassi';
            }

            if (isset($dados['renavam']) && !empty($dados['renavam'])) {
                $regras['renavam'] = 'renavam';
            }

            if (isset($dados['ano_fabricacao'])) {
                $regras['ano_fabricacao'] = 'inteiro';
            }

            if (isset($dados['ano_modelo'])) {
                $regras['ano_modelo'] = 'inteiro';
            }

            if (isset($dados['quilometragem'])) {
                $regras['quilometragem'] = 'inteiro';
            }

            if (isset($dados['data_aquisicao'])) {
                $regras['data_aquisicao'] = 'data';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica se a placa já existe (excluindo o próprio veículo)
            if (isset($dados['placa'])) {
                $dados['placa'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $dados['placa']));
                if ($this->model->placaExiste($dados['placa'], (int) $id)) {
                    AuxiliarResposta::conflito('Placa já cadastrada em outro veículo');
                    return;
                }
            }

            // Verifica se o chassi já existe (excluindo o próprio veículo)
            if (isset($dados['chassi']) && !empty($dados['chassi'])) {
                $dados['chassi'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $dados['chassi']));
                if ($this->model->chassiExiste($dados['chassi'], (int) $id)) {
                    AuxiliarResposta::conflito('Chassi já cadastrado em outro veículo');
                    return;
                }
            }

            // Verifica se o RENAVAM já existe (excluindo o próprio veículo)
            if (isset($dados['renavam']) && !empty($dados['renavam'])) {
                $dados['renavam'] = preg_replace('/[^0-9]/', '', $dados['renavam']);
                if ($this->model->renavamExiste($dados['renavam'], (int) $id)) {
                    AuxiliarResposta::conflito('RENAVAM já cadastrado em outro veículo');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o veículo
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar veículo', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($veiculo, 'Veículo atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um veículo da frota (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o veículo existe
            $veiculo = $this->model->buscarPorId((int) $id);
            if (!$veiculo) {
                AuxiliarResposta::naoEncontrado('Veículo não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o veículo
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar veículo', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Veículo removido da frota com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza a quilometragem de um veículo
     */
    public function atualizarQuilometragem(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);
            if (!$veiculo) {
                AuxiliarResposta::naoEncontrado('Veículo não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'quilometragem' => 'obrigatorio|inteiro'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            $resultado = $this->model->atualizarQuilometragem(
                (int) $id,
                (int) $dados['quilometragem'],
                $usuarioId
            );

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar quilometragem', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($veiculo, 'Quilometragem atualizada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza o status de um veículo
     */
    public function atualizarStatus(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);
            if (!$veiculo) {
                AuxiliarResposta::naoEncontrado('Veículo não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'status' => 'obrigatorio|em:ativo,inativo,manutencao,reservado,vendido'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            $resultado = $this->model->atualizarStatus((int) $id, $dados['status'], $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar status', 400);
                return;
            }

            $veiculo = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($veiculo, 'Status do veículo atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas da frota
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas da frota obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
