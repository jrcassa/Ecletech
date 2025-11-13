<?php

namespace App\Models\S3;

use App\Core\BancoDados;

/**
 * Model para gerenciar registros de arquivos S3
 * Mantém controle de todos os arquivos armazenados
 */
class ModelS3Arquivo
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria um novo registro de arquivo
     */
    public function criar(array $dados): ?int
    {
        // Gera UUID se não fornecido
        if (empty($dados['uuid'])) {
            $dados['uuid'] = $this->gerarUuid();
        }

        $sql = "INSERT INTO s3_arquivos (
                    uuid, nome_original, nome_s3, caminho_s3, bucket,
                    tipo_mime, extensao, tamanho_bytes, hash_md5,
                    acl, url_publica, metadata,
                    entidade_tipo, entidade_id, categoria,
                    criado_por, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $parametros = [
            $dados['uuid'],
            $dados['nome_original'],
            $dados['nome_s3'],
            $dados['caminho_s3'],
            $dados['bucket'],
            $dados['tipo_mime'] ?? null,
            $dados['extensao'] ?? null,
            $dados['tamanho_bytes'] ?? null,
            $dados['hash_md5'] ?? null,
            $dados['acl'] ?? 'private',
            $dados['url_publica'] ?? null,
            isset($dados['metadata']) ? json_encode($dados['metadata']) : null,
            $dados['entidade_tipo'] ?? null,
            $dados['entidade_id'] ?? null,
            $dados['categoria'] ?? null,
            $dados['criado_por'] ?? null,
            $dados['status'] ?? 'ativo'
        ];

        if ($this->db->executar($sql, $parametros)) {
            return (int) $this->db->obterUltimoId();
        }

        return null;
    }

    /**
     * Busca arquivo por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM s3_arquivos WHERE id = ? AND status != 'deletado'",
            [$id]
        );
    }

    /**
     * Busca arquivo por UUID
     */
    public function buscarPorUuid(string $uuid): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM s3_arquivos WHERE uuid = ? AND status != 'deletado'",
            [$uuid]
        );
    }

    /**
     * Busca arquivo por caminho S3
     */
    public function buscarPorCaminhoS3(string $bucket, string $caminhoS3): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM s3_arquivos WHERE bucket = ? AND caminho_s3 = ? AND status != 'deletado'",
            [$bucket, $caminhoS3]
        );
    }

    /**
     * Lista todos os arquivos com filtros opcionais
     */
    public function listar(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM s3_arquivos WHERE status != 'deletado'";
        $parametros = [];

        // Aplica filtros
        if (!empty($filtros['bucket'])) {
            $sql .= " AND bucket = ?";
            $parametros[] = $filtros['bucket'];
        }

        if (!empty($filtros['entidade_tipo'])) {
            $sql .= " AND entidade_tipo = ?";
            $parametros[] = $filtros['entidade_tipo'];
        }

        if (!empty($filtros['entidade_id'])) {
            $sql .= " AND entidade_id = ?";
            $parametros[] = $filtros['entidade_id'];
        }

        if (!empty($filtros['categoria'])) {
            $sql .= " AND categoria = ?";
            $parametros[] = $filtros['categoria'];
        }

        if (!empty($filtros['tipo_mime'])) {
            $sql .= " AND tipo_mime LIKE ?";
            $parametros[] = $filtros['tipo_mime'] . '%';
        }

        if (!empty($filtros['criado_por'])) {
            $sql .= " AND criado_por = ?";
            $parametros[] = $filtros['criado_por'];
        }

        // Ordenação
        $sql .= " ORDER BY criado_em DESC LIMIT ? OFFSET ?";
        $parametros[] = $limite;
        $parametros[] = $offset;

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Conta total de arquivos com filtros
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM s3_arquivos WHERE status != 'deletado'";
        $parametros = [];

        // Aplica mesmos filtros do listar
        if (!empty($filtros['bucket'])) {
            $sql .= " AND bucket = ?";
            $parametros[] = $filtros['bucket'];
        }

        if (!empty($filtros['entidade_tipo'])) {
            $sql .= " AND entidade_tipo = ?";
            $parametros[] = $filtros['entidade_tipo'];
        }

        if (!empty($filtros['entidade_id'])) {
            $sql .= " AND entidade_id = ?";
            $parametros[] = $filtros['entidade_id'];
        }

        if (!empty($filtros['categoria'])) {
            $sql .= " AND categoria = ?";
            $parametros[] = $filtros['categoria'];
        }

        if (!empty($filtros['tipo_mime'])) {
            $sql .= " AND tipo_mime LIKE ?";
            $parametros[] = $filtros['tipo_mime'] . '%';
        }

        if (!empty($filtros['criado_por'])) {
            $sql .= " AND criado_por = ?";
            $parametros[] = $filtros['criado_por'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Busca arquivos por entidade
     */
    public function buscarPorEntidade(string $tipo, int $id): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_arquivos
             WHERE entidade_tipo = ? AND entidade_id = ? AND status != 'deletado'
             ORDER BY criado_em DESC",
            [$tipo, $id]
        );
    }

    /**
     * Atualiza dados de um arquivo
     */
    public function atualizar(int $id, array $dados): bool
    {
        $campos = [];
        $parametros = [];

        // Campos permitidos para atualização
        $camposPermitidos = [
            'nome_original', 'acl', 'url_publica', 'metadata',
            'entidade_tipo', 'entidade_id', 'categoria', 'status'
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $campos[] = "{$campo} = ?";

                if ($campo === 'metadata' && is_array($dados[$campo])) {
                    $parametros[] = json_encode($dados[$campo]);
                } else {
                    $parametros[] = $dados[$campo];
                }
            }
        }

        if (empty($campos)) {
            return false;
        }

        $parametros[] = $id;

        $sql = "UPDATE s3_arquivos SET " . implode(', ', $campos) . " WHERE id = ?";

        return $this->db->executar($sql, $parametros);
    }

    /**
     * Deleta arquivo (soft delete)
     */
    public function deletar(int $id): bool
    {
        return $this->db->executar(
            "UPDATE s3_arquivos SET status = 'deletado', deletado_em = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Deleta arquivo permanentemente
     */
    public function deletarPermanente(int $id): bool
    {
        return $this->db->executar(
            "DELETE FROM s3_arquivos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Restaura arquivo deletado
     */
    public function restaurar(int $id): bool
    {
        return $this->db->executar(
            "UPDATE s3_arquivos SET status = 'ativo', deletado_em = NULL WHERE id = ?",
            [$id]
        );
    }

    /**
     * Obtém estatísticas de armazenamento
     */
    public function obterEstatisticas(): array
    {
        $stats = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total_arquivos,
                SUM(tamanho_bytes) as tamanho_total,
                AVG(tamanho_bytes) as tamanho_medio
             FROM s3_arquivos
             WHERE status != 'deletado'"
        );

        $porTipo = $this->db->buscarTodos(
            "SELECT
                tipo_mime,
                COUNT(*) as quantidade,
                SUM(tamanho_bytes) as tamanho
             FROM s3_arquivos
             WHERE status != 'deletado'
             GROUP BY tipo_mime
             ORDER BY quantidade DESC
             LIMIT 10"
        );

        return [
            'total_arquivos' => (int) ($stats['total_arquivos'] ?? 0),
            'tamanho_total_bytes' => (int) ($stats['tamanho_total'] ?? 0),
            'tamanho_medio_bytes' => (int) ($stats['tamanho_medio'] ?? 0),
            'por_tipo' => $porTipo
        ];
    }

    /**
     * Busca arquivos deletados (para limpeza)
     */
    public function buscarDeletados(int $diasAtras = 30): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_arquivos
             WHERE status = 'deletado'
             AND deletado_em < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY deletado_em ASC",
            [$diasAtras]
        );
    }

    /**
     * Gera UUID v4
     */
    private function gerarUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Verifica se UUID já existe
     */
    public function uuidExiste(string $uuid): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM s3_arquivos WHERE uuid = ?",
            [$uuid]
        );

        return ($resultado['total'] ?? 0) > 0;
    }

    /**
     * Calcula hash MD5 de conteúdo
     */
    public function calcularHashMd5(string $conteudo): string
    {
        return md5($conteudo);
    }

    /**
     * Busca arquivo por hash (para evitar duplicatas)
     */
    public function buscarPorHash(string $hash): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM s3_arquivos WHERE hash_md5 = ? AND status != 'deletado' LIMIT 1",
            [$hash]
        );
    }
}
