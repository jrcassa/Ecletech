<?php

namespace App\Services\Transportadora;

use App\Models\Transportadora\ModelTransportadora;
use App\Models\Transportadora\ModelTransportadoraContato;
use App\Models\Transportadora\ModelTransportadoraEndereco;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Service para gerenciar lógica de negócio de Transportadoras
 * Coordena Transportadora + Contatos + Endereços com transações
 */
class ServiceTransportadora
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
     * Cria transportadora completa (transportadora + contatos + endereços)
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
            // Cria transportadora
            $transportadoraId = $this->model->criar($dados);

            // Adiciona contatos se existirem
            $this->adicionarContatos($transportadoraId, $dados['contatos'] ?? []);

            // Adiciona endereços se existirem
            $this->adicionarEnderecos($transportadoraId, $dados['enderecos'] ?? []);

            $this->db->commit();

            // Retorna transportadora completa
            return $this->model->buscarComRelacionamentos($transportadoraId);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza transportadora completa
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
            // Atualiza transportadora
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
     * Remove transportadora
     */
    public function deletar(int $id): bool
    {
        $this->verificarExistencia($id);

        $usuarioId = $this->obterUsuarioAtual();

        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Busca transportadora completa
     */
    public function buscarComRelacionamentos(int $id): ?array
    {
        return $this->model->buscarComRelacionamentos($id);
    }

    /**
     * Busca transportadora por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista transportadoras
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta transportadoras
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
        // Sanitiza CNPJ
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
                throw new \Exception('CNPJ já cadastrado em outra transportadora');
            }
        }

        if (isset($dados['email']) && !empty($dados['email'])) {
            if ($this->model->emailExiste($dados['email'], $id)) {
                throw new \Exception('Email já cadastrado em outra transportadora');
            }
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outra transportadora');
            }
        }
    }

    /**
     * Adiciona contatos à transportadora
     */
    private function adicionarContatos(int $transportadoraId, array $contatos): void
    {
        foreach ($contatos as $contato) {
            if (isset($contato['nome']) && isset($contato['contato'])) {
                $contato['transportadora_id'] = $transportadoraId;
                $this->modelContato->criar($contato);
            }
        }
    }

    /**
     * Adiciona endereços à transportadora
     */
    private function adicionarEnderecos(int $transportadoraId, array $enderecos): void
    {
        foreach ($enderecos as $endereco) {
            $endereco['transportadora_id'] = $transportadoraId;
            $this->modelEndereco->criar($endereco);
        }
    }

    /**
     * Atualiza contatos da transportadora
     */
    private function atualizarContatos(int $transportadoraId, array $contatos): void
    {
        // Remove contatos existentes
        $this->modelContato->deletarPorTransportadora($transportadoraId);

        // Adiciona novos contatos
        $this->adicionarContatos($transportadoraId, $contatos);
    }

    /**
     * Atualiza endereços da transportadora
     */
    private function atualizarEnderecos(int $transportadoraId, array $enderecos): void
    {
        // Remove endereços existentes
        $this->modelEndereco->deletarPorTransportadora($transportadoraId);

        // Adiciona novos endereços
        $this->adicionarEnderecos($transportadoraId, $enderecos);
    }

    /**
     * Verifica se transportadora existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Transportadora não encontrada');
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
