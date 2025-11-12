<?php

namespace App\Services\Fornecedor;

use App\Models\Fornecedor\ModelFornecedor;
use App\Models\Fornecedor\ModelFornecedorContato;
use App\Models\Fornecedor\ModelFornecedorEndereco;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Service para gerenciar lógica de negócio de Fornecedores
 * Coordena Fornecedor + Contatos + Endereços com transações
 */
class ServiceFornecedor
{
    private ModelFornecedor $model;
    private ModelFornecedorContato $modelContato;
    private ModelFornecedorEndereco $modelEndereco;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelFornecedor();
        $this->modelContato = new ModelFornecedorContato();
        $this->modelEndereco = new ModelFornecedorEndereco();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria fornecedor completo (fornecedor + contatos + endereços)
     */
    public function criarCompleto(array $dados): array
    {
        // Prepara e valida dados
        $dados = $this->prepararDados($dados);
        $this->validarDuplicatas($dados);

        // Enriquece com usuário
        $dados['colaborador_id'] = $this->obterUsuarioAtual();

        // Transação para garantir atomicidade
        $this->db->iniciarTransacao();

        try {
            // Cria fornecedor
            $fornecedorId = $this->model->criar($dados);

            // Adiciona contatos se existirem
            $this->adicionarContatos($fornecedorId, $dados['contatos'] ?? []);

            // Adiciona endereços se existirem
            $this->adicionarEnderecos($fornecedorId, $dados['enderecos'] ?? []);

            $this->db->commit();

            // Retorna fornecedor completo
            return $this->model->buscarComRelacionamentos($fornecedorId);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza fornecedor completo
     */
    public function atualizarCompleto(int $id, array $dados): array
    {
        // Verifica existência
        $this->verificarExistencia($id);

        // Prepara e valida dados
        $dados = $this->prepararDados($dados);
        $this->validarDuplicatasParaAtualizacao($id, $dados);

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Transação
        $this->db->iniciarTransacao();

        try {
            // Atualiza fornecedor
            $this->model->atualizar($id, $dados, $usuarioId);

            // Atualiza contatos se fornecidos
            if (isset($dados['contatos'])) {
                $this->atualizarContatos($id, $dados['contatos']);
            }

            // Atualiza endereços se fornecidos
            if (isset($dados['enderecos'])) {
                $this->atualizarEnderecos($id, $dados['enderecos']);
            }

            $this->db->commit();

            return $this->model->buscarComRelacionamentos($id);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Remove fornecedor
     */
    public function deletar(int $id): bool
    {
        $this->verificarExistencia($id);

        $usuarioId = $this->obterUsuarioAtual();

        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Busca fornecedor completo
     */
    public function buscarComRelacionamentos(int $id): ?array
    {
        return $this->model->buscarComRelacionamentos($id);
    }

    /**
     * Busca fornecedor por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista fornecedores
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta fornecedores
     */
    public function contar(array $filtros): int
    {
        return $this->model->contar($filtros);
    }

    /**
     * Obtém estatísticas
     */
    public function obterEstatisticas(): array
    {
        return $this->model->obterEstatisticas();
    }

    // =============== MÉTODOS PRIVADOS ===============

    /**
     * Prepara dados (sanitização)
     */
    private function prepararDados(array $dados): array
    {
        // Sanitiza CPF/CNPJ
        if (isset($dados['cpf']) && !empty($dados['cpf'])) {
            $dados['cpf'] = AuxiliarSanitizacao::cpf($dados['cpf']);
        }

        if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
            $dados['cnpj'] = AuxiliarSanitizacao::cnpj($dados['cnpj']);
        }

        return $dados;
    }

    /**
     * Valida duplicatas
     */
    private function validarDuplicatas(array $dados): void
    {
        if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
            if ($this->model->cnpjExiste($dados['cnpj'])) {
                throw new \Exception('CNPJ já cadastrado no sistema');
            }
        }

        if (isset($dados['cpf']) && !empty($dados['cpf'])) {
            if ($this->model->cpfExiste($dados['cpf'])) {
                throw new \Exception('CPF já cadastrado no sistema');
            }
        }

        if (isset($dados['email']) && !empty($dados['email'])) {
            if ($this->model->emailExiste($dados['email'])) {
                throw new \Exception('Email já cadastrado no sistema');
            }
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'])) {
                throw new \Exception('External ID já cadastrado no sistema');
            }
        }
    }

    /**
     * Valida duplicatas para atualização
     */
    private function validarDuplicatasParaAtualizacao(int $id, array $dados): void
    {
        if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
            if ($this->model->cnpjExiste($dados['cnpj'], $id)) {
                throw new \Exception('CNPJ já cadastrado em outro fornecedor');
            }
        }

        if (isset($dados['cpf']) && !empty($dados['cpf'])) {
            if ($this->model->cpfExiste($dados['cpf'], $id)) {
                throw new \Exception('CPF já cadastrado em outro fornecedor');
            }
        }

        if (isset($dados['email']) && !empty($dados['email'])) {
            if ($this->model->emailExiste($dados['email'], $id)) {
                throw new \Exception('Email já cadastrado em outro fornecedor');
            }
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outro fornecedor');
            }
        }
    }

    /**
     * Adiciona contatos ao fornecedor
     */
    private function adicionarContatos(int $fornecedorId, array $contatos): void
    {
        foreach ($contatos as $contato) {
            if (isset($contato['nome']) && isset($contato['contato'])) {
                $contato['fornecedor_id'] = $fornecedorId;
                $this->modelContato->criar($contato);
            }
        }
    }

    /**
     * Adiciona endereços ao fornecedor
     */
    private function adicionarEnderecos(int $fornecedorId, array $enderecos): void
    {
        foreach ($enderecos as $endereco) {
            $endereco['fornecedor_id'] = $fornecedorId;
            $this->modelEndereco->criar($endereco);
        }
    }

    /**
     * Atualiza contatos do fornecedor
     */
    private function atualizarContatos(int $fornecedorId, array $contatos): void
    {
        // Remove contatos existentes
        $this->modelContato->deletarPorFornecedor($fornecedorId);

        // Adiciona novos contatos
        $this->adicionarContatos($fornecedorId, $contatos);
    }

    /**
     * Atualiza endereços do fornecedor
     */
    private function atualizarEnderecos(int $fornecedorId, array $enderecos): void
    {
        // Remove endereços existentes
        $this->modelEndereco->deletarPorFornecedor($fornecedorId);

        // Adiciona novos endereços
        $this->adicionarEnderecos($fornecedorId, $enderecos);
    }

    /**
     * Verifica se fornecedor existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Fornecedor não encontrado');
        }
    }

    /**
     * Obtém ID do usuário autenticado
     */
    private function obterUsuarioAtual(): ?int
    {
        $usuario = $this->auth->obterUsuarioAutenticado();
        return $usuario['id'] ?? null;
    }
}
