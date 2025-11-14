#!/usr/bin/env php
<?php
/**
 * Cron: Processador de Fila do WhatsApp
 *
 * Processa mensagens pendentes na fila do WhatsApp
 *
 * Configuração do crontab (executar a cada 1 minuto):
 * * * * * php /caminho/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
 *
 * Ou a cada 5 minutos:
 * *\/5 * * * * php /caminho/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
 */

// Garante execução apenas via CLI
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via linha de comando');
}

// Define diretório raiz
define('ROOT_PATH', dirname(__DIR__, 2));

// Carrega o autoloader do Composer (se existir)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = ROOT_PATH . '/App/';

    $tamanho = strlen($prefixo);
    if (strncmp($prefixo, $classe, $tamanho) !== 0) {
        return;
    }

    $classeRelativa = substr($classe, $tamanho);
    $arquivo = $diretorioBase . str_replace('\\', '/', $classeRelativa) . '.php';

    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

// Carrega variáveis de ambiente
$caminhoEnv = ROOT_PATH . '/.env';
$carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
$carregadorEnv->carregar($caminhoEnv);

use App\Helpers\ErrorLogger;

/**
 * Classe principal do cron
 */
class ProcessadorWhatsAppCron
{
    private \App\Services\Whatsapp\ServiceWhatsapp $service;
    private \App\Models\Whatsapp\ModelWhatsappConfiguracao $config;
    private array $resultado = [
        'inicio' => null,
        'fim' => null,
        'duracao' => 0,
        'processadas' => 0,
        'sucesso' => 0,
        'erro' => 0,
        'detalhes' => []
    ];

    public function __construct()
    {
        $this->service = new \App\Services\Whatsapp\ServiceWhatsapp();
        $this->config = new \App\Models\Whatsapp\ModelWhatsappConfiguracao();
    }

    /**
     * Executa o processamento
     */
    public function executar(): void
    {
        $this->resultado['inicio'] = date('Y-m-d H:i:s');
        $this->log("=== Iniciando processamento da fila WhatsApp ===");

        try {
            // Verifica se o processamento está habilitado
            $habilitado = $this->config->obter('cron_habilitado', true);

            if (!$habilitado) {
                $this->log("Processamento desabilitado via configuração");
                return;
            }

            // Obtém limite de mensagens por execução
            $limite = (int) $this->config->obter('cron_limite_mensagens', 10);
            $this->log("Limite de mensagens: {$limite}");

            // Processa a fila
            $resultado = $this->service->processarFila($limite);

            // Atualiza estatísticas
            $this->resultado['processadas'] = $resultado['processadas'];
            $this->resultado['sucesso'] = $resultado['sucesso'];
            $this->resultado['erro'] = $resultado['erro'];
            $this->resultado['detalhes'] = $resultado['detalhes'];

            // Log de resultado
            $this->log("Processadas: {$resultado['processadas']}");
            $this->log("Sucesso: {$resultado['sucesso']}");
            $this->log("Erro: {$resultado['erro']}");

            // Log de detalhes (se houver erros)
            if ($resultado['erro'] > 0) {
                foreach ($resultado['detalhes'] as $detalhe) {
                    if (!$detalhe['sucesso']) {
                        $this->log("  - Erro queue_id {$detalhe['queue_id']}: {$detalhe['erro']}");
                    }
                }
            }

        } catch (\Exception $e) {
            ErrorLogger::log($e, [
                'tipo_erro' => 'cron',
                'nivel' => 'critico',
                'contexto' => [
                    'cron_job' => 'processar_whatsapp',
                    'descricao' => 'Erro crítico ao processar fila WhatsApp'
                ]
            ]);

            $this->log("ERRO CRÍTICO: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
        }

        $this->resultado['fim'] = date('Y-m-d H:i:s');
        $this->resultado['duracao'] = strtotime($this->resultado['fim']) - strtotime($this->resultado['inicio']);

        $this->log("Duração: {$this->resultado['duracao']} segundos");
        $this->log("=== Processamento concluído ===\n");
    }

    /**
     * Registra log
     */
    private function log(string $mensagem): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$mensagem}\n";
    }

    /**
     * Retorna resultado da execução
     */
    public function obterResultado(): array
    {
        return $this->resultado;
    }
}

// Executa o cron
try {
    $cron = new ProcessadorWhatsAppCron();
    $cron->executar();
    exit(0);
} catch (\Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'cron',
        'nivel' => 'critico',
        'contexto' => [
            'cron_job' => 'processar_whatsapp',
            'descricao' => 'Erro fatal ao inicializar cron de WhatsApp'
        ]
    ]);

    echo "[FATAL] " . $e->getMessage() . "\n";
    exit(1);
}
