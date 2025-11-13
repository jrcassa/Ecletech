<?php

namespace App\Models\ErroLog;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciamento de logs de erros
 *
 * Registra todos os erros do sistema de forma detalhada,
 * incluindo stack trace, contexto e informações do usuário
 */
class ModelErroLog
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Cria um novo registro de erro
     *
     * @param array $dados Dados do erro
     * @return int ID do erro criado
     */
    public function criar(array $dados): int
    {
        try {
            $sql = "INSERT INTO erros_log (
                tipo_erro,
                nivel,
                mensagem,
                stack_trace,
                arquivo,
                linha,
                codigo_erro,
                tipo_entidade,
                entidade_id,
                contexto,
                usuario_id,
                url,
                metodo_http,
                ip_address,
                user_agent
            ) VALUES (
                :tipo_erro,
                :nivel,
                :mensagem,
                :stack_trace,
                :arquivo,
                :linha,
                :codigo_erro,
                :tipo_entidade,
                :entidade_id,
                :contexto,
                :usuario_id,
                :url,
                :metodo_http,
                :ip_address,
                :user_agent
            )";

            $params = [
                ':tipo_erro' => $dados['tipo_erro'] ?? 'exception',
                ':nivel' => $dados['nivel'] ?? 'medio',
                ':mensagem' => $dados['mensagem'],
                ':stack_trace' => $dados['stack_trace'] ?? null,
                ':arquivo' => $dados['arquivo'] ?? null,
                ':linha' => $dados['linha'] ?? null,
                ':codigo_erro' => $dados['codigo_erro'] ?? null,
                ':tipo_entidade' => $dados['tipo_entidade'] ?? null,
                ':entidade_id' => $dados['entidade_id'] ?? null,
                ':contexto' => isset($dados['contexto']) ? json_encode($dados['contexto']) : null,
                ':usuario_id' => $dados['usuario_id'] ?? null,
                ':url' => $dados['url'] ?? null,
                ':metodo_http' => $dados['metodo_http'] ?? null,
                ':ip_address' => $dados['ip_address'] ?? null,
                ':user_agent' => $dados['user_agent'] ?? null
            ];

            $stmt = $this->db->executar($sql, $params);
            $id = (int) $this->db->obterUltimoId();

            // Registra auditoria
            $this->auditoria->registrarCriacao('erros_log', $id, $dados);

            return $id;
        } catch (\Exception $e) {
            // Registra no error_log do PHP para evitar loop infinito
            error_log("Erro ao salvar log de erro: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Busca um erro por ID
     *
     * @param int $id ID do erro
     * @return array|null Dados do erro ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM erros_log
                WHERE id = :id AND deletado_em IS NULL";

        $stmt = $this->db->executar($sql, [':id' => $id]);
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($resultado && isset($resultado['contexto'])) {
            $resultado['contexto'] = json_decode($resultado['contexto'], true);
        }

        return $resultado ?: null;
    }

    /**
     * Lista erros com filtros
     *
     * @param array $filtros Filtros de busca
     * @return array Lista de erros
     */
    public function listar(array $filtros = []): array
    {
        $where = ['deletado_em IS NULL'];
        $params = [];

        // Filtro por tipo de erro
        if (!empty($filtros['tipo_erro'])) {
            $where[] = 'tipo_erro = :tipo_erro';
            $params[':tipo_erro'] = $filtros['tipo_erro'];
        }

        // Filtro por nível
        if (!empty($filtros['nivel'])) {
            $where[] = 'nivel = :nivel';
            $params[':nivel'] = $filtros['nivel'];
        }

        // Filtro por entidade
        if (!empty($filtros['tipo_entidade'])) {
            $where[] = 'tipo_entidade = :tipo_entidade';
            $params[':tipo_entidade'] = $filtros['tipo_entidade'];
        }

        if (!empty($filtros['entidade_id'])) {
            $where[] = 'entidade_id = :entidade_id';
            $params[':entidade_id'] = $filtros['entidade_id'];
        }

        // Filtro por usuário
        if (!empty($filtros['usuario_id'])) {
            $where[] = 'usuario_id = :usuario_id';
            $params[':usuario_id'] = $filtros['usuario_id'];
        }

        // Filtro por resolução
        if (isset($filtros['resolvido'])) {
            $where[] = 'resolvido = :resolvido';
            $params[':resolvido'] = $filtros['resolvido'] ? 1 : 0;
        }

        // Filtro por período
        if (!empty($filtros['data_inicio'])) {
            $where[] = 'criado_em >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = 'criado_em <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }

        // Busca por mensagem
        if (!empty($filtros['busca'])) {
            $where[] = '(mensagem LIKE :busca OR stack_trace LIKE :busca OR arquivo LIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Ordenação
        $orderBy = $filtros['order_by'] ?? 'criado_em';
        $orderDir = $filtros['order_dir'] ?? 'DESC';

        // Paginação
        $limit = $filtros['limit'] ?? 50;
        $offset = $filtros['offset'] ?? 0;

        $sql = "SELECT * FROM erros_log
                WHERE {$whereClause}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->executar($sql, $params);
        $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decodifica JSON do contexto
        foreach ($resultados as &$resultado) {
            if (isset($resultado['contexto'])) {
                $resultado['contexto'] = json_decode($resultado['contexto'], true);
            }
        }

        return $resultados;
    }

    /**
     * Conta erros com filtros
     *
     * @param array $filtros Filtros de busca
     * @return int Total de erros
     */
    public function contar(array $filtros = []): int
    {
        $where = ['deletado_em IS NULL'];
        $params = [];

        // Mesmos filtros da listagem
        if (!empty($filtros['tipo_erro'])) {
            $where[] = 'tipo_erro = :tipo_erro';
            $params[':tipo_erro'] = $filtros['tipo_erro'];
        }

        if (!empty($filtros['nivel'])) {
            $where[] = 'nivel = :nivel';
            $params[':nivel'] = $filtros['nivel'];
        }

        if (!empty($filtros['tipo_entidade'])) {
            $where[] = 'tipo_entidade = :tipo_entidade';
            $params[':tipo_entidade'] = $filtros['tipo_entidade'];
        }

        if (!empty($filtros['entidade_id'])) {
            $where[] = 'entidade_id = :entidade_id';
            $params[':entidade_id'] = $filtros['entidade_id'];
        }

        if (!empty($filtros['usuario_id'])) {
            $where[] = 'usuario_id = :usuario_id';
            $params[':usuario_id'] = $filtros['usuario_id'];
        }

        if (isset($filtros['resolvido'])) {
            $where[] = 'resolvido = :resolvido';
            $params[':resolvido'] = $filtros['resolvido'] ? 1 : 0;
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = 'criado_em >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = 'criado_em <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }

        if (!empty($filtros['busca'])) {
            $where[] = '(mensagem LIKE :busca OR stack_trace LIKE :busca OR arquivo LIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) as total FROM erros_log WHERE {$whereClause}";

        $stmt = $this->db->executar($sql, $params);
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $resultado['total'];
    }

    /**
     * Marca um erro como resolvido
     *
     * @param int $id ID do erro
     * @param int $usuarioId ID do usuário que resolveu
     * @param string $notas Notas sobre a resolução
     * @return bool Sucesso da operação
     */
    public function marcarComoResolvido(int $id, int $usuarioId, string $notas = ''): bool
    {
        $sql = "UPDATE erros_log
                SET resolvido = 1,
                    resolvido_em = NOW(),
                    resolvido_por = :usuario_id,
                    notas_resolucao = :notas
                WHERE id = :id AND deletado_em IS NULL";

        $params = [
            ':id' => $id,
            ':usuario_id' => $usuarioId,
            ':notas' => $notas
        ];

        $stmt = $this->db->executar($sql, $params);

        // Registra auditoria
        $this->auditoria->registrarAtualizacao('erros_log', $id, [
            'resolvido' => 1,
            'resolvido_por' => $usuarioId,
            'notas_resolucao' => $notas
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Busca erros de uma entidade específica
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array Lista de erros
     */
    public function buscarPorEntidade(string $tipo, int $id): array
    {
        $sql = "SELECT * FROM erros_log
                WHERE tipo_entidade = :tipo
                AND entidade_id = :id
                AND deletado_em IS NULL
                ORDER BY criado_em DESC";

        $stmt = $this->db->executar($sql, [
            ':tipo' => $tipo,
            ':id' => $id
        ]);

        $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($resultados as &$resultado) {
            if (isset($resultado['contexto'])) {
                $resultado['contexto'] = json_decode($resultado['contexto'], true);
            }
        }

        return $resultados;
    }

    /**
     * Obtém estatísticas de erros
     *
     * @param array $filtros Filtros de período
     * @return array Estatísticas
     */
    public function estatisticas(array $filtros = []): array
    {
        $where = ['deletado_em IS NULL'];
        $params = [];

        if (!empty($filtros['data_inicio'])) {
            $where[] = 'criado_em >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = 'criado_em <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }

        $whereClause = implode(' AND ', $where);

        // Total de erros
        $sqlTotal = "SELECT COUNT(*) as total FROM erros_log WHERE {$whereClause}";
        $stmtTotal = $this->db->executar($sqlTotal, $params);
        $total = (int) $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];

        // Erros por tipo
        $sqlPorTipo = "SELECT tipo_erro, COUNT(*) as total
                       FROM erros_log
                       WHERE {$whereClause}
                       GROUP BY tipo_erro";
        $stmtPorTipo = $this->db->executar($sqlPorTipo, $params);
        $porTipo = $stmtPorTipo->fetchAll(\PDO::FETCH_ASSOC);

        // Erros por nível
        $sqlPorNivel = "SELECT nivel, COUNT(*) as total
                        FROM erros_log
                        WHERE {$whereClause}
                        GROUP BY nivel";
        $stmtPorNivel = $this->db->executar($sqlPorNivel, $params);
        $porNivel = $stmtPorNivel->fetchAll(\PDO::FETCH_ASSOC);

        // Erros resolvidos vs não resolvidos
        $sqlResolvidos = "SELECT resolvido, COUNT(*) as total
                          FROM erros_log
                          WHERE {$whereClause}
                          GROUP BY resolvido";
        $stmtResolvidos = $this->db->executar($sqlResolvidos, $params);
        $resolvidos = $stmtResolvidos->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'por_tipo' => $porTipo,
            'por_nivel' => $porNivel,
            'por_status_resolucao' => $resolvidos
        ];
    }

    /**
     * Soft delete de um erro
     *
     * @param int $id ID do erro
     * @return bool Sucesso da operação
     */
    public function deletar(int $id): bool
    {
        $sql = "UPDATE erros_log
                SET deletado_em = NOW()
                WHERE id = :id AND deletado_em IS NULL";

        $stmt = $this->db->executar($sql, [':id' => $id]);

        // Registra auditoria
        $this->auditoria->registrarDelecao('erros_log', $id);

        return $stmt->rowCount() > 0;
    }

    /**
     * Limpa erros antigos (hard delete)
     *
     * @param int $dias Idade dos erros em dias
     * @return int Quantidade de erros removidos
     */
    public function limparAntigos(int $dias = 90): int
    {
        $sql = "DELETE FROM erros_log
                WHERE criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)
                OR deletado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";

        $stmt = $this->db->executar($sql, [':dias' => $dias]);

        return $stmt->rowCount();
    }
}
