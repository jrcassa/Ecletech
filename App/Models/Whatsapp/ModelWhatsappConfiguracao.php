<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para gerenciar configurações do WhatsApp
 */
class ModelWhatsappConfiguracao
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
            "SELECT * FROM whatsapp_configuracoes ORDER BY categoria, chave"
        );
    }

    /**
     * Busca configurações por categoria
     */
    public function buscarPorCategoria(string $categoria): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_configuracoes WHERE categoria = ? ORDER BY chave",
            [$categoria]
        );
    }

    /**
     * Busca uma configuração por chave
     */
    public function buscarPorChave(string $chave): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_configuracoes WHERE chave = ?",
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

        if ($config['tipo'] === 'boolean') {
            $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        } elseif ($config['tipo'] === 'integer') {
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
        if ($config['tipo'] === 'boolean') {
            $valorString = $valor ? '1' : '0';
        } elseif ($config['tipo'] === 'json') {
            $valorString = json_encode($valor);
        } else {
            $valorString = (string) $valor;
        }

        $this->db->atualizar(
            'whatsapp_configuracoes',
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
     * Reseta configuração para valor padrão
     */
    public function resetar(string $chave): bool
    {
        $config = $this->buscarPorChave($chave);

        if ($config === null) {
            return false;
        }

        $this->db->atualizar(
            'whatsapp_configuracoes',
            [
                'valor' => $config['valor_padrao'],
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
     * Limpa cache de configurações
     */
    public function limparCache(): void
    {
        $this->cache = [];
    }
}
