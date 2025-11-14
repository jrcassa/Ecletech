#!/usr/bin/env php
<?php
/**
 * Script para atualizar configuração de relatório para segunda-feira
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

echo "=== ATUALIZAR CONFIGURAÇÃO DE RELATÓRIO ===\n\n";

try {
    $modelConfig = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao();

    // Busca todas configurações semanais ativas
    $configs = $modelConfig->listar([
        'ativo' => true,
        'tipo_relatorio' => 'semanal'
    ]);

    if (empty($configs)) {
        echo "❌ Nenhuma configuração semanal ativa encontrada.\n";
        exit(1);
    }

    echo "Configurações semanais ativas encontradas: " . count($configs) . "\n\n";

    foreach ($configs as $config) {
        $diaAtual = $config['dia_envio_semanal'];

        echo "Config ID {$config['id']}:\n";
        echo "  Colaborador: {$config['colaborador_nome']} (ID: {$config['colaborador_id']})\n";
        echo "  Dia atual: {$diaAtual}\n";

        if ($diaAtual === 'segunda') {
            echo "  ✓ Já está configurado para segunda-feira\n\n";
            continue;
        }

        // Atualiza para segunda
        $modelConfig->atualizar($config['id'], [
            'dia_envio_semanal' => 'segunda',
            'atualizado_por' => $config['colaborador_id']
        ]);

        echo "  ✓ Atualizado para SEGUNDA-FEIRA\n";
        echo "  ⏰ Próximo envio: Segunda às {$config['hora_envio']}\n\n";
    }

    echo "=== CONCLUÍDO ===\n";
    echo "\n📌 IMPORTANTE:\n";
    echo "Os relatórios agora serão enviados automaticamente toda SEGUNDA-FEIRA às 8h.\n";
    echo "Para testar agora, use: php enviar_relatorio_agora.php\n\n";

} catch (\Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
