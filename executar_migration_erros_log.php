<?php
/**
 * Migration para criar tabela de log de erros
 * Execução: php executar_migration_erros_log.php
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

// Carrega o arquivo .env
if (file_exists(__DIR__ . '/.env')) {
    $linhas = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        if (strpos(trim($linha), '#') === 0) {
            continue;
        }
        list($nome, $valor) = explode('=', $linha, 2);
        $nome = trim($nome);
        $valor = trim($valor);
        if (!array_key_exists($nome, $_ENV)) {
            $_ENV[$nome] = $valor;
        }
        if (!array_key_exists($nome, $_SERVER)) {
            $_SERVER[$nome] = $valor;
        }
    }
}

use App\Core\BancoDados;

try {
    echo "Iniciando migration: erros_log\n\n";

    $db = BancoDados::obterInstancia();
    $pdo = $db->obterConexao();

    // Verifica se a tabela já existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'erros_log'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Tabela 'erros_log' já existe. Migration cancelada.\n";
        exit(0);
    }

    echo "Criando tabela 'erros_log'...\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS `erros_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tipo_erro` ENUM('exception', 'fatal', 'warning', 'notice', 'database', 'api', 'validacao', 'autenticacao', 'outro') NOT NULL DEFAULT 'exception',
        `nivel` ENUM('critico', 'alto', 'medio', 'baixo') NOT NULL DEFAULT 'medio',
        `mensagem` TEXT NOT NULL,
        `stack_trace` TEXT NULL,
        `arquivo` VARCHAR(500) NULL,
        `linha` INT NULL,
        `codigo_erro` VARCHAR(50) NULL,
        `tipo_entidade` VARCHAR(50) NULL,
        `entidade_id` INT NULL,
        `contexto` JSON NULL,
        `usuario_id` INT NULL,
        `url` VARCHAR(500) NULL,
        `metodo_http` VARCHAR(10) NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `resolvido` BOOLEAN DEFAULT 0,
        `resolvido_em` TIMESTAMP NULL,
        `resolvido_por` INT NULL,
        `notas_resolucao` TEXT NULL,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `deletado_em` TIMESTAMP NULL,
        INDEX `idx_tipo_erro` (`tipo_erro`),
        INDEX `idx_nivel` (`nivel`),
        INDEX `idx_entidade` (`tipo_entidade`, `entidade_id`),
        INDEX `idx_usuario` (`usuario_id`),
        INDEX `idx_resolvido` (`resolvido`),
        INDEX `idx_criado_em` (`criado_em`),
        INDEX `idx_deletado_em` (`deletado_em`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);

    echo "✓ Tabela 'erros_log' criada com sucesso!\n\n";

    echo "Estrutura da tabela:\n";
    echo "- id: Identificador único\n";
    echo "- tipo_erro: Classificação do erro (exception, fatal, warning, etc.)\n";
    echo "- nivel: Nível de severidade (critico, alto, medio, baixo)\n";
    echo "- mensagem: Mensagem do erro\n";
    echo "- stack_trace: Stack trace completo\n";
    echo "- arquivo: Arquivo onde ocorreu o erro\n";
    echo "- linha: Linha do erro\n";
    echo "- codigo_erro: Código do erro (se houver)\n";
    echo "- tipo_entidade: Tipo da entidade relacionada\n";
    echo "- entidade_id: ID da entidade relacionada\n";
    echo "- contexto: Dados de contexto (JSON)\n";
    echo "- usuario_id: ID do usuário\n";
    echo "- url: URL onde ocorreu\n";
    echo "- metodo_http: GET, POST, etc.\n";
    echo "- ip_address: IP do cliente\n";
    echo "- user_agent: Navegador/cliente\n";
    echo "- resolvido: Se o erro foi resolvido\n";
    echo "- resolvido_em: Data de resolução\n";
    echo "- resolvido_por: Quem resolveu\n";
    echo "- notas_resolucao: Notas sobre a resolução\n";
    echo "- criado_em: Data de criação\n";
    echo "- deletado_em: Data de deleção (soft delete)\n\n";

    echo "✅ Migration concluída com sucesso!\n";

} catch (\Exception $e) {
    echo "❌ Erro ao executar migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
