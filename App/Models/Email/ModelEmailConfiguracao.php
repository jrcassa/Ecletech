<?php

namespace App\Models\Email;

use App\Core\BancoDados;

/**
 * Model para gerenciar configurações do Email
 * Padrão: Segue estrutura do ModelWhatsappConfiguracao
 */
class ModelEmailConfiguracao
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
            "SELECT * FROM email_configuracoes ORDER BY categoria, chave"
        );
    }

    /**
     * Busca configurações por categoria
     */
    public function buscarPorCategoria(string $categoria): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_configuracoes WHERE categoria = ? ORDER BY chave",
            [$categoria]
        );
    }

    /**
     * Busca uma configuração por chave
     */
    public function buscarPorChave(string $chave): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_configuracoes WHERE chave = ?",
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

        if ($config['tipo'] === 'bool') {
            $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        } elseif ($config['tipo'] === 'int') {
            $valor = (int) $valor;
        } elseif ($config['tipo'] === 'float') {
            $valor = (float) $valor;
        } elseif ($config['tipo'] === 'json') {
            $valor = json_decode($valor, true);
        }

        // Armazena em cache
        $this->cache[$chave] = $valor;

        return $valor;
    }

    /**
     * Salva uma configuração
     */
    public function salvar(string $chave, mixed $valor): bool
    {
        $config = $this->buscarPorChave($chave);

        if ($config === null) {
            return false;
        }

        // Converte valor para string conforme tipo
        if ($config['tipo'] === 'bool') {
            $valorString = $valor ? 'true' : 'false';
        } elseif ($config['tipo'] === 'json') {
            $valorString = json_encode($valor);
        } else {
            $valorString = (string) $valor;
        }

        $this->db->atualizar(
            'email_configuracoes',
            [
                'valor' => $valorString,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'chave = ?',
            [$chave]
        );

        // Limpa cache
        unset($this->cache[$chave]);

        return true;
    }

    /**
     * Cria uma nova configuração
     */
    public function criar(array $dados): int
    {
        return $this->db->inserir('email_configuracoes', $dados);
    }

    /**
     * Limpa cache de configurações
     */
    public function limparCache(): void
    {
        $this->cache = [];
    }

    /**
     * Lista todas as categorias disponíveis
     */
    public function listarCategorias(): array
    {
        $result = $this->db->buscarTodos(
            "SELECT DISTINCT categoria FROM email_configuracoes WHERE categoria IS NOT NULL ORDER BY categoria"
        );

        return array_column($result, 'categoria');
    }
}
