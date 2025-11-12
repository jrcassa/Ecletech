<?php

namespace App\Services\Cliente;

use App\Models\Cliente\ModelCliente;
use App\Models\Cliente\ModelClienteContato;
use App\Models\Cliente\ModelClienteEndereco;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Service para gerenciar lógica de negócio de Clientes
 * Coordena Cliente + Contatos + Endereços com transações
 */
class ServiceCliente
{
    private ModelCliente $model;
    private ModelClienteContato $modelContato;
    private ModelClienteEndereco $modelEndereco;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelCliente();
        $this->modelContato = new ModelClienteContato();
        $this->modelEndereco = new ModelClienteEndereco();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria cliente completo (cliente + contatos + endereços)
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
            // Cria cliente
            $clienteId = $this->model->criar($dados);

            // Adiciona contatos se existirem
            $this->adicionarContatos($clienteId, $dados['contatos'] ?? []);

            // Adiciona endereços se existirem
            $this->adicionarEnderecos($clienteId, $dados['enderecos'] ?? []);

            $this->db->commit();

            // Retorna cliente completo
            return $this->model->buscarComRelacionamentos($clienteId);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza cliente completo
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
            // Atualiza cliente
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
     * Remove cliente
     */
    public function deletar(int $id): bool
    {
        $this->verificarExistencia($id);

        $usuarioId = $this->obterUsuarioAtual();

        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Busca cliente completo
     */
    public function buscarComRelacionamentos(int $id): ?array
    {
        return $this->model->buscarComRelacionamentos($id);
    }

    /**
     * Busca cliente por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista clientes
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta clientes
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
                throw new \Exception('CNPJ já cadastrado em outro cliente');
            }
        }

        if (isset($dados['cpf']) && !empty($dados['cpf'])) {
            if ($this->model->cpfExiste($dados['cpf'], $id)) {
                throw new \Exception('CPF já cadastrado em outro cliente');
            }
        }

        if (isset($dados['email']) && !empty($dados['email'])) {
            if ($this->model->emailExiste($dados['email'], $id)) {
                throw new \Exception('Email já cadastrado em outro cliente');
            }
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outro cliente');
            }
        }
    }

    /**
     * Adiciona contatos ao cliente
     */
    private function adicionarContatos(int $clienteId, array $contatos): void
    {
        foreach ($contatos as $contato) {
            if (isset($contato['nome']) && isset($contato['contato'])) {
                $contato['cliente_id'] = $clienteId;
                $this->modelContato->criar($contato);
            }
        }
    }

    /**
     * Adiciona endereços ao cliente
     */
    private function adicionarEnderecos(int $clienteId, array $enderecos): void
    {
        foreach ($enderecos as $endereco) {
            $endereco['cliente_id'] = $clienteId;
            $this->modelEndereco->criar($endereco);
        }
    }

    /**
     * Atualiza contatos do cliente
     */
    private function atualizarContatos(int $clienteId, array $contatos): void
    {
        // Remove contatos existentes
        $this->modelContato->deletarPorCliente($clienteId);

        // Adiciona novos contatos
        $this->adicionarContatos($clienteId, $contatos);
    }

    /**
     * Atualiza endereços do cliente
     */
    private function atualizarEnderecos(int $clienteId, array $enderecos): void
    {
        // Remove endereços existentes
        $this->modelEndereco->deletarPorCliente($clienteId);

        // Adiciona novos endereços
        $this->adicionarEnderecos($clienteId, $enderecos);
    }

    /**
     * Verifica se cliente existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Cliente não encontrado');
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
