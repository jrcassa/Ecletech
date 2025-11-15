<?php

namespace App\CRM\Core;

use App\Models\ModelCrmIntegracao;

/**
 * Gerenciador de configurações de CRM
 */
class CrmConfig
{
    private ModelCrmIntegracao $model;
    private static array $cache = [];

    public function __construct()
    {
        $this->model = new ModelCrmIntegracao();
    }

    /**
     * Obtém a configuração ativa para uma loja
     */
    public function obterConfiguracao(int $idLoja): ?array
    {
        // Verifica cache
        if (isset(self::$cache[$idLoja])) {
            return self::$cache[$idLoja];
        }

        $config = $this->model->buscarPorLoja($idLoja);

        if (!$config || !$config['ativo']) {
            return null;
        }

        // Descriptografa credenciais
        $config['credenciais'] = $this->descriptografarCredenciais($config['credenciais']);

        // Decodifica configurações JSON
        if (!empty($config['configuracoes'])) {
            $config['configuracoes'] = json_decode($config['configuracoes'], true);
        }

        // Salva em cache
        self::$cache[$idLoja] = $config;

        return $config;
    }

    /**
     * Salva configuração de CRM para uma loja
     */
    public function salvarConfiguracao(int $idLoja, string $provider, array $credenciais, array $configuracoes = []): int
    {
        // Criptografa credenciais
        $credenciaisEncriptadas = $this->criptografarCredenciais($credenciais);

        $dados = [
            'id_loja' => $idLoja,
            'provider' => $provider,
            'credenciais' => $credenciaisEncriptadas,
            'configuracoes' => json_encode($configuracoes),
            'ativo' => 1
        ];

        // Verifica se já existe
        $existente = $this->model->buscarPorLoja($idLoja);

        if ($existente) {
            $this->model->atualizar($existente['id'], $dados);
            $id = $existente['id'];
        } else {
            $id = $this->model->criar($dados);
        }

        // Limpa cache
        unset(self::$cache[$idLoja]);

        return $id;
    }

    /**
     * Desativa integração de uma loja
     */
    public function desativarIntegracao(int $idLoja): bool
    {
        $config = $this->model->buscarPorLoja($idLoja);

        if (!$config) {
            return false;
        }

        $this->model->atualizar($config['id'], ['ativo' => 0]);

        // Limpa cache
        unset(self::$cache[$idLoja]);

        return true;
    }

    /**
     * Criptografa credenciais usando AES-256-CBC
     */
    private function criptografarCredenciais(array $credenciais): string
    {
        $chave = $this->obterChaveCriptografia();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        $json = json_encode($credenciais);
        $encriptado = openssl_encrypt($json, 'aes-256-cbc', $chave, 0, $iv);

        // Retorna IV + dados encriptados (base64)
        return base64_encode($iv . $encriptado);
    }

    /**
     * Descriptografa credenciais
     */
    private function descriptografarCredenciais(string $credenciaisEncriptadas): array
    {
        $chave = $this->obterChaveCriptografia();
        $dados = base64_decode($credenciaisEncriptadas);

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($dados, 0, $ivLength);
        $encriptado = substr($dados, $ivLength);

        $json = openssl_decrypt($encriptado, 'aes-256-cbc', $chave, 0, $iv);

        return json_decode($json, true);
    }

    /**
     * Obtém chave de criptografia do .env
     */
    private function obterChaveCriptografia(): string
    {
        // Usa a chave JWT do .env como base
        $chaveBase = $_ENV['JWT_SECRET'] ?? 'ecletech-crm-secret-key';

        // Deriva uma chave de 32 bytes para AES-256
        return hash('sha256', $chaveBase, true);
    }

    /**
     * Limpa todo o cache
     */
    public static function limparCache(): void
    {
        self::$cache = [];
    }
}
