<?php

/**
 * Cron Job: Marca ordens de abastecimento expiradas
 * Execução: A cada hora
 * Crontab: 0 * * * * /usr/bin/php /path/to/Ecletech/cron/ordens_expiradas.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Carrega o autoloader
require __DIR__ . '/../vendor/autoload.php';

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../App/';

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

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificação de ordens expiradas...\n";

    // Carrega as variáveis de ambiente
    $caminhoEnv = __DIR__ . '/../.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Instancia o model
    $model = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimento();

    // Obtém database
    $db = \App\Core\BancoDados::obterInstancia()->obterConexao();

    // Busca ordens aguardando com data_limite vencida
    $sql = "UPDATE frotas_abastecimentos
            SET status = 'expirado',
                atualizado_em = NOW()
            WHERE status = 'aguardando'
            AND data_limite IS NOT NULL
            AND data_limite < CURDATE()";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $totalExpiradas = $stmt->rowCount();

    echo "[" . date('Y-m-d H:i:s') . "] Total de ordens marcadas como expiradas: {$totalExpiradas}\n";

    // Se houver ordens expiradas, envia notificações para admins
    if ($totalExpiradas > 0) {
        $serviceNotificacao = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoNotificacao();

        // Busca ordens expiradas de hoje
        $sqlExpiradas = "SELECT fa.*, f.placa, c.nome as motorista_nome, c.telefone as motorista_telefone
                        FROM frotas_abastecimentos fa
                        INNER JOIN frotas f ON fa.frota_id = f.id
                        INNER JOIN colaboradores c ON fa.colaborador_id = c.id
                        WHERE fa.status = 'expirado'
                        AND DATE(fa.atualizado_em) = CURDATE()";

        $stmtExpiradas = $db->prepare($sqlExpiradas);
        $stmtExpiradas->execute();
        $ordensExpiradas = $stmtExpiradas->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($ordensExpiradas as $ordem) {
            try {
                // Envia notificação para motorista
                $mensagem = "⚠️ *ORDEM DE ABASTECIMENTO EXPIRADA*\n\n";
                $mensagem .= "Olá {$ordem['motorista_nome']}!\n\n";
                $mensagem .= "A ordem de abastecimento do veículo *{$ordem['placa']}* expirou.\n";
                $mensagem .= "Data limite: " . date('d/m/Y', strtotime($ordem['data_limite'])) . "\n\n";
                $mensagem .= "Criada em: " . date('d/m/Y', strtotime($ordem['criado_em'])) . "\n";

                if ($ordem['observacao_admin']) {
                    $mensagem .= "\nObservação: {$ordem['observacao_admin']}\n";
                }

                $mensagem .= "\nEntre em contato com a gestão para esclarecimentos.";

                $serviceWhatsapp = new \App\Services\Whatsapp\ServiceWhatsapp();
                $serviceWhatsapp->enviarMensagem($ordem['motorista_telefone'], $mensagem);

                echo "[" . date('Y-m-d H:i:s') . "] Notificação enviada para {$ordem['motorista_nome']} ({$ordem['placa']})\n";
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Erro ao enviar notificação: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Verificação concluída com sucesso!\n";

    exit(0);

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n";

    // Log do erro
    error_log("Erro no cron de ordens expiradas: " . $e->getMessage());

    exit(1);
}
