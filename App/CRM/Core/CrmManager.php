<?php

namespace App\CRM\Core;

use App\CRM\Providers\CrmProviderInterface;

/**
 * Gerenciador central de CRM
 * Responsável por instanciar o provider correto baseado na configuração
 */
class CrmManager
{
    private CrmConfig $config;
    private static array $providersCache = [];

    public function __construct()
    {
        $this->config = new CrmConfig();
    }

    /**
     * Obtém o provider ativo para uma loja
     *
     * @throws CrmException Se não houver integração ativa ou provider não existir
     */
    public function obterProvider(int $idLoja): CrmProviderInterface
    {
        // Verifica cache
        if (isset(self::$providersCache[$idLoja])) {
            return self::$providersCache[$idLoja];
        }

        // Busca configuração
        $configuracao = $this->config->obterConfiguracao($idLoja);

        if (!$configuracao) {
            throw new CrmException("Nenhuma integração CRM ativa encontrada para loja #{$idLoja}");
        }

        // Instancia provider
        $provider = $this->instanciarProvider($configuracao['provider'], $configuracao);

        // Salva em cache
        self::$providersCache[$idLoja] = $provider;

        return $provider;
    }

    /**
     * Verifica se uma loja tem integração CRM ativa
     */
    public function temIntegracaoAtiva(int $idLoja): bool
    {
        try {
            $this->obterProvider($idLoja);
            return true;
        } catch (CrmException $e) {
            return false;
        }
    }

    /**
     * Instancia o provider baseado no nome
     *
     * @throws CrmException Se o provider não existir
     */
    private function instanciarProvider(string $nomeProvider, array $configuracao): CrmProviderInterface
    {
        // Converte nome do provider para classe (gestao_click -> GestaoClick)
        $className = $this->converterNomeParaClasse($nomeProvider);
        $classeCompleta = "App\\CRM\\Providers\\{$className}\\{$className}Provider";

        if (!class_exists($classeCompleta)) {
            throw new CrmException("Provider '{$nomeProvider}' não encontrado: {$classeCompleta}");
        }

        $provider = new $classeCompleta($configuracao);

        if (!$provider instanceof CrmProviderInterface) {
            throw new CrmException("Provider '{$nomeProvider}' não implementa CrmProviderInterface");
        }

        return $provider;
    }

    /**
     * Converte nome do provider (snake_case) para PascalCase
     *
     * Exemplos:
     * - gestao_click -> GestaoClick
     * - pipedrive -> Pipedrive
     * - bling -> Bling
     */
    private function converterNomeParaClasse(string $nome): string
    {
        return str_replace('_', '', ucwords($nome, '_'));
    }

    /**
     * Lista todos os providers disponíveis no sistema
     */
    public function listarProvidersDisponiveis(): array
    {
        $diretorio = __DIR__ . '/../Providers';
        $providers = [];

        if (!is_dir($diretorio)) {
            return $providers;
        }

        $itens = scandir($diretorio);

        foreach ($itens as $item) {
            if ($item === '.' || $item === '..' || !is_dir("{$diretorio}/{$item}")) {
                continue;
            }

            // Verifica se existe o arquivo Provider
            $arquivoProvider = "{$diretorio}/{$item}/{$item}Provider.php";

            if (file_exists($arquivoProvider)) {
                $providers[] = [
                    'nome' => $this->converterClasseParaNome($item),
                    'classe' => $item,
                    'disponivel' => true
                ];
            }
        }

        return $providers;
    }

    /**
     * Converte classe (PascalCase) para nome (snake_case)
     *
     * Exemplos:
     * - GestaoClick -> gestao_click
     * - Pipedrive -> pipedrive
     */
    private function converterClasseParaNome(string $classe): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $classe));
    }

    /**
     * Limpa o cache de providers (útil após atualizar configurações)
     */
    public static function limparCache(): void
    {
        self::$providersCache = [];
        CrmConfig::limparCache();
    }
}
