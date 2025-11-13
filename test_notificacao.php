<?php
/**
 * Script de teste para diagnóstico de notificações WhatsApp
 *
 * Execute via CLI: php test_notificacao.php
 */

require_once __DIR__ . '/App/Core/BancoDados.php';
require_once __DIR__ . '/App/Models/FrotaAbastecimento/ModelFrotaAbastecimento.php';
require_once __DIR__ . '/App/Models/Colaborador/ModelColaborador.php';
require_once __DIR__ . '/App/Services/FrotaAbastecimento/ServiceFrotaAbastecimentoNotificacao.php';
require_once __DIR__ . '/App/Services/Whatsapp/ServiceWhatsapp.php';
require_once __DIR__ . '/App/Services/Whatsapp/ServiceWhatsappEntidade.php';
require_once __DIR__ . '/App/Models/Whatsapp/ModelWhatsappQueue.php';
require_once __DIR__ . '/App/Models/Whatsapp/ModelWhatsappConfiguracao.php';
require_once __DIR__ . '/App/Models/Whatsapp/ModelWhatsappHistorico.php';
require_once __DIR__ . '/App/Helpers/AuxiliarWhatsapp.php';

use App\Core\BancoDados;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\Colaborador\ModelColaborador;
use App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoNotificacao;
use App\Models\Whatsapp\ModelWhatsappQueue;

echo "========================================\n";
echo "TESTE DE NOTIFICAÇÃO WHATSAPP\n";
echo "========================================\n\n";

try {
    // 1. Busca último abastecimento
    echo "1. Buscando último abastecimento...\n";
    $db = BancoDados::obterInstancia();
    $model = new ModelFrotaAbastecimento();

    $sql = "SELECT id FROM frotas_abastecimentos ORDER BY criado_em DESC LIMIT 1";
    $resultado = $db->buscarUm($sql);

    if (!$resultado) {
        echo "❌ ERRO: Nenhum abastecimento encontrado\n";
        exit(1);
    }

    $abastecimentoId = $resultado['id'];
    echo "✅ Abastecimento ID: {$abastecimentoId}\n\n";

    // 2. Busca detalhes do abastecimento
    echo "2. Buscando detalhes do abastecimento...\n";
    $abastecimento = $model->buscarComDetalhes($abastecimentoId);

    if (!$abastecimento) {
        echo "❌ ERRO: Não foi possível buscar detalhes do abastecimento\n";
        exit(1);
    }

    echo "✅ Motorista: {$abastecimento['motorista_nome']}\n";
    echo "✅ Celular: " . ($abastecimento['motorista_celular'] ?? 'NÃO CADASTRADO') . "\n";
    echo "✅ Veículo: {$abastecimento['frota_placa']}\n\n";

    // 3. Verifica se motorista tem celular
    echo "3. Verificando celular do motorista...\n";
    $modelColaborador = new ModelColaborador();
    $motorista = $modelColaborador->buscarPorId($abastecimento['colaborador_id']);

    if (!$motorista || !$motorista['celular']) {
        echo "❌ ERRO: Motorista não tem celular cadastrado\n";
        echo "   ID: {$abastecimento['colaborador_id']}\n";
        echo "   Nome: {$motorista['nome']}\n";
        echo "   Celular: " . ($motorista['celular'] ?? 'NULL') . "\n";
        exit(1);
    }

    echo "✅ Motorista tem celular: {$motorista['celular']}\n\n";

    // 4. Tenta enviar notificação
    echo "4. Enviando notificação...\n";
    $serviceNotificacao = new ServiceFrotaAbastecimentoNotificacao();

    try {
        $serviceNotificacao->enviarNotificacaoOrdemCriada($abastecimentoId);
        echo "✅ Método enviarNotificacaoOrdemCriada() executado sem erros\n\n";
    } catch (Exception $e) {
        echo "❌ ERRO ao enviar notificação: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
        exit(1);
    }

    // 5. Verifica se foi inserido na fila
    echo "5. Verificando fila WhatsApp...\n";
    $queueModel = new ModelWhatsappQueue();

    $sql = "SELECT * FROM whatsapp_queue
            WHERE dados_extras LIKE '%\"abastecimento_id\":{$abastecimentoId}%'
            ORDER BY criado_em DESC LIMIT 1";

    $mensagemFila = $db->buscarUm($sql);

    if (!$mensagemFila) {
        echo "❌ ERRO: Mensagem NÃO foi inserida na fila whatsapp_queue\n\n";

        // Debug adicional
        echo "6. Verificando últimas mensagens na fila...\n";
        $ultimasMensagens = $db->buscarTodos(
            "SELECT id, destinatario, tipo_mensagem, status, criado_em, dados_extras
             FROM whatsapp_queue
             ORDER BY criado_em DESC
             LIMIT 5"
        );

        if (empty($ultimasMensagens)) {
            echo "   Fila está vazia\n";
        } else {
            echo "   Últimas 5 mensagens:\n";
            foreach ($ultimasMensagens as $msg) {
                echo "   - ID: {$msg['id']}, Destino: {$msg['destinatario']}, Status: {$msg['status']}\n";
            }
        }

        exit(1);
    }

    echo "✅ Mensagem inserida na fila!\n";
    echo "   ID: {$mensagemFila['id']}\n";
    echo "   Destinatário: {$mensagemFila['destinatario']}\n";
    echo "   Status: {$mensagemFila['status']}\n";
    echo "   Tipo: {$mensagemFila['tipo_mensagem']}\n";
    echo "   Criado em: {$mensagemFila['criado_em']}\n\n";

    echo "========================================\n";
    echo "✅ TESTE CONCLUÍDO COM SUCESSO\n";
    echo "========================================\n";
    echo "\nA notificação foi inserida na fila.\n";
    echo "Execute o processador da fila para enviar:\n";
    echo "php processar_fila_whatsapp.php\n";

} catch (Exception $e) {
    echo "\n❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
