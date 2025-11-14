<?php

namespace App\Services\Pagamento;

use App\Models\Pagamento\ModelPagamento;
use App\Core\Autenticacao;

/**
 * Service para gerenciar lógica de negócio de Pagamentos
 */
class ServicePagamento
{
    private ModelPagamento $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelPagamento();
        $this->auth = new Autenticacao();
    }

    /**
     * Cria um pagamento
     */
    public function criar(array $dados): array
    {
        // Validações de negócio
        $this->validarDadosPagamento($dados, false);

        // Enriquece com dados do sistema
        $dados['usuario_id'] = $this->obterUsuarioAtual();

        // Persiste
        $id = $this->model->criar($dados);

        // Retorna dados completos
        return $this->model->buscarPorId($id);
    }

    /**
     * Atualiza um pagamento
     */
    public function atualizar(int $id, array $dados): array
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Validações de negócio
        $this->validarDadosPagamento($dados, true);

        // Verifica se external_id é único (se fornecido)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outro pagamento');
            }
        }

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Atualiza
        $this->model->atualizar($id, $dados, $usuarioId);

        // Retorna dados atualizados
        return $this->model->buscarPorId($id);
    }

    /**
     * Remove um pagamento
     */
    public function deletar(int $id): bool
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Deleta
        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Liquida um pagamento
     */
    public function liquidar(int $id, array $dados): array
    {
        // Verifica se existe
        $pagamento = $this->model->buscarPorId($id);
        if (!$pagamento) {
            throw new \Exception('Pagamento não encontrado');
        }

        // Verifica se já está liquidado
        if ($pagamento['liquidado'] == 1) {
            throw new \Exception('Pagamento já está liquidado');
        }

        // Validação da data de liquidação
        if (!isset($dados['data_liquidacao']) || empty($dados['data_liquidacao'])) {
            $dados['data_liquidacao'] = date('Y-m-d');
        }

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Liquida
        $this->model->liquidar($id, $dados['data_liquidacao'], $usuarioId);

        // Retorna dados atualizados
        return $this->model->buscarPorId($id);
    }

    /**
     * Busca por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista com filtros e paginação
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta total
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
     * Valida dados do pagamento
     */
    private function validarDadosPagamento(array $dados, bool $isUpdate): void
    {
        // Validações apenas para criação
        if (!$isUpdate) {
            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    throw new \Exception('External ID já cadastrado no sistema');
                }
            }

            // Validação de entidade obrigatória
            if (!isset($dados['entidade']) || !in_array($dados['entidade'], ['C', 'F', 'T', 'U'])) {
                throw new \Exception('Entidade inválida. Deve ser C (Cliente), F (Fornecedor), T (Transportadora) ou U (Funcionário)');
            }

            // Validação de acordo com a entidade
            switch ($dados['entidade']) {
                case 'C': // Cliente
                    if (empty($dados['cliente_id'])) {
                        throw new \Exception('Cliente é obrigatório quando entidade=C');
                    }
                    break;

                case 'F': // Fornecedor
                    if (empty($dados['fornecedor_id'])) {
                        throw new \Exception('Fornecedor é obrigatório quando entidade=F');
                    }
                    break;

                case 'T': // Transportadora
                    if (empty($dados['transportadora_id'])) {
                        throw new \Exception('Transportadora é obrigatória quando entidade=T');
                    }
                    break;

                case 'U': // Funcionário
                    if (empty($dados['funcionario_id'])) {
                        throw new \Exception('Funcionário é obrigatório quando entidade=U');
                    }
                    break;
            }
        }

        // Validações de valores
        if (isset($dados['valor']) && $dados['valor'] < 0) {
            throw new \Exception('Valor não pode ser negativo');
        }

        if (isset($dados['juros']) && $dados['juros'] < 0) {
            throw new \Exception('Juros não pode ser negativo');
        }

        if (isset($dados['desconto']) && $dados['desconto'] < 0) {
            throw new \Exception('Desconto não pode ser negativo');
        }

        if (isset($dados['taxa_banco']) && $dados['taxa_banco'] < 0) {
            throw new \Exception('Taxa do banco não pode ser negativa');
        }

        if (isset($dados['taxa_operadora']) && $dados['taxa_operadora'] < 0) {
            throw new \Exception('Taxa da operadora não pode ser negativa');
        }

        // Validação de data de vencimento
        if (isset($dados['data_vencimento'])) {
            if (!$this->validarData($dados['data_vencimento'])) {
                throw new \Exception('Data de vencimento inválida');
            }
        }

        // Validação de data de liquidação
        if (isset($dados['data_liquidacao']) && !empty($dados['data_liquidacao'])) {
            if (!$this->validarData($dados['data_liquidacao'])) {
                throw new \Exception('Data de liquidação inválida');
            }
        }

        // Validação de data de competência
        if (isset($dados['data_competencia']) && !empty($dados['data_competencia'])) {
            if (!$this->validarData($dados['data_competencia'])) {
                throw new \Exception('Data de competência inválida');
            }
        }

        // Se está marcado como liquidado, deve ter data de liquidação
        if (isset($dados['liquidado']) && $dados['liquidado'] == 1) {
            if (empty($dados['data_liquidacao'])) {
                throw new \Exception('Data de liquidação é obrigatória quando o pagamento está liquidado');
            }
        }
    }

    /**
     * Valida formato de data (Y-m-d)
     */
    private function validarData(string $data): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }

    /**
     * Verifica se o pagamento existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Pagamento não encontrado');
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
