#!/usr/bin/env php
<?php
/**
 * Script para FORÇAR envio de relatório SEMANAL AGORA
 * Envia relatório dos últimos 7 dias para todos colaboradores com configuração ativa
 *
 * Uso: php enviar_relatorio_semanal.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Define diretório raiz
define('ROOT_PATH', __DIR__);

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

echo "=== ENVIO FORÇADO - RELATÓRIO SEMANAL ===\n\n";

try {
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();
    $modelConfig = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao();

    // Calcula período dos últimos 7 dias
    $fim = new DateTime();
    $inicio = clone $fim;
    $inicio->modify('-7 days');

    $periodoInicio = $inicio->format('Y-m-d');
    $periodoFim = $fim->format('Y-m-d');

    echo "📅 Período: {$periodoInicio} a {$periodoFim}\n";
    echo "📊 Tipo: semanal\n\n";

    // Busca configurações ativas para relatórios semanais
    $configs = $modelConfig->listar([
        'ativo' => true,
        'tipo_relatorio' => 'semanal'
    ]);

    if (empty($configs)) {
        echo "⚠️  Nenhuma configuração ativa encontrada para relatórios semanais.\n";
        echo "Crie uma configuração primeiro:\n";
        echo "  - Via API: POST /frota-abastecimento-relatorios/configurar\n";
        echo "  - Ou diretamente no banco de dados\n\n";
        exit(0);
    }

    echo "Encontradas " . count($configs) . " configuração(ões) ativa(s).\n\n";

    $enviados = 0;
    $erros = 0;
    $ignorados = 0;

    foreach ($configs as $config) {
        echo "📤 Processando: {$config['colaborador_nome']}\n";
        echo "   Email: {$config['colaborador_email']}\n";

        if (empty($config['colaborador_celular'])) {
            echo "   ⚠️  SEM CELULAR cadastrado! Ignorando.\n\n";
            $ignorados++;
            continue;
        }

        echo "   📱 Celular: {$config['colaborador_celular']}\n";
        echo "   📋 Formato: {$config['formato_relatorio']}\n";
        echo "   Enviando via WhatsApp...\n";

        try {
            $logId = $service->enviarRelatorioManual(
                $config['colaborador_id'],
                'semanal',
                $periodoInicio,
                $periodoFim,
                $config['formato_relatorio']
            );

            echo "   ✅ ENVIADO COM SUCESSO! Log ID: {$logId}\n\n";
            $enviados++;

            // Pausa para não sobrecarregar WhatsApp
            sleep(2);

        } catch (\Exception $e) {
            echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
            $erros++;
        }
    }

    echo str_repeat('=', 60) . "\n";
    echo "=== RESUMO DO ENVIO ===\n";
    echo str_repeat('=', 60) . "\n";
    echo "✅ Enviados com sucesso: {$enviados}\n";
    echo "❌ Erros: {$erros}\n";
    echo "⚠️  Ignorados (sem celular): {$ignorados}\n";
    echo "📊 Total processado: " . ($enviados + $erros + $ignorados) . "\n";
    echo str_repeat('=', 60) . "\n\n";

    if ($enviados > 0) {
        echo "✅ Verifique o WhatsApp para confirmar o recebimento!\n";
        echo "📝 Confira os logs em: frotas_abastecimentos_relatorios_logs\n\n";
    }

    if ($erros > 0) {
        echo "⚠️  Houve erros no envio. Verifique:\n";
        echo "   1. Configuração do WhatsApp (api_base_url, tokens)\n";
        echo "   2. Números de celular cadastrados\n";
        echo "   3. Logs de erro no sistema\n\n";
    }

    if ($ignorados > 0) {
        echo "⚠️  {$ignorados} colaborador(es) sem celular cadastrado.\n";
        echo "   Cadastre o celular no perfil do colaborador para receber relatórios.\n\n";
    }

    exit(0);

} catch (\Exception $e) {
    echo "\n";
    echo str_repeat('=', 60) . "\n";
    echo "✗ ERRO FATAL\n";
    echo str_repeat('=', 60) . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    echo str_repeat('=', 60) . "\n";
    exit(1);
}
