<?php

namespace App\Models\S3;

use App\Core\BancoDados;

/**
 * Model para gerenciar configurações do S3
 * Armazena credenciais AWS e parâmetros de conexão no banco de dados
 */
class ModelS3Configuracao
{
    private BancoDados $db;
    private array $cache = [];

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca todas as configurações
     */
    public function buscarTodas(): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_configuracoes ORDER BY categoria, chave"
        );
    }

    /**
     * Busca configurações por categoria
     */
    public function buscarPorCategoria(string $categoria): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_configuracoes WHERE categoria = ? ORDER BY chave",
            [$categoria]
        );
    }

    /**
     * Busca uma configuração por chave
     */
    public function buscarPorChave(string $chave): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM s3_configuracoes WHERE chave = ?",
            [$chave]
        );
    }

    /**
     * Obtém valor de uma configuração (com cache)
     */
    public function obter(string $chave, mixed $default = null): mixed
    {
        // Verifica cache
        if (isset($this->cache[$chave])) {
            return $this->cache[$chave];
        }

        $config = $this->buscarPorChave($chave);

        if ($config === null) {
            return $default;
        }

        // Converte valor conforme tipo
        $valor = $config['valor'];

        if ($config['tipo'] === 'booleano') {
            $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        } elseif ($config['tipo'] === 'numero') {
            $valor = is_numeric($valor) ? (strpos($valor, '.') !== false ? (float)$valor : (int)$valor) : $valor;
        }

        // Armazena em cache
        $this->cache[$chave] = $valor;

        return $valor;
    }

    /**
     * Obtém todas configurações como array associativo
     */
    public function obterTodas(): array
    {
        $configs = $this->buscarTodas();
        $resultado = [];

        foreach ($configs as $config) {
            $resultado[$config['chave']] = $this->obter($config['chave']);
        }

        return $resultado;
    }

    /**
     * Salva ou atualiza uma configuração
     */
    public function salvar(string $chave, mixed $valor): bool
    {
        // Limpa cache
        unset($this->cache[$chave]);

        // Converte valor para string se necessário
        if (is_bool($valor)) {
            $valor = $valor ? '1' : '0';
        } elseif (is_array($valor)) {
            $valor = json_encode($valor);
        }

        $stmt = $this->db->executar(
            "UPDATE s3_configuracoes SET valor = ?, atualizado_em = NOW() WHERE chave = ?",
            [$valor, $chave]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Salva múltiplas configurações de uma vez
     */
    public function salvarLote(array $configuracoes): bool
    {
        $this->db->iniciarTransacao();

        try {
            foreach ($configuracoes as $chave => $valor) {
                // Para salvarLote, não verificamos rowCount pois pode não alterar nada se o valor for igual
                $this->salvar($chave, $valor);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Verifica se todas as configurações obrigatórias estão preenchidas
     */
    public function validarConfiguracoesObrigatorias(): array
    {
        $obrigatorias = $this->db->buscarTodos(
            "SELECT chave, descricao FROM s3_configuracoes WHERE obrigatorio = 1 AND (valor IS NULL OR valor = '')"
        );

        return $obrigatorias;
    }

    /**
     * Verifica se o S3 está configurado e habilitado
     */
    public function estaConfigurado(): bool
    {
        $status = $this->obter('aws_s3_status', 0);

        if (!$status) {
            return false;
        }

        $obrigatorias = $this->validarConfiguracoesObrigatorias();

        return empty($obrigatorias);
    }

    /**
     * Limpa todo o cache
     */
    public function limparCache(): void
    {
        $this->cache = [];
    }

    /**
     * Cria uma nova configuração (usado em migrations)
     */
    public function criar(array $dados): bool
    {
        $stmt = $this->db->executar(
            "INSERT INTO s3_configuracoes (chave, valor, categoria, descricao, tipo, obrigatorio)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $dados['chave'],
                $dados['valor'] ?? null,
                $dados['categoria'] ?? 'geral',
                $dados['descricao'] ?? null,
                $dados['tipo'] ?? 'texto',
                $dados['obrigatorio'] ?? 0
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta uma configuração
     */
    public function deletar(string $chave): bool
    {
        unset($this->cache[$chave]);

        $stmt = $this->db->executar(
            "DELETE FROM s3_configuracoes WHERE chave = ?",
            [$chave]
        );

        return $stmt->rowCount() > 0;
    }
}
