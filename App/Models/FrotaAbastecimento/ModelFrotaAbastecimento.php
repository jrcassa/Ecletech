<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar abastecimentos da frota
 */
class ModelFrotaAbastecimento
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um abastecimento por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca abastecimento com dados completos (joins)
     */
    public function buscarComDetalhes(int $id): ?array
    {
        $sql = "
            SELECT
                fa.*,
                f.nome as frota_nome,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                f.marca as frota_marca,
                f.quilometragem as frota_quilometragem_atual,
                c.nome as motorista_nome,
                c.email as motorista_email,
                c.celular as motorista_celular,
                fp.nome as forma_pagamento_nome,
                criador.nome as criado_por_nome,
                finalizador.nome as finalizado_por_nome,
                s3.id as comprovante_arquivo_id,
                s3.nome_original as comprovante_nome_original,
                s3.tipo_mime as comprovante_tipo_mime
            FROM frotas_abastecimentos fa
            INNER JOIN frotas f ON f.id = fa.frota_id
            INNER JOIN colaboradores c ON c.id = fa.colaborador_id
            LEFT JOIN forma_de_pagamento fp ON fp.id = fa.forma_pagamento_id
            LEFT JOIN colaboradores criador ON criador.id = fa.criado_por
            LEFT JOIN colaboradores finalizador ON finalizador.id = fa.finalizado_por
            LEFT JOIN s3_arquivos s3 ON s3.entidade_tipo = 'frota_abastecimento'
                AND s3.entidade_id = fa.id
                AND s3.categoria = 'comprovante_pagamento'
                AND s3.status != 'deletado'
            WHERE fa.id = ? AND fa.deletado_em IS NULL
        ";

        return $this->db->buscarUm($sql, [$id]);
    }

    /**
     * Lista abastecimentos com filtros e paginação
     */
    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT
                fa.*,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                c.nome as motorista_nome
            FROM frotas_abastecimentos fa
            INNER JOIN frotas f ON f.id = fa.frota_id
            INNER JOIN colaboradores c ON c.id = fa.colaborador_id
            WHERE fa.deletado_em IS NULL
        ";
        $parametros = [];

        // Filtro por colaborador (motorista) - para visualização restrita
        if (isset($filtros['colaborador_id'])) {
            $sql .= " AND fa.colaborador_id = ?";
            $parametros[] = $filtros['colaborador_id'];
        }

        // Filtro por frota
        if (isset($filtros['frota_id'])) {
            $sql .= " AND fa.frota_id = ?";
            $parametros[] = $filtros['frota_id'];
        }

        // Filtro por status
        if (isset($filtros['status'])) {
            $sql .= " AND fa.status = ?";
            $parametros[] = $filtros['status'];
        }

        // Filtro por período
        if (isset($filtros['data_inicio'])) {
            $sql .= " AND fa.data_abastecimento >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND fa.data_abastecimento <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        // Filtro por combustível
        if (isset($filtros['combustivel'])) {
            $sql .= " AND fa.combustivel = ?";
            $parametros[] = $filtros['combustivel'];
        }

        // Ordenação
        $camposPermitidos = ['id', 'data_abastecimento', 'data_limite', 'km', 'valor', 'litros', 'status', 'criado_em'];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'criado_em',
            $filtros['direcao'] ?? 'DESC',
            $camposPermitidos,
            'criado_em'
        );
        $sql .= " ORDER BY fa.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
     * Conta total de abastecimentos
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM frotas_abastecimentos fa WHERE fa.deletado_em IS NULL";
        $parametros = [];

        // Aplicar os mesmos filtros do listar
        if (isset($filtros['colaborador_id'])) {
            $sql .= " AND fa.colaborador_id = ?";
            $parametros[] = $filtros['colaborador_id'];
        }

        if (isset($filtros['frota_id'])) {
            $sql .= " AND fa.frota_id = ?";
            $parametros[] = $filtros['frota_id'];
        }

        if (isset($filtros['status'])) {
            $sql .= " AND fa.status = ?";
            $parametros[] = $filtros['status'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND fa.data_abastecimento >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND fa.data_abastecimento <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        if (isset($filtros['combustivel'])) {
            $sql .= " AND fa.combustivel = ?";
            $parametros[] = $filtros['combustivel'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Busca ordens pendentes de um motorista
     */
    public function buscarOrdensPendentes(int $colaborador_id): array
    {
        $sql = "
            SELECT
                fa.*,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                f.marca as frota_marca,
                f.quilometragem as frota_quilometragem_atual,
                criador.nome as criado_por_nome
            FROM frotas_abastecimentos fa
            INNER JOIN frotas f ON f.id = fa.frota_id
            LEFT JOIN colaboradores criador ON criador.id = fa.criado_por
            WHERE fa.colaborador_id = ?
            AND fa.status = 'aguardando'
            AND fa.deletado_em IS NULL
            ORDER BY fa.data_limite ASC, fa.criado_em ASC
        ";

        return $this->db->buscarTodos($sql, [$colaborador_id]);
    }

    /**
     * Busca histórico de abastecimentos de um motorista
     */
    public function buscarHistoricoMotorista(int $colaborador_id, int $limite = 20): array
    {
        $sql = "
            SELECT
                fa.*,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                f.marca as frota_marca,
                fp.nome as forma_pagamento_nome
            FROM frotas_abastecimentos fa
            INNER JOIN frotas f ON f.id = fa.frota_id
            LEFT JOIN forma_de_pagamento fp ON fp.id = fa.forma_pagamento_id
            WHERE fa.colaborador_id = ?
            AND fa.status = 'abastecido'
            AND fa.deletado_em IS NULL
            ORDER BY fa.data_abastecimento DESC
            LIMIT ?
        ";

        return $this->db->buscarTodos($sql, [$colaborador_id, $limite]);
    }

    /**
     * Verifica se já existe ordem aguardando para frota/motorista
     */
    public function buscarOrdemAguardando(int $frota_id, int $colaborador_id): ?array
    {
        $sql = "
            SELECT id
            FROM frotas_abastecimentos
            WHERE frota_id = ?
            AND colaborador_id = ?
            AND status = 'aguardando'
            AND deletado_em IS NULL
            LIMIT 1
        ";

        return $this->db->buscarUm($sql, [$frota_id, $colaborador_id]);
    }

    /**
     * Busca último abastecimento de uma frota
     */
    public function buscarUltimoAbastecimentoFrota(int $frota_id): ?array
    {
        $sql = "
            SELECT km, data_abastecimento
            FROM frotas_abastecimentos
            WHERE frota_id = ?
            AND status = 'abastecido'
            AND km IS NOT NULL
            AND deletado_em IS NULL
            ORDER BY km DESC
            LIMIT 1
        ";

        return $this->db->buscarUm($sql, [$frota_id]);
    }

    /**
     * Cria nova ordem de abastecimento
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos (
                frota_id, colaborador_id, data_limite, observacao_admin,
                status, criado_por, criado_em
            ) VALUES (?, ?, ?, ?, 'aguardando', ?, NOW())
        ";

        $parametros = [
            $dados['frota_id'],
            $dados['colaborador_id'],
            $dados['data_limite'] ?? null,
            $dados['observacao_admin'] ?? null,
            $dados['criado_por']
        ];

        $this->db->executar($sql, $parametros);
        $id = (int) $this->db->obterConexao()->lastInsertId();

        // Registrar auditoria
        $this->auditoria->registrar('criar', 'frotas_abastecimentos', $id, null, [
            'frota_id' => $dados['frota_id'],
            'colaborador_id' => $dados['colaborador_id']
        ]);

        return $id;
    }

    /**
     * Atualiza ordem de abastecimento
     */
    public function atualizar(int $id, array $dados): bool
    {
        $campos = [];
        $parametros = [];

        // Campos atualizáveis conforme contexto
        $camposPermitidos = [
            'data_limite', 'observacao_admin', 'km', 'litros', 'combustivel',
            'valor', 'preco_por_litro', 'forma_pagamento_id', 'data_abastecimento',
            'latitude', 'longitude', 'endereco_formatado', 'observacao_motorista',
            'status', 'finalizado_em', 'finalizado_por'
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $campos[] = "{$campo} = ?";
                $parametros[] = $dados[$campo];
            }
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE frotas_abastecimentos SET " . implode(', ', $campos) . " WHERE id = ?";
        $parametros[] = $id;

        $this->db->executar($sql, $parametros);

        // Registrar auditoria
        $this->auditoria->registrar('atualizar', 'frotas_abastecimentos', $id, null, $dados);

        return true;
    }

    /**
     * Marca ordem como finalizada
     */
    public function marcarComoFinalizado(int $id, array $dados): bool
    {
        // Calcular preço por litro
        $precoPorLitro = null;
        if (isset($dados['valor']) && isset($dados['litros']) && $dados['litros'] > 0) {
            $precoPorLitro = $dados['valor'] / $dados['litros'];
        }

        $sql = "
            UPDATE frotas_abastecimentos SET
                km = ?,
                litros = ?,
                combustivel = ?,
                valor = ?,
                preco_por_litro = ?,
                forma_pagamento_id = ?,
                data_abastecimento = ?,
                latitude = ?,
                longitude = ?,
                endereco_formatado = ?,
                observacao_motorista = ?,
                status = 'abastecido',
                finalizado_em = NOW(),
                finalizado_por = ?
            WHERE id = ?
        ";

        $parametros = [
            $dados['km'],
            $dados['litros'],
            $dados['combustivel'],
            $dados['valor'],
            $precoPorLitro,
            $dados['forma_pagamento_id'] ?? null,
            $dados['data_abastecimento'],
            $dados['latitude'] ?? null,
            $dados['longitude'] ?? null,
            $dados['endereco_formatado'] ?? null,
            $dados['observacao_motorista'] ?? null,
            $dados['finalizado_por'],
            $id
        ];

        $this->db->executar($sql, $parametros);

        // Registrar auditoria
        $this->auditoria->registrar('finalizar', 'frotas_abastecimentos', $id, null, $dados);

        return true;
    }

    /**
     * Marca ordem como cancelada
     */
    public function marcarComoCancelado(int $id, ?string $observacao = null): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos SET
                status = 'cancelado',
                observacao_admin = CONCAT(COALESCE(observacao_admin, ''), ' [CANCELADO: ', COALESCE(?, 'Sem motivo informado'), ']')
            WHERE id = ?
        ";

        $this->db->executar($sql, [$observacao, $id]);

        // Registrar auditoria
        $this->auditoria->registrar('cancelar', 'frotas_abastecimentos', $id, null, ['observacao' => $observacao]);

        return true;
    }

    /**
     * Marca ordens expiradas (cron job)
     */
    public function marcarExpirados(): int
    {
        $sql = "
            UPDATE frotas_abastecimentos
            SET status = 'expirado'
            WHERE status = 'aguardando'
            AND data_limite < CURDATE()
            AND deletado_em IS NULL
        ";

        $stmt = $this->db->executar($sql);
        return $stmt->rowCount();
    }

    /**
     * Marca notificação ao motorista como enviada
     */
    public function marcarNotificacaoMotoristaEnviada(int $id): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos SET
                notificacao_motorista_enviada = TRUE,
                notificacao_motorista_enviada_em = NOW()
            WHERE id = ?
        ";

        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Marca notificação aos admins como enviada
     */
    public function marcarNotificacaoAdminEnviada(int $id): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos SET
                notificacao_admin_enviada = TRUE,
                notificacao_admin_enviada_em = NOW()
            WHERE id = ?
        ";

        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Soft delete
     */
    public function deletar(int $id): bool
    {
        $sql = "UPDATE frotas_abastecimentos SET deletado_em = NOW() WHERE id = ?";
        $this->db->executar($sql, [$id]);

        // Registrar auditoria
        $this->auditoria->registrar('deletar', 'frotas_abastecimentos', $id, null, []);

        return true;
    }

    /**
     * Restaurar soft delete
     */
    public function restaurar(int $id): bool
    {
        $sql = "UPDATE frotas_abastecimentos SET deletado_em = NULL WHERE id = ?";
        $this->db->executar($sql, [$id]);

        // Registrar auditoria
        $this->auditoria->registrar('restaurar', 'frotas_abastecimentos', $id, null, []);

        return true;
    }

    /**
     * Obter estatísticas gerais
     */
    public function obterEstatisticas(array $filtros = []): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_abastecimentos,
                SUM(CASE WHEN status = 'aguardando' THEN 1 ELSE 0 END) as total_aguardando,
                SUM(CASE WHEN status = 'abastecido' THEN 1 ELSE 0 END) as total_abastecido,
                SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as total_cancelado,
                SUM(CASE WHEN status = 'expirado' THEN 1 ELSE 0 END) as total_expirado,
                COALESCE(SUM(litros), 0) as total_litros,
                COALESCE(SUM(valor), 0) as total_valor,
                COALESCE(AVG(valor), 0) as valor_medio
            FROM frotas_abastecimentos
            WHERE deletado_em IS NULL
            AND status = 'abastecido'
        ";

        $parametros = [];

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND data_abastecimento >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND data_abastecimento <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        return $this->db->buscarUm($sql, $parametros) ?: [];
    }
}
