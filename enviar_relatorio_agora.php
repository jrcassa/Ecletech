#!/usr/bin/env php
<?php
/**
 * Script para FORÇAR envio de relatório AGORA
 * Envia para todos colaboradores com configuração ativa
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

echo "=== ENVIO FORÇADO DE RELATÓRIOS ===\n\n";

try {
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();
    $modelConfig = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao();

    // Pergunta qual tipo
    echo "Escolha o tipo de relatório:\n";
    echo "1) Semanal (últimos 7 dias)\n";
    echo "2) Mensal (último mês completo)\n";
    echo "3) Personalizado (você escolhe o período)\n";
    echo "\nOpção: ";

    $opcao = trim(fgets(STDIN));

    $tipo = 'semanal';
    $inicio = null;
    $fim = null;

    switch ($opcao) {
        case '1':
            $tipo = 'semanal';
            // Últimos 7 dias
            $fim = new DateTime();
            $inicio = clone $fim;
            $inicio->modify('-7 days');
            break;

        case '2':
            $tipo = 'mensal';
            // Mês anterior completo
            $mesAnterior = new DateTime();
            $mesAnterior->modify('-1 month');
            $inicio = new DateTime($mesAnterior->format('Y-m-01'));
            $fim = new DateTime($mesAnterior->format('Y-m-t'));
            break;

        case '3':
            echo "\nData início (YYYY-MM-DD): ";
            $dataInicio = trim(fgets(STDIN));
            echo "Data fim (YYYY-MM-DD): ";
            $dataFim = trim(fgets(STDIN));

            $inicio = new DateTime($dataInicio);
            $fim = new DateTime($dataFim);

            echo "\nTipo (semanal/mensal): ";
            $tipo = trim(fgets(STDIN));
            break;

        default:
            echo "❌ Opção inválida!\n";
            exit(1);
    }

    $periodoInicio = $inicio->format('Y-m-d');
    $periodoFim = $fim->format('Y-m-d');

    echo "\n📅 Período: {$periodoInicio} a {$periodoFim}\n";
    echo "📊 Tipo: {$tipo}\n\n";

    // Busca configurações ativas
    $configs = $modelConfig->listar([
        'ativo' => true,
        'tipo_relatorio' => $tipo
    ]);

    if (empty($configs)) {
        echo "⚠️  Nenhuma configuração ativa encontrada para relatórios {$tipo}.\n";
        echo "Crie uma configuração primeiro via API ou banco de dados.\n";
        exit(0);
    }

    echo "Encontradas " . count($configs) . " configuração(ões) ativa(s).\n\n";

    $enviados = 0;
    $erros = 0;

    foreach ($configs as $config) {
        echo "📤 Enviando para: {$config['colaborador_nome']} ({$config['colaborador_email']})...\n";

        if (empty($config['colaborador_celular'])) {
            echo "   ❌ Sem celular cadastrado! Ignorando.\n\n";
            continue;
        }

        echo "   📱 Celular: {$config['colaborador_celular']}\n";
        echo "   📋 Formato: {$config['formato_relatorio']}\n";

        try {
            $logId = $service->enviarRelatorioManual(
                $config['colaborador_id'],
                $tipo,
                $periodoInicio,
                $periodoFim,
                $config['formato_relatorio']
            );

            echo "   ✅ Enviado! Log ID: {$logId}\n\n";
            $enviados++;

            // Pequena pausa para não sobrecarregar WhatsApp
            sleep(2);

        } catch (\Exception $e) {
            echo "   ❌ Erro: " . $e->getMessage() . "\n\n";
            $erros++;
        }
    }

    echo "=== RESUMO ===\n";
    echo "✅ Enviados: {$enviados}\n";
    echo "❌ Erros: {$erros}\n";
    echo "📊 Total: " . ($enviados + $erros) . "\n\n";

    if ($enviados > 0) {
        echo "Verifique o WhatsApp para confirmar o recebimento!\n";
    }

} catch (\Exception $e) {
    echo "\n✗ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
