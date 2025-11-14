#!/usr/bin/env php
<?php
/**
 * Cron: Processador de Fila de Email
 *
 * Processa emails pendentes na fila
 *
 * Configuração do crontab (executar a cada 1 minuto):
 * * * * * php /caminho/para/cron/processar_email.php >> /var/log/email_cron.log 2>&1
 *
 * Ou a cada 5 minutos:
 * *\/5 * * * * php /caminho/para/cron/processar_email.php >> /var/log/email_cron.log 2>&1
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
class ProcessadorEmailCron
{
    private \App\Services\Email\ServiceEmail $service;
    private \App\Models\Email\ModelEmailConfiguracao $config;
    private \App\Core\BancoDados $db;
    private array $resultado = [
        'inicio' => null,
        'fim' => null,
        'processados' => 0,
        'enviados' => 0,
        'erros' => 0,
        'tempo' => 0,
        'memoria' => 0
    ];

    public function __construct()
    {
        $this->service = new \App\Services\Email\ServiceEmail();
        $this->config = new \App\Models\Email\ModelEmailConfiguracao();
        $this->db = \App\Core\BancoDados::obterInstancia();
    }

    /**
     * Executa processamento
     */
    public function executar(): void
    {
        $this->resultado['inicio'] = microtime(true);

        echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de emails...\n";

        try {
            // Verifica se processamento está habilitado
            if (!$this->config->obter('cron_habilitado', true)) {
                echo "[" . date('Y-m-d H:i:s') . "] Processamento desabilitado nas configurações\n";
                return;
            }

            // Verifica horário de funcionamento
            if (!$this->dentroDoHorarioPermitido()) {
                echo "[" . date('Y-m-d H:i:s') . "] Fora do horário de processamento\n";
                return;
            }

            // Obtém limite de mensagens
            $limite = $this->config->obter('cron_limite_mensagens', 20);

            echo "[" . date('Y-m-d H:i:s') . "] Processando até {$limite} emails...\n";

            // Processa fila
            $resultado = $this->service->processarFila($limite);

            $this->resultado['processados'] = $resultado['processados'];
            $this->resultado['enviados'] = $resultado['enviados'];
            $this->resultado['erros'] = $resultado['erros'];

            echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
            echo "  - Processados: {$resultado['processados']}\n";
            echo "  - Enviados: {$resultado['enviados']}\n";
            echo "  - Erros: {$resultado['erros']}\n";

        } catch (\Exception $e) {
            ErrorLogger::log($e, [
                'tipo_erro' => 'cron',
                'nivel' => 'alto',
                'contexto' => [
                    'cron_job' => 'processar_email',
                    'descricao' => 'Erro ao processar fila de emails'
                ]
            ]);

            echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
            $this->resultado['erros']++;
        } finally {
            $this->finalizarExecucao();
        }
    }

    /**
     * Verifica se está dentro do horário permitido
     */
    private function dentroDoHorarioPermitido(): bool
    {
        $horaAtual = date('H:i');
        $horaInicio = $this->config->obter('cron_horario_inicio', '00:00');
        $horaFim = $this->config->obter('cron_horario_fim', '23:59');

        return $horaAtual >= $horaInicio && $horaAtual <= $horaFim;
    }

    /**
     * Finaliza execução e registra log
     */
    private function finalizarExecucao(): void
    {
        $this->resultado['fim'] = microtime(true);
        $this->resultado['tempo'] = round($this->resultado['fim'] - $this->resultado['inicio'], 2);
        $this->resultado['memoria'] = $this->formatarMemoria(memory_get_peak_usage(true));

        echo "[" . date('Y-m-d H:i:s') . "] Tempo: {$this->resultado['tempo']}s | Memória: {$this->resultado['memoria']}\n";

        // Registra no banco
        $this->registrarLog();
    }

    /**
     * Registra log no banco
     */
    private function registrarLog(): void
    {
        try {
            $status = $this->resultado['erros'] > 0 ? 'erro' : 'sucesso';

            $sql = "INSERT INTO email_cron_logs
                    (iniciado_em, finalizado_em, tempo_execucao, mensagens_processadas,
                     mensagens_enviadas, erros, memoria_pico, status, detalhes, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $this->db->executar($sql, [
                date('Y-m-d H:i:s', (int)$this->resultado['inicio']),
                date('Y-m-d H:i:s', (int)$this->resultado['fim']),
                $this->resultado['tempo'],
                $this->resultado['processados'],
                $this->resultado['enviados'],
                $this->resultado['erros'],
                $this->resultado['memoria'],
                $status,
                json_encode($this->resultado)
            ]);

        } catch (\Exception $e) {
            ErrorLogger::log($e, [
                'tipo_erro' => 'database',
                'nivel' => 'medio',
                'contexto' => [
                    'cron_job' => 'processar_email',
                    'descricao' => 'Erro ao registrar log de execução no banco'
                ]
            ]);

            echo "[" . date('Y-m-d H:i:s') . "] Erro ao registrar log: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Formata memória
     */
    private function formatarMemoria(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $unidades[$i];
    }
}

// ============================================
// EXECUÇÃO
// ============================================

try {
    $processador = new ProcessadorEmailCron();
    $processador->executar();
    exit(0); // Sucesso
} catch (\Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'cron',
        'nivel' => 'critico',
        'contexto' => [
            'cron_job' => 'processar_email',
            'descricao' => 'Erro fatal ao inicializar cron de email'
        ]
    ]);

    echo "[" . date('Y-m-d H:i:s') . "] ERRO FATAL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1); // Erro
}
