<?php

namespace App\Models\Fornecedor;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar fornecedores (PF e PJ)
 */
class ModelFornecedor
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um fornecedor por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um fornecedor por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca um fornecedor por CNPJ
     */
    public function buscarPorCnpj(string $cnpj): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores WHERE cnpj = ? AND deletado_em IS NULL",
            [$cnpj]
        );
    }

    /**
     * Busca um fornecedor por CPF
     */
    public function buscarPorCpf(string $cpf): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores WHERE cpf = ? AND deletado_em IS NULL",
            [$cpf]
        );
    }

    /**
     * Busca um fornecedor por email
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores WHERE email = ? AND deletado_em IS NULL",
            [$email]
        );
    }

    /**
     * Busca fornecedor com relacionamentos (contatos e endereços)
     */
    public function buscarComRelacionamentos(int $id): ?array
    {
        $fornecedor = $this->buscarPorId($id);
        if (!$fornecedor) {
            return null;
        }

        // Busca contatos
        $fornecedor['contatos'] = $this->db->buscarTodos(
            "SELECT * FROM fornecedores_contatos WHERE fornecedor_id = ? ORDER BY id",
            [$id]
        );

        // Busca endereços com informações da cidade
        $fornecedor['enderecos'] = $this->db->buscarTodos(
            "SELECT
                fe.*,
                c.nome as nome_cidade,
                c.estado_id
            FROM fornecedores_enderecos fe
            LEFT JOIN cidades c ON fe.cidade_id = c.id
            WHERE fe.fornecedor_id = ?
            ORDER BY fe.id",
            [$id]
        );

        return $fornecedor;
    }

    /**
     * Lista todos os fornecedores
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM fornecedores WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo de pessoa
        if (isset($filtros['tipo_pessoa'])) {
            $sql .= " AND tipo_pessoa = ?";
            $parametros[] = $filtros['tipo_pessoa'];
        }

        // Busca textual (nome, razão social, CNPJ, CPF, email)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR razao_social LIKE ? OR cnpj LIKE ? OR cpf LIKE ? OR email LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'razao_social', 'tipo_pessoa', 'cnpj', 'cpf',
            'email', 'telefone', 'ativo', 'cadastrado_em', 'modificado_em'
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
     * Conta o total de fornecedores
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo de pessoa
        if (isset($filtros['tipo_pessoa'])) {
            $sql .= " AND tipo_pessoa = ?";
            $parametros[] = $filtros['tipo_pessoa'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR razao_social LIKE ? OR cnpj LIKE ? OR cpf LIKE ? OR email LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria um novo fornecedor
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'tipo_pessoa' => $dados['tipo_pessoa'],
            'nome' => $dados['nome'],
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'razao_social', 'cnpj', 'inscricao_estadual',
            'inscricao_municipal', 'tipo_contribuinte', 'cpf', 'rg',
            'data_nascimento', 'telefone', 'celular', 'email'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('fornecedores', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'fornecedor',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um fornecedor
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
            'external_id', 'tipo_pessoa', 'nome', 'razao_social', 'cnpj',
            'inscricao_estadual', 'inscricao_municipal', 'tipo_contribuinte',
            'cpf', 'rg', 'data_nascimento', 'telefone', 'celular', 'email', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('fornecedores', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'fornecedor',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um fornecedor (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'fornecedores',
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
                'fornecedor',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um fornecedor deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'fornecedores',
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
                'fornecedor',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um CNPJ já existe
     */
    public function cnpjExiste(string $cnpj, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE cnpj = ? AND deletado_em IS NULL";
        $parametros = [$cnpj];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um CPF já existe
     */
    public function cpfExiste(string $cpf, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE cpf = ? AND deletado_em IS NULL";
        $parametros = [$cpf];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um email já existe
     */
    public function emailExiste(string $email, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE email = ? AND deletado_em IS NULL";
        $parametros = [$email];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um external_id já existe
     */
    public function externalIdExiste(string $externalId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas dos fornecedores
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de fornecedores ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM fornecedores WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total por tipo de pessoa
        $resultado = $this->db->buscarTodos(
            "SELECT tipo_pessoa, COUNT(*) as total FROM fornecedores WHERE ativo = 1 AND deletado_em IS NULL GROUP BY tipo_pessoa"
        );
        $stats['por_tipo_pessoa'] = $resultado;

        // Total de fornecedores PF
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM fornecedores WHERE tipo_pessoa = 'PF' AND ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_pf'] = (int) $resultado['total'];

        // Total de fornecedores PJ
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM fornecedores WHERE tipo_pessoa = 'PJ' AND ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_pj'] = (int) $resultado['total'];

        return $stats;
    }
}
