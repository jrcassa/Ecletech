<?php

namespace App\Models\Frota;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar a frota de veículos
 */
class ModelFrota
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um veículo por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um veículo por placa
     */
    public function buscarPorPlaca(string $placa): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas WHERE placa = ? AND deletado_em IS NULL",
            [$placa]
        );
    }

    /**
     * Busca um veículo por chassi
     */
    public function buscarPorChassi(string $chassi): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas WHERE chassi = ? AND deletado_em IS NULL",
            [$chassi]
        );
    }

    /**
     * Busca um veículo por RENAVAM
     */
    public function buscarPorRenavam(string $renavam): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas WHERE renavam = ? AND deletado_em IS NULL",
            [$renavam]
        );
    }

    /**
     * Lista todos os veículos da frota
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM frotas WHERE deletado_em IS NULL";
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

        // Filtro por status
        if (isset($filtros['status'])) {
            $sql .= " AND status = ?";
            $parametros[] = $filtros['status'];
        }

        // Filtro por marca
        if (isset($filtros['marca'])) {
            $sql .= " AND marca = ?";
            $parametros[] = $filtros['marca'];
        }

        // Filtro por modelo
        if (isset($filtros['modelo'])) {
            $sql .= " AND modelo = ?";
            $parametros[] = $filtros['modelo'];
        }

        // Busca textual (nome, placa, marca, modelo)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR placa LIKE ? OR marca LIKE ? OR modelo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'tipo', 'placa', 'status', 'marca', 'modelo',
            'ano_fabricacao', 'ano_modelo', 'cor', 'quilometragem',
            'data_aquisicao', 'criado_em', 'atualizado_em'
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
     * Conta o total de veículos na frota
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM frotas WHERE deletado_em IS NULL";
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

        // Filtro por status
        if (isset($filtros['status'])) {
            $sql .= " AND status = ?";
            $parametros[] = $filtros['status'];
        }

        // Filtro por marca
        if (isset($filtros['marca'])) {
            $sql .= " AND marca = ?";
            $parametros[] = $filtros['marca'];
        }

        // Filtro por modelo
        if (isset($filtros['modelo'])) {
            $sql .= " AND modelo = ?";
            $parametros[] = $filtros['modelo'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR placa LIKE ? OR marca LIKE ? OR modelo LIKE ?)";
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
     * Cria um novo veículo na frota
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'placa' => $dados['placa'],
            'status' => $dados['status'] ?? 'ativo',
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'marca', 'modelo', 'ano_fabricacao', 'ano_modelo', 'cor',
            'chassi', 'renavam', 'quilometragem', 'capacidade_tanque',
            'data_aquisicao', 'valor_aquisicao', 'observacoes'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo])) {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('frotas', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'frota',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um veículo da frota
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'nome', 'tipo', 'placa', 'status', 'marca', 'modelo',
            'ano_fabricacao', 'ano_modelo', 'cor', 'chassi', 'renavam',
            'quilometragem', 'capacidade_tanque', 'data_aquisicao',
            'valor_aquisicao', 'observacoes', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('frotas', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'frota',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um veículo (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'frotas',
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
                'frota',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um veículo deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'frotas',
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
                'frota',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se uma placa já existe
     */
    public function placaExiste(string $placa, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM frotas WHERE placa = ? AND deletado_em IS NULL";
        $parametros = [$placa];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um chassi já existe
     */
    public function chassiExiste(string $chassi, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM frotas WHERE chassi = ? AND deletado_em IS NULL";
        $parametros = [$chassi];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um RENAVAM já existe
     */
    public function renavamExiste(string $renavam, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM frotas WHERE renavam = ? AND deletado_em IS NULL";
        $parametros = [$renavam];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Atualiza a quilometragem de um veículo
     */
    public function atualizarQuilometragem(int $id, int $quilometragem, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'frotas',
            [
                'quilometragem' => $quilometragem,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar_quilometragem',
                'frota',
                $id,
                ['quilometragem' => $dadosAtuais['quilometragem']],
                ['quilometragem' => $quilometragem],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Atualiza o status de um veículo
     */
    public function atualizarStatus(int $id, string $status, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'frotas',
            [
                'status' => $status,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar_status',
                'frota',
                $id,
                ['status' => $dadosAtuais['status']],
                ['status' => $status],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Obtém estatísticas da frota
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de veículos ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM frotas WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total por tipo
        $resultado = $this->db->buscarTodos(
            "SELECT tipo, COUNT(*) as total FROM frotas WHERE ativo = 1 AND deletado_em IS NULL GROUP BY tipo"
        );
        $stats['por_tipo'] = $resultado;

        // Total por status
        $resultado = $this->db->buscarTodos(
            "SELECT status, COUNT(*) as total FROM frotas WHERE ativo = 1 AND deletado_em IS NULL GROUP BY status"
        );
        $stats['por_status'] = $resultado;

        // Veículos em manutenção
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM frotas WHERE status = 'manutencao' AND ativo = 1 AND deletado_em IS NULL"
        );
        $stats['em_manutencao'] = (int) $resultado['total'];

        return $stats;
    }
}
