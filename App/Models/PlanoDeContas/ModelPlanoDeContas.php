<?php

namespace App\Models\PlanoDeContas;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar plano de contas
 */
class ModelPlanoDeContas
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma conta por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $conta = $this->db->buscarUm(
            "SELECT * FROM plano_de_contas WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );

        if ($conta) {
            // Busca o nome da conta mãe se houver
            if ($conta['conta_mae_id']) {
                $contaMae = $this->buscarPorId((int) $conta['conta_mae_id']);
                $conta['nome_conta_mae'] = $contaMae ? $contaMae['nome'] : null;
            }
        }

        return $conta;
    }

    /**
     * Busca uma conta por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM plano_de_contas WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca uma conta por classificação
     */
    public function buscarPorClassificacao(string $classificacao): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM plano_de_contas WHERE classificacao = ? AND deletado_em IS NULL",
            [$classificacao]
        );
    }

    /**
     * Lista todas as contas
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT pc.*,
                       pcm.nome as nome_conta_mae
                FROM plano_de_contas pc
                LEFT JOIN plano_de_contas pcm ON pc.conta_mae_id = pcm.id AND pcm.deletado_em IS NULL
                WHERE pc.deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND pc.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo (D=Débito, C=Crédito)
        if (isset($filtros['tipo'])) {
            $sql .= " AND pc.tipo = ?";
            $parametros[] = $filtros['tipo'];
        }

        // Filtro por conta mãe
        if (isset($filtros['conta_mae_id'])) {
            $sql .= " AND pc.conta_mae_id = ?";
            $parametros[] = $filtros['conta_mae_id'];
        }

        // Filtro por nível (contas sem mãe = nível 1)
        if (isset($filtros['nivel']) && $filtros['nivel'] == '1') {
            $sql .= " AND pc.conta_mae_id IS NULL";
        }

        // Busca textual (nome, classificação, tipo)
        if (isset($filtros['busca'])) {
            $sql .= " AND (pc.nome LIKE ? OR pc.classificacao LIKE ? OR pc.nome_tipo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'classificacao', 'tipo', 'nome_tipo',
            'ativo', 'cadastrado_em', 'modificado_em'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'classificacao',
            $filtros['direcao'] ?? 'ASC',
            $camposPermitidos,
            'classificacao'
        );
        $sql .= " ORDER BY pc.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
     * Conta o total de contas
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM plano_de_contas WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo
        if (isset($filtros['tipo'])) {
            $sql .= " AND tipo = ?";
            $parametros[] = $filtros['tipo'];
        }

        // Filtro por conta mãe
        if (isset($filtros['conta_mae_id'])) {
            $sql .= " AND conta_mae_id = ?";
            $parametros[] = $filtros['conta_mae_id'];
        }

        // Filtro por nível
        if (isset($filtros['nivel']) && $filtros['nivel'] == '1') {
            $sql .= " AND conta_mae_id IS NULL";
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR classificacao LIKE ? OR nome_tipo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria uma nova conta
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'conta_mae_id', 'classificacao', 'nome_tipo', 'observacoes'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('plano_de_contas', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'plano_de_contas',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza uma conta
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
            'external_id', 'nome', 'conta_mae_id', 'classificacao',
            'tipo', 'nome_tipo', 'observacoes', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('plano_de_contas', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'plano_de_contas',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta uma conta (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        // Verifica se existem contas filhas
        $contasFilhas = $this->db->buscarTodos(
            "SELECT id FROM plano_de_contas WHERE conta_mae_id = ? AND deletado_em IS NULL",
            [$id]
        );

        if (count($contasFilhas) > 0) {
            throw new \Exception('Não é possível excluir uma conta que possui subcontas');
        }

        $resultado = $this->db->atualizar(
            'plano_de_contas',
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
                'plano_de_contas',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura uma conta deletada
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'plano_de_contas',
            [
                'deletado_em' => null,
                'ativo' => 1
            ],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'restaurar',
                'plano_de_contas',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
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
        $sql = "SELECT COUNT(*) as total FROM plano_de_contas WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se uma classificação já existe
     */
    public function classificacaoExiste(string $classificacao, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM plano_de_contas WHERE classificacao = ? AND deletado_em IS NULL";
        $parametros = [$classificacao];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Lista contas principais (sem conta mãe)
     */
    public function listarContasPrincipais(): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM plano_de_contas
             WHERE conta_mae_id IS NULL AND deletado_em IS NULL AND ativo = 1
             ORDER BY classificacao ASC"
        );
    }

    /**
     * Lista contas filhas de uma conta mãe
     */
    public function listarContasFilhas(int $contaMaeId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM plano_de_contas
             WHERE conta_mae_id = ? AND deletado_em IS NULL
             ORDER BY classificacao ASC",
            [$contaMaeId]
        );
    }

    /**
     * Obtém estatísticas das contas
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de contas ativas
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM plano_de_contas WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativas'] = (int) $resultado['total'];

        // Total por tipo
        $resultado = $this->db->buscarTodos(
            "SELECT tipo, COUNT(*) as total FROM plano_de_contas
             WHERE ativo = 1 AND deletado_em IS NULL
             GROUP BY tipo"
        );
        $stats['por_tipo'] = $resultado;

        // Total de contas principais
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM plano_de_contas
             WHERE conta_mae_id IS NULL AND ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_principais'] = (int) $resultado['total'];

        // Total de subcontas
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM plano_de_contas
             WHERE conta_mae_id IS NOT NULL AND ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_subcontas'] = (int) $resultado['total'];

        return $stats;
    }

    /**
     * Obtém a árvore hierárquica de contas
     */
    public function obterArvore(?int $contaMaeId = null): array
    {
        $sql = "SELECT * FROM plano_de_contas WHERE deletado_em IS NULL";

        if ($contaMaeId === null) {
            $sql .= " AND conta_mae_id IS NULL";
            $parametros = [];
        } else {
            $sql .= " AND conta_mae_id = ?";
            $parametros = [$contaMaeId];
        }

        $sql .= " ORDER BY classificacao ASC";

        $contas = $this->db->buscarTodos($sql, $parametros);

        // Para cada conta, busca suas filhas recursivamente
        foreach ($contas as &$conta) {
            $conta['filhas'] = $this->obterArvore($conta['id']);
        }

        return $contas;
    }
}
