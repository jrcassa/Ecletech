<?php

namespace App\Services\Venda;

use App\Core\BancoDados;
use App\Models\Venda\ModelVenda;
use App\Models\Venda\ModelVendaItem;
use App\Models\Venda\ModelVendaPagamento;
use App\Models\Venda\ModelVendaEndereco;
use App\Models\Venda\ModelVendaAtributo;
use App\Models\Cliente\ModelCliente;
use App\Models\Colaborador\ModelColaborador;
use App\Models\SituacaoVenda\ModelSituacaoVenda;
use App\Models\Produtos\ModelProdutos;
use App\Models\Servico\ModelServico;

/**
 * Service principal para gerenciar vendas
 * Coordena Models, valida regras de negócio, gerencia transações
 */
class ServiceVenda
{
    private BancoDados $db;
    private ModelVenda $vendaModel;
    private ModelVendaItem $itemModel;
    private ModelVendaPagamento $pagamentoModel;
    private ModelVendaEndereco $enderecoModel;
    private ModelVendaAtributo $atributoModel;
    private ModelCliente $clienteModel;
    private ModelColaborador $colaboradorModel;
    private ModelSituacaoVenda $situacaoModel;
    private ModelProdutos $produtoModel;
    private ModelServico $servicoModel;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->vendaModel = new ModelVenda();
        $this->itemModel = new ModelVendaItem();
        $this->pagamentoModel = new ModelVendaPagamento();
        $this->enderecoModel = new ModelVendaEndereco();
        $this->atributoModel = new ModelVendaAtributo();
        $this->clienteModel = new ModelCliente();
        $this->colaboradorModel = new ModelColaborador();
        $this->situacaoModel = new ModelSituacaoVenda();
        $this->produtoModel = new ModelProdutos();
        $this->servicoModel = new ModelServico();
    }

    /**
     * Cria uma venda completa com todos os relacionamentos
     * Usa transação para garantir consistência
     */
    public function criarVendaCompleta(array $dados, ?int $usuarioId = null): array
    {
        try {
            $this->db->iniciarTransacao();

            // 1. Valida dados básicos
            $this->validarDadosVenda($dados);

            // 2. Enriquece dados (busca nomes para snapshot)
            $dadosEnriquecidos = $this->enriquecerDadosVenda($dados);

            // 3. Gera código e hash se não informados
            if (empty($dadosEnriquecidos['codigo'])) {
                $dadosEnriquecidos['codigo'] = $this->vendaModel->gerarCodigo();
            }
            if (empty($dadosEnriquecidos['hash'])) {
                $dadosEnriquecidos['hash'] = $this->vendaModel->gerarHash();
            }

            // 4. Cria venda principal
            $dadosEnriquecidos['colaborador_id'] = $usuarioId;
            $vendaId = $this->vendaModel->criar($dadosEnriquecidos);

            // 5. Cria itens (produtos e serviços)
            if (!empty($dados['itens'])) {
                $this->criarItens($vendaId, $dados['itens'], $usuarioId);
            }

            // 6. Cria pagamentos
            if (!empty($dados['pagamentos'])) {
                $this->criarPagamentos($vendaId, $dados['pagamentos'], $usuarioId);
            }

            // 7. Cria endereço de entrega
            // Aceita tanto 'endereco' (singular) quanto 'enderecos' (plural array)
            $endereco = null;
            if (!empty($dados['endereco'])) {
                $endereco = $dados['endereco'];
            } elseif (!empty($dados['enderecos']) && is_array($dados['enderecos']) && count($dados['enderecos']) > 0) {
                $endereco = $dados['enderecos'][0]; // Pega primeiro endereço do array
            }

            if ($endereco) {
                $this->criarEndereco($vendaId, $endereco, $usuarioId);
            }

            // 8. Cria atributos customizados
            if (!empty($dados['atributos'])) {
                $this->criarAtributos($vendaId, $dados['atributos'], $usuarioId);
            }

            // 9. Recalcula e atualiza totais
            $this->recalcularTotais($vendaId);

            // 10. Commit da transação
            $this->db->commit();

            // 11. Retorna venda completa
            return $this->vendaModel->buscarCompleta($vendaId);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception('Erro ao criar venda: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza uma venda completa
     */
    public function atualizarVendaCompleta(int $vendaId, array $dados, ?int $usuarioId = null): array
    {
        try {
            $this->db->iniciarTransacao();

            // 1. Verifica se venda existe
            $vendaExistente = $this->vendaModel->buscarPorId($vendaId);
            if (!$vendaExistente) {
                throw new \Exception('Venda não encontrada');
            }

            // 2. Enriquece dados
            $dadosEnriquecidos = $this->enriquecerDadosVenda($dados);

            // 3. Atualiza venda principal
            $this->vendaModel->atualizar($vendaId, $dadosEnriquecidos, $usuarioId);

            // 4. Atualiza itens (deleta e recria)
            if (isset($dados['itens'])) {
                $this->itemModel->deletarPorVenda($vendaId, $usuarioId);
                $this->criarItens($vendaId, $dados['itens'], $usuarioId);
            }

            // 5. Atualiza pagamentos (deleta e recria)
            if (isset($dados['pagamentos'])) {
                $this->pagamentoModel->deletarPorVenda($vendaId, $usuarioId);
                $this->criarPagamentos($vendaId, $dados['pagamentos'], $usuarioId);
            }

            // 6. Atualiza endereço
            // Aceita tanto 'endereco' quanto 'enderecos'
            if (isset($dados['endereco']) || isset($dados['enderecos'])) {
                $this->enderecoModel->deletarPorVenda($vendaId, $usuarioId);

                $endereco = null;
                if (!empty($dados['endereco'])) {
                    $endereco = $dados['endereco'];
                } elseif (!empty($dados['enderecos']) && is_array($dados['enderecos']) && count($dados['enderecos']) > 0) {
                    $endereco = $dados['enderecos'][0];
                }

                if ($endereco) {
                    $this->criarEndereco($vendaId, $endereco, $usuarioId);
                }
            }

            // 7. Atualiza atributos
            if (isset($dados['atributos'])) {
                $this->atributoModel->deletarPorVenda($vendaId, $usuarioId);
                if (!empty($dados['atributos'])) {
                    $this->criarAtributos($vendaId, $dados['atributos'], $usuarioId);
                }
            }

            // 8. Recalcula totais
            $this->recalcularTotais($vendaId);

            // 9. Commit
            $this->db->commit();

            // 10. Retorna venda atualizada
            return $this->vendaModel->buscarCompleta($vendaId);

        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception('Erro ao atualizar venda: ' . $e->getMessage());
        }
    }

    /**
     * Valida dados básicos da venda
     */
    private function validarDadosVenda(array $dados): void
    {
        // Data de venda obrigatória
        if (empty($dados['data_venda'])) {
            throw new \Exception('Data da venda é obrigatória');
        }

        // Ao menos um item obrigatório
        if (empty($dados['itens']) || !is_array($dados['itens']) || count($dados['itens']) === 0) {
            throw new \Exception('Venda deve ter ao menos um item');
        }

        // Valida itens
        foreach ($dados['itens'] as $item) {
            $this->validarItem($item);
        }

        // Valida pagamentos
        if (!empty($dados['pagamentos'])) {
            foreach ($dados['pagamentos'] as $pagamento) {
                $this->validarPagamento($pagamento);
            }
        }
    }

    /**
     * Valida um item
     */
    private function validarItem(array $item): void
    {
        $tipo = $item['tipo'] ?? 'produto';

        if (!in_array($tipo, ['produto', 'servico'])) {
            throw new \Exception('Tipo de item inválido. Use "produto" ou "servico"');
        }

        // Se tipo = produto, deve ter produto_id ou produto_external_id
        if ($tipo === 'produto') {
            if (empty($item['produto_id']) && empty($item['produto_external_id'])) {
                throw new \Exception('Item do tipo produto deve ter produto_id ou produto_external_id');
            }
        }

        // Se tipo = servico, deve ter servico_id ou servico_external_id
        if ($tipo === 'servico') {
            if (empty($item['servico_id']) && empty($item['servico_external_id'])) {
                throw new \Exception('Item do tipo serviço deve ter servico_id ou servico_external_id');
            }
        }

        // Quantidade obrigatória
        if (empty($item['quantidade']) || $item['quantidade'] <= 0) {
            throw new \Exception('Quantidade deve ser maior que zero');
        }

        // Valor de venda obrigatório
        if (!isset($item['valor_venda']) || $item['valor_venda'] < 0) {
            throw new \Exception('Valor de venda é obrigatório');
        }
    }

    /**
     * Valida um pagamento
     */
    private function validarPagamento(array $pagamento): void
    {
        if (empty($pagamento['data_vencimento'])) {
            throw new \Exception('Data de vencimento do pagamento é obrigatória');
        }

        if (empty($pagamento['valor']) || $pagamento['valor'] <= 0) {
            throw new \Exception('Valor do pagamento deve ser maior que zero');
        }
    }

    /**
     * Enriquece dados da venda (busca nomes para snapshot)
     */
    private function enriquecerDadosVenda(array $dados): array
    {
        $dadosEnriquecidos = $dados;

        // Busca nome do cliente
        if (!empty($dados['cliente_id']) && empty($dados['nome_cliente'])) {
            $cliente = $this->clienteModel->buscarPorId($dados['cliente_id']);
            if ($cliente) {
                $dadosEnriquecidos['nome_cliente'] = $cliente['tipo_pessoa'] === 'PJ'
                    ? $cliente['razao_social']
                    : $cliente['nome'];
            }
        }

        // Busca nome do vendedor
        if (!empty($dados['vendedor_id']) && empty($dados['nome_vendedor'])) {
            $vendedor = $this->colaboradorModel->buscarPorId($dados['vendedor_id']);
            if ($vendedor) {
                $dadosEnriquecidos['nome_vendedor'] = $vendedor['nome'];
            }
        }

        // Busca nome do técnico
        if (!empty($dados['tecnico_id']) && empty($dados['nome_tecnico'])) {
            $tecnico = $this->colaboradorModel->buscarPorId($dados['tecnico_id']);
            if ($tecnico) {
                $dadosEnriquecidos['nome_tecnico'] = $tecnico['nome'];
            }
        }

        // Busca nome da situação
        if (!empty($dados['situacao_venda_id']) && empty($dados['nome_situacao'])) {
            $situacao = $this->situacaoModel->buscarPorId($dados['situacao_venda_id']);
            if ($situacao) {
                $dadosEnriquecidos['nome_situacao'] = $situacao['nome'];
            }
        }

        return $dadosEnriquecidos;
    }

    /**
     * Cria itens da venda
     */
    private function criarItens(int $vendaId, array $itens, ?int $usuarioId): void
    {
        foreach ($itens as $item) {
            $dadosItem = $item;
            $dadosItem['venda_id'] = $vendaId;
            $dadosItem['colaborador_id'] = $usuarioId;

            // Define tipo padrão
            if (empty($dadosItem['tipo'])) {
                $dadosItem['tipo'] = 'produto';
            }

            // Enriquece dados do item
            $dadosItem = $this->enriquecerDadosItem($dadosItem);

            // Calcula valor total do item
            if (empty($dadosItem['valor_total'])) {
                $dadosItem['valor_total'] = $this->itemModel->calcularValorTotal($dadosItem);
            }

            $this->itemModel->criar($dadosItem);
        }
    }

    /**
     * Enriquece dados do item (busca nome do produto/serviço)
     */
    private function enriquecerDadosItem(array $item): array
    {
        $dadosEnriquecidos = $item;
        $tipo = $item['tipo'] ?? 'produto';

        if ($tipo === 'produto') {
            // Busca dados do produto
            if (!empty($item['produto_id']) && empty($item['nome_produto'])) {
                $produto = $this->produtoModel->buscarPorId($item['produto_id']);
                if ($produto) {
                    $dadosEnriquecidos['nome_produto'] = $produto['nome'];
                    $dadosEnriquecidos['sigla_unidade'] = $produto['sigla_unidade'] ?? 'UN';

                    if (empty($dadosEnriquecidos['valor_custo'])) {
                        $dadosEnriquecidos['valor_custo'] = $produto['valor_custo'] ?? 0;
                    }
                }
            }
        } elseif ($tipo === 'servico') {
            // Busca dados do serviço
            if (!empty($item['servico_id']) && empty($item['nome_produto'])) {
                $servico = $this->servicoModel->buscarPorId($item['servico_id']);
                if ($servico) {
                    $dadosEnriquecidos['nome_produto'] = $servico['nome'];
                    $dadosEnriquecidos['sigla_unidade'] = $servico['sigla_unidade'] ?? null;

                    if (empty($dadosEnriquecidos['valor_custo'])) {
                        $dadosEnriquecidos['valor_custo'] = $servico['valor_custo'] ?? 0;
                    }
                }
            }
        }

        return $dadosEnriquecidos;
    }

    /**
     * Cria pagamentos da venda
     */
    private function criarPagamentos(int $vendaId, array $pagamentos, ?int $usuarioId): void
    {
        foreach ($pagamentos as $pagamento) {
            $dadosPagamento = $pagamento;
            $dadosPagamento['venda_id'] = $vendaId;
            $dadosPagamento['colaborador_id'] = $usuarioId;

            $this->pagamentoModel->criar($dadosPagamento);
        }
    }

    /**
     * Cria endereço de entrega
     */
    private function criarEndereco(int $vendaId, array $endereco, ?int $usuarioId): void
    {
        $dadosEndereco = $endereco;
        $dadosEndereco['venda_id'] = $vendaId;
        $dadosEndereco['colaborador_id'] = $usuarioId;

        $this->enderecoModel->criar($dadosEndereco);
    }

    /**
     * Cria atributos customizados
     */
    private function criarAtributos(int $vendaId, array $atributos, ?int $usuarioId): void
    {
        foreach ($atributos as $atributo) {
            $dadosAtributo = $atributo;
            $dadosAtributo['venda_id'] = $vendaId;
            $dadosAtributo['colaborador_id'] = $usuarioId;

            $this->atributoModel->criar($dadosAtributo);
        }
    }

    /**
     * Recalcula totais da venda baseado nos itens
     */
    private function recalcularTotais(int $vendaId): void
    {
        $totais = $this->vendaModel->calcularTotais($vendaId);

        $this->vendaModel->atualizar($vendaId, [
            'valor_produtos' => $totais['valor_produtos'],
            'valor_servicos' => $totais['valor_servicos'],
            'valor_total' => $totais['valor_total']
        ]);
    }

    /**
     * Busca venda completa
     */
    public function buscarVendaCompleta(int $vendaId): ?array
    {
        return $this->vendaModel->buscarCompleta($vendaId);
    }

    /**
     * Lista vendas com filtros
     */
    public function listarVendas(array $filtros = []): array
    {
        return $this->vendaModel->listar($filtros);
    }

    /**
     * Conta vendas com filtros
     */
    public function contarVendas(array $filtros = []): int
    {
        return $this->vendaModel->contar($filtros);
    }

    /**
     * Deleta venda (soft delete)
     */
    public function deletarVenda(int $vendaId, ?int $usuarioId = null): bool
    {
        return $this->vendaModel->deletar($vendaId, $usuarioId);
    }

    /**
     * Atualiza situação financeira da venda
     */
    public function atualizarSituacaoFinanceira(int $vendaId): void
    {
        $totalPago = $this->pagamentoModel->calcularTotalPago($vendaId);
        $totalAPagar = $this->pagamentoModel->calcularTotalAPagar($vendaId);

        $situacao = 0; // Pendente
        if ($totalPago > 0 && $totalAPagar > 0) {
            $situacao = 2; // Parcial
        } elseif ($totalAPagar == 0) {
            $situacao = 1; // Pago
        }

        $this->vendaModel->atualizar($vendaId, [
            'situacao_financeiro' => $situacao
        ]);
    }
}
