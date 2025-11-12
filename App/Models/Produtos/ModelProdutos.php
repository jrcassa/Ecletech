<?php

namespace App\Models\Produtos;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar produtos (estrutura refatorada - 2 tabelas)
 */
class ModelProdutos
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um produto por ID com todos os relacionamentos
     */
    public function buscarPorId(int $id): ?array
    {
        $produto = $this->db->buscarUm(
            "SELECT * FROM produtos WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );

        if ($produto) {
            // Busca fornecedores
            $produto['fornecedores'] = $this->buscarFornecedoresDoProduto($id);

            // Busca valores (múltiplos preços por tipo)
            $produto['valores'] = $this->buscarValoresDoProduto($id);

            // Busca variações
            if ($produto['possui_variacao']) {
                $produto['variacoes'] = $this->buscarVariacoesDoProduto($id);
            } else {
                $produto['variacoes'] = [];
            }

            // Monta objeto fiscal
            $produto['fiscal'] = [
                'ncm' => $produto['ncm'] ?? null,
                'cest' => $produto['cest'] ?? null,
                'peso_liquido' => $produto['peso_liquido'] ?? null,
                'peso_bruto' => $produto['peso_bruto'] ?? null,
                'valor_aproximado_tributos' => $produto['valor_aproximado_tributos'] ?? null,
                'valor_fixo_pis' => $produto['valor_fixo_pis'] ?? null,
                'valor_fixo_pis_st' => $produto['valor_fixo_pis_st'] ?? null,
                'valor_fixo_confins' => $produto['valor_fixo_confins'] ?? null,
                'valor_fixo_confins_st' => $produto['valor_fixo_confins_st'] ?? null
            ];
        }

        return $produto;
    }

    /**
     * Busca um produto por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        $produto = $this->db->buscarUm(
            "SELECT * FROM produtos WHERE external_id = ? AND deleted_at IS NULL",
            [$externalId]
        );

        if ($produto) {
            $produto['fornecedores'] = $this->buscarFornecedoresDoProduto($produto['id']);
        }

        return $produto;
    }

    /**
     * Busca um produto por código interno
     */
    public function buscarPorCodigoInterno(string $codigoInterno): ?array
    {
        $produto = $this->db->buscarUm(
            "SELECT * FROM produtos WHERE codigo_interno = ? AND deleted_at IS NULL",
            [$codigoInterno]
        );

        if ($produto) {
            $produto['fornecedores'] = $this->buscarFornecedoresDoProduto($produto['id']);
        }

        return $produto;
    }

    /**
     * Busca fornecedores de um produto
     */
    private function buscarFornecedoresDoProduto(int $produtoId): array
    {
        return $this->db->buscarTodos(
            "SELECT pf.*, f.nome as fornecedor_nome
             FROM produtos_fornecedores pf
             INNER JOIN fornecedores f ON pf.fornecedor_id = f.id
             WHERE pf.produto_id = ?
             ORDER BY f.nome",
            [$produtoId]
        );
    }

    /**
     * Lista todos os produtos
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM produtos WHERE deleted_at IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por grupo
        if (isset($filtros['grupo_id']) && $filtros['grupo_id'] !== '') {
            $sql .= " AND grupo_id = ?";
            $parametros[] = $filtros['grupo_id'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo_interno LIKE ? OR codigo_barra LIKE ? OR descricao LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação
        $camposPermitidos = [
            'id', 'nome', 'codigo_interno', 'estoque', 'valor_venda', 'ativo', 'created_at', 'updated_at'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'nome',
            $filtros['direcao'] ?? 'ASC',
            $camposPermitidos,
            'nome'
        );
        $sql .= " ORDER BY {$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

        // Paginação
        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Conta o total de produtos
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE deleted_at IS NULL";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['grupo_id']) && $filtros['grupo_id'] !== '') {
            $sql .= " AND grupo_id = ?";
            $parametros[] = $filtros['grupo_id'];
        }

        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo_interno LIKE ? OR codigo_barra LIKE ? OR descricao LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria um novo produto
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'ativo' => $dados['ativo'] ?? 1,
            'estoque' => $dados['estoque'] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'codigo_interno', 'codigo_barra', 'descricao',
            'possui_variacao', 'possui_composicao', 'movimenta_estoque',
            'peso', 'largura', 'altura', 'comprimento',
            'grupo_id', 'nome_grupo',
            'valor_custo', 'valor_venda',
            'ncm', 'cest', 'peso_liquido', 'peso_bruto',
            'valor_aproximado_tributos', 'valor_fixo_pis', 'valor_fixo_pis_st',
            'valor_fixo_confins', 'valor_fixo_confins_st'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('produtos', $dadosInsert);

        // Insere fornecedores se fornecidos
        if (isset($dados['fornecedores']) && is_array($dados['fornecedores'])) {
            $this->sincronizarFornecedores($id, $dados['fornecedores']);
        }

        // Insere valores (múltiplos preços)
        if (isset($dados['valores']) && is_array($dados['valores'])) {
            $this->sincronizarValores($id, $dados['valores']);
        }

        // Insere variações
        if (isset($dados['variacoes']) && is_array($dados['variacoes'])) {
            $this->sincronizarVariacoes($id, $dados['variacoes']);
        }

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'produtos',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um produto
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'external_id', 'nome', 'codigo_interno', 'codigo_barra', 'descricao',
            'possui_variacao', 'possui_composicao', 'movimenta_estoque',
            'peso', 'largura', 'altura', 'comprimento',
            'grupo_id', 'nome_grupo',
            'estoque', 'valor_custo', 'valor_venda',
            'ncm', 'cest', 'peso_liquido', 'peso_bruto',
            'valor_aproximado_tributos', 'valor_fixo_pis', 'valor_fixo_pis_st',
            'valor_fixo_confins', 'valor_fixo_confins_st',
            'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('produtos', $dadosUpdate, 'id = ?', [$id]);

        // Atualiza fornecedores se fornecidos
        if (isset($dados['fornecedores']) && is_array($dados['fornecedores'])) {
            $this->sincronizarFornecedores($id, $dados['fornecedores']);
        }

        // Atualiza valores (múltiplos preços)
        if (isset($dados['valores']) && is_array($dados['valores'])) {
            $this->sincronizarValores($id, $dados['valores']);
        }

        // Atualiza variações
        if (isset($dados['variacoes']) && is_array($dados['variacoes'])) {
            $this->sincronizarVariacoes($id, $dados['variacoes']);
        }

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'produtos',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Sincroniza fornecedores do produto
     */
    private function sincronizarFornecedores(int $produtoId, array $fornecedores): void
    {
        // Remove todos os fornecedores atuais
        $this->db->executar(
            "DELETE FROM produtos_fornecedores WHERE produto_id = ?",
            [$produtoId]
        );

        // Insere novos fornecedores
        foreach ($fornecedores as $fornecedor) {
            $fornecedorId = is_array($fornecedor)
                ? ($fornecedor['fornecedor_id'] ?? $fornecedor['id'] ?? null)
                : $fornecedor;

            if ($fornecedorId) {
                $this->db->inserir('produtos_fornecedores', [
                    'produto_id' => $produtoId,
                    'fornecedor_id' => $fornecedorId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Deleta um produto (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'produtos',
            [
                'deleted_at' => date('Y-m-d H:i:s'),
                'ativo' => 0
            ],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'deletar',
                'produtos',
                $id,
                $dadosAtuais,
                ['deleted_at' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um external_id já existe
     */
    public function externalIdExiste(string $externalId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE external_id = ? AND deleted_at IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um código interno já existe
     */
    public function codigoInternoExiste(string $codigoInterno, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE codigo_interno = ? AND deleted_at IS NULL";
        $parametros = [$codigoInterno];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas dos produtos
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de produtos ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND deleted_at IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total de produtos inativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM produtos WHERE ativo = 0 AND deleted_at IS NULL"
        );
        $stats['total_inativos'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativos'] + $stats['total_inativos'];

        // Valor total em estoque
        $resultado = $this->db->buscarUm(
            "SELECT SUM(estoque * valor_venda) as total FROM produtos WHERE ativo = 1 AND deleted_at IS NULL"
        );
        $stats['valor_total_estoque'] = (float) ($resultado['total'] ?? 0);

        return $stats;
    }

    /**
     * Busca valores/preços de um produto
     */
    private function buscarValoresDoProduto(int $produtoId): array
    {
        return $this->db->buscarTodos(
            "SELECT tipo_id, nome_tipo, lucro_utilizado, valor_custo, valor_venda
             FROM produto_valores
             WHERE produto_id = ?
             ORDER BY nome_tipo",
            [$produtoId]
        );
    }

    /**
     * Busca variações de um produto
     */
    private function buscarVariacoesDoProduto(int $produtoId): array
    {
        $variacoes = $this->db->buscarTodos(
            "SELECT id, nome, estoque
             FROM produto_variacoes
             WHERE produto_id = ?
             ORDER BY nome",
            [$produtoId]
        );

        // Para cada variação, busca seus valores
        foreach ($variacoes as &$variacao) {
            $variacao['valores'] = $this->buscarValoresDaVariacao($variacao['id']);

            // Formata para a estrutura esperada
            $variacao = [
                'variacao' => $variacao
            ];
        }

        return $variacoes;
    }

    /**
     * Busca valores de uma variação específica
     */
    private function buscarValoresDaVariacao(int $variacaoId): array
    {
        return $this->db->buscarTodos(
            "SELECT tipo_id, nome_tipo, lucro_utilizado, valor_custo, valor_venda
             FROM produto_variacao_valores
             WHERE variacao_id = ?
             ORDER BY nome_tipo",
            [$variacaoId]
        );
    }

    /**
     * Sincroniza valores do produto
     */
    private function sincronizarValores(int $produtoId, array $valores): void
    {
        // Remove todos os valores atuais
        $this->db->executar(
            "DELETE FROM produto_valores WHERE produto_id = ?",
            [$produtoId]
        );

        // Insere novos valores
        foreach ($valores as $valor) {
            if (isset($valor['tipo_id']) && isset($valor['nome_tipo'])) {
                $this->db->inserir('produto_valores', [
                    'produto_id' => $produtoId,
                    'tipo_id' => $valor['tipo_id'],
                    'nome_tipo' => $valor['nome_tipo'],
                    'lucro_utilizado' => $valor['lucro_utilizado'] ?? null,
                    'valor_custo' => $valor['valor_custo'] ?? 0,
                    'valor_venda' => $valor['valor_venda'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Sincroniza variações do produto
     */
    private function sincronizarVariacoes(int $produtoId, array $variacoes): void
    {
        // Remove todas as variações atuais (CASCADE remove os valores também)
        $this->db->executar(
            "DELETE FROM produto_variacoes WHERE produto_id = ?",
            [$produtoId]
        );

        // Insere novas variações
        foreach ($variacoes as $item) {
            $variacao = $item['variacao'] ?? $item;

            if (isset($variacao['nome'])) {
                $variacaoId = $this->db->inserir('produto_variacoes', [
                    'produto_id' => $produtoId,
                    'nome' => $variacao['nome'],
                    'estoque' => $variacao['estoque'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // Insere valores da variação
                if (isset($variacao['valores']) && is_array($variacao['valores'])) {
                    foreach ($variacao['valores'] as $valor) {
                        if (isset($valor['tipo_id']) && isset($valor['nome_tipo'])) {
                            $this->db->inserir('produto_variacao_valores', [
                                'variacao_id' => $variacaoId,
                                'tipo_id' => $valor['tipo_id'],
                                'nome_tipo' => $valor['nome_tipo'],
                                'lucro_utilizado' => $valor['lucro_utilizado'] ?? null,
                                'valor_custo' => $valor['valor_custo'] ?? 0,
                                'valor_venda' => $valor['valor_venda'] ?? 0,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                }
            }
        }
    }
}
