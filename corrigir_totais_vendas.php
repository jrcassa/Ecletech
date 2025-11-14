<?php
/**
 * Script para recalcular valor_total de todas as vendas
 * Executa o mesmo cálculo que ServiceVenda::recalcularTotais
 */

// Carrega o autoloader do Composer (se existir)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/App/';

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
$caminhoEnv = __DIR__ . '/.env';
if (file_exists($caminhoEnv)) {
    $linhas = file($caminhoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        if (strpos(trim($linha), '#') === 0) continue;
        list($chave, $valor) = explode('=', $linha, 2);
        $_ENV[trim($chave)] = trim($valor);
    }
}

use App\Core\BancoDados;
use App\Models\Venda\ModelVenda;

try {
    echo "Iniciando recálculo de totais de vendas...\n\n";

    $db = BancoDados::obterInstancia();
    $vendaModel = new ModelVenda();

    // Busca todas as vendas ativas
    $vendas = $db->buscarTodos(
        "SELECT id, codigo FROM vendas WHERE deletado_em IS NULL ORDER BY id"
    );

    if (empty($vendas)) {
        echo "Nenhuma venda encontrada.\n";
        exit(0);
    }

    echo "Encontradas " . count($vendas) . " vendas para recalcular.\n\n";

    $atualizadas = 0;
    $erros = 0;

    foreach ($vendas as $venda) {
        try {
            // Calcula totais baseado nos itens
            $totais = $vendaModel->calcularTotais($venda['id']);

            // Atualiza a venda
            $sucesso = $vendaModel->atualizar($venda['id'], [
                'valor_produtos' => $totais['valor_produtos'],
                'valor_servicos' => $totais['valor_servicos'],
                'valor_total' => $totais['valor_total']
            ]);

            if ($sucesso) {
                echo "[✓] Venda #{$venda['codigo']} (ID: {$venda['id']}) - R$ " .
                     number_format($totais['valor_total'], 2, ',', '.') . "\n";
                $atualizadas++;
            } else {
                echo "[✗] Erro ao atualizar venda #{$venda['codigo']} (ID: {$venda['id']})\n";
                $erros++;
            }

        } catch (Exception $e) {
            echo "[✗] Erro na venda #{$venda['codigo']} (ID: {$venda['id']}): " . $e->getMessage() . "\n";
            $erros++;
        }
    }

    echo "\n";
    echo "========================================\n";
    echo "Resumo:\n";
    echo "Total de vendas: " . count($vendas) . "\n";
    echo "Atualizadas com sucesso: " . $atualizadas . "\n";
    echo "Erros: " . $erros . "\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
