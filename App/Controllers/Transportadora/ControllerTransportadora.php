<?php

namespace App\Controllers\Transportadora;

use App\Models\Transportadora\ModelTransportadora;
use App\Models\Transportadora\ModelTransportadoraContato;
use App\Models\Transportadora\ModelTransportadoraEndereco;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar transportadoras
 */
class ControllerTransportadora
{
    private ModelTransportadora $model;
    private ModelTransportadoraContato $modelContato;
    private ModelTransportadoraEndereco $modelEndereco;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelTransportadora();
        $this->modelContato = new ModelTransportadoraContato();
        $this->modelEndereco = new ModelTransportadoraEndereco();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Lista todas as transportadoras
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'tipo_pessoa' => $_GET['tipo_pessoa'] ?? null,
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
            $transportadoras = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $transportadoras,
                $total,
                $paginaAtual,
                $porPagina,
                'Transportadoras listadas com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma transportadora por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $transportadora = $this->model->buscarComRelacionamentos((int) $id);

            if (!$transportadora) {
                AuxiliarResposta::naoEncontrado('Transportadora não encontrada');
                return;
            }

            AuxiliarResposta::sucesso($transportadora, 'Transportadora encontrada');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova transportadora
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_pessoa' => 'obrigatorio|em:PF,PJ',
                'nome' => 'obrigatorio|min:3|max:200'
            ]);

            // Validações condicionais por tipo de pessoa
            if (isset($dados['tipo_pessoa'])) {
                if ($dados['tipo_pessoa'] === 'PJ') {
                    // Para PJ, CNPJ é obrigatório
                    $errosCondicional = AuxiliarValidacao::validar($dados, [
                        'cnpj' => 'obrigatorio|cnpj'
                    ]);
                    $erros = array_merge($erros, $errosCondicional);
                } elseif ($dados['tipo_pessoa'] === 'PF') {
                    // Para PF, CPF é obrigatório
                    $errosCondicional = AuxiliarValidacao::validar($dados, [
                        'cpf' => 'obrigatorio|cpf'
                    ]);
                    $erros = array_merge($erros, $errosCondicional);
                }
            }

            // Validações opcionais
            if (isset($dados['email']) && !empty($dados['email'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['email' => 'email']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['data_nascimento']) && !empty($dados['data_nascimento'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['data_nascimento' => 'data']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas
            if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
                $dados['cnpj'] = preg_replace('/[^0-9]/', '', $dados['cnpj']);
                if ($this->model->cnpjExiste($dados['cnpj'])) {
                    AuxiliarResposta::conflito('CNPJ já cadastrado no sistema');
                    return;
                }
            }

            if (isset($dados['cpf']) && !empty($dados['cpf'])) {
                $dados['cpf'] = preg_replace('/[^0-9]/', '', $dados['cpf']);
                if ($this->model->cpfExiste($dados['cpf'])) {
                    AuxiliarResposta::conflito('CPF já cadastrado no sistema');
                    return;
                }
            }

            if (isset($dados['email']) && !empty($dados['email'])) {
                if ($this->model->emailExiste($dados['email'])) {
                    AuxiliarResposta::conflito('Email já cadastrado no sistema');
                    return;
                }
            }

            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Inicia transação
            $this->db->iniciarTransacao();

            try {
                // Cria a transportadora
                $id = $this->model->criar($dados);

                // Processa contatos se existirem
                if (isset($dados['contatos']) && is_array($dados['contatos'])) {
                    foreach ($dados['contatos'] as $contato) {
                        if (isset($contato['nome']) && isset($contato['contato'])) {
                            $contato['transportadora_id'] = $id;
                            $this->modelContato->criar($contato);
                        }
                    }
                }

                // Processa endereços se existirem
                if (isset($dados['enderecos']) && is_array($dados['enderecos'])) {
                    foreach ($dados['enderecos'] as $endereco) {
                        $endereco['transportadora_id'] = $id;
                        $this->modelEndereco->criar($endereco);
                    }
                }

                // Confirma a transação
                $this->db->commit();

                $transportadora = $this->model->buscarComRelacionamentos($id);

                AuxiliarResposta::criado($transportadora, 'Transportadora cadastrada com sucesso');
            } catch (\Exception $e) {
                // Reverte a transação em caso de erro
                $this->db->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma transportadora
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se a transportadora existe
            $transportadoraExistente = $this->model->buscarPorId((int) $id);
            if (!$transportadoraExistente) {
                AuxiliarResposta::naoEncontrado('Transportadora não encontrada');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados (campos opcionais)
            $regras = [];

            if (isset($dados['tipo_pessoa'])) {
                $regras['tipo_pessoa'] = 'obrigatorio|em:PF,PJ';
            }

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
                $regras['cnpj'] = 'cnpj';
            }

            if (isset($dados['cpf']) && !empty($dados['cpf'])) {
                $regras['cpf'] = 'cpf';
            }

            if (isset($dados['email']) && !empty($dados['email'])) {
                $regras['email'] = 'email';
            }

            if (isset($dados['data_nascimento']) && !empty($dados['data_nascimento'])) {
                $regras['data_nascimento'] = 'data';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas (excluindo a própria transportadora)
            if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
                $dados['cnpj'] = preg_replace('/[^0-9]/', '', $dados['cnpj']);
                if ($this->model->cnpjExiste($dados['cnpj'], (int) $id)) {
                    AuxiliarResposta::conflito('CNPJ já cadastrado em outra transportadora');
                    return;
                }
            }

            if (isset($dados['cpf']) && !empty($dados['cpf'])) {
                $dados['cpf'] = preg_replace('/[^0-9]/', '', $dados['cpf']);
                if ($this->model->cpfExiste($dados['cpf'], (int) $id)) {
                    AuxiliarResposta::conflito('CPF já cadastrado em outra transportadora');
                    return;
                }
            }

            if (isset($dados['email']) && !empty($dados['email'])) {
                if ($this->model->emailExiste($dados['email'], (int) $id)) {
                    AuxiliarResposta::conflito('Email já cadastrado em outra transportadora');
                    return;
                }
            }

            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outra transportadora');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Inicia transação
            $this->db->iniciarTransacao();

            try {
                // Atualiza a transportadora
                $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

                if (!$resultado) {
                    throw new \Exception('Erro ao atualizar transportadora');
                }

                // Processa contatos se existirem
                if (isset($dados['contatos']) && is_array($dados['contatos'])) {
                    // Remove contatos antigos
                    $this->modelContato->deletarPorTransportadora((int) $id);

                    // Adiciona novos contatos
                    foreach ($dados['contatos'] as $contato) {
                        if (isset($contato['nome']) && isset($contato['contato'])) {
                            $contato['transportadora_id'] = (int) $id;
                            $this->modelContato->criar($contato);
                        }
                    }
                }

                // Processa endereços se existirem
                if (isset($dados['enderecos']) && is_array($dados['enderecos'])) {
                    // Remove endereços antigos
                    $this->modelEndereco->deletarPorTransportadora((int) $id);

                    // Adiciona novos endereços
                    foreach ($dados['enderecos'] as $endereco) {
                        $endereco['transportadora_id'] = (int) $id;
                        $this->modelEndereco->criar($endereco);
                    }
                }

                // Confirma a transação
                $this->db->commit();

                $transportadora = $this->model->buscarComRelacionamentos((int) $id);

                AuxiliarResposta::sucesso($transportadora, 'Transportadora atualizada com sucesso');
            } catch (\Exception $e) {
                // Reverte a transação em caso de erro
                $this->db->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma transportadora (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se a transportadora existe
            $transportadora = $this->model->buscarPorId((int) $id);
            if (!$transportadora) {
                AuxiliarResposta::naoEncontrado('Transportadora não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a transportadora (soft delete)
            // Os contatos e endereços serão deletados automaticamente por CASCADE
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar transportadora', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Transportadora removida com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das transportadoras
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas das transportadoras obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
