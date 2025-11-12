<?php

namespace App\Models\Produtos;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar produtos
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
     * Busca um produto por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT p.*, g.nome as nome_grupo
             FROM produtos p
             LEFT JOIN grupos_produtos g ON p.grupo_id = g.id
             WHERE p.id = ? AND p.deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um produto por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT p.*, g.nome as nome_grupo
             FROM produtos p
             LEFT JOIN grupos_produtos g ON p.grupo_id = g.id
             WHERE p.external_id = ? AND p.deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca um produto por código interno
     */
    public function buscarPorCodigoInterno(string $codigoInterno): ?array
    {
        return $this->db->buscarUm(
            "SELECT p.*, g.nome as nome_grupo
             FROM produtos p
             LEFT JOIN grupos_produtos g ON p.grupo_id = g.id
             WHERE p.codigo_interno = ? AND p.deletado_em IS NULL",
            [$codigoInterno]
        );
    }

    /**
     * Lista todos os produtos com relacionamentos
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT p.*, g.nome as nome_grupo
                FROM produtos p
                LEFT JOIN grupos_produtos g ON p.grupo_id = g.id
                WHERE p.deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND p.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por grupo
        if (isset($filtros['grupo_id']) && $filtros['grupo_id'] !== '') {
            $sql .= " AND p.grupo_id = ?";
            $parametros[] = $filtros['grupo_id'];
        }

        // Busca textual (nome, código interno, código de barras)
        if (isset($filtros['busca'])) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo_interno LIKE ? OR p.codigo_barra LIKE ? OR p.descricao LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'codigo_interno', 'estoque', 'valor_venda', 'ativo', 'cadastrado_em', 'modificado_em'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'nome',
            $filtros['direcao'] ?? 'ASC',
            $camposPermitidos,
            'nome'
        );
        $sql .= " ORDER BY p.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
        $sql = "SELECT COUNT(*) as total FROM produtos p WHERE p.deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND p.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por grupo
        if (isset($filtros['grupo_id']) && $filtros['grupo_id'] !== '') {
            $sql .= " AND p.grupo_id = ?";
            $parametros[] = $filtros['grupo_id'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo_interno LIKE ? OR p.codigo_barra LIKE ? OR p.descricao LIKE ?)";
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
            'possui_variacao' => $dados['possui_variacao'] ?? 0,
            'possui_composicao' => $dados['possui_composicao'] ?? 0,
            'movimenta_estoque' => $dados['movimenta_estoque'] ?? 1,
            'estoque' => $dados['estoque'] ?? 0,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'codigo_interno', 'codigo_barra',
            'peso', 'largura', 'altura', 'comprimento',
            'grupo_id', 'descricao', 'valor_custo', 'valor_venda'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('produtos', $dadosInsert);

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
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'external_id', 'nome', 'codigo_interno', 'codigo_barra',
            'possui_variacao', 'possui_composicao', 'movimenta_estoque',
            'peso', 'largura', 'altura', 'comprimento',
            'grupo_id', 'descricao', 'estoque', 'valor_custo', 'valor_venda', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('produtos', $dadosUpdate, 'id = ?', [$id]);

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
     * Deleta um produto (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'produtos',
            [
                'deletado_em' => date('Y-m-d H:i:s'),
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
                ['deletado_em' => date('Y-m-d H:i:s')],
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
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE external_id = ? AND deletado_em IS NULL";
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
        $sql = "SELECT COUNT(*) as total FROM produtos WHERE codigo_interno = ? AND deletado_em IS NULL";
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
            "SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total de produtos inativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM produtos WHERE ativo = 0 AND deletado_em IS NULL"
        );
        $stats['total_inativos'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativos'] + $stats['total_inativos'];

        // Valor total em estoque
        $resultado = $this->db->buscarUm(
            "SELECT SUM(estoque * valor_venda) as total FROM produtos WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['valor_total_estoque'] = (float) ($resultado['total'] ?? 0);

        return $stats;
    }
}
