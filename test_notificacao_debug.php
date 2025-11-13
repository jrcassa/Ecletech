<?php
/**
 * Script de teste DETALHADO para diagnóstico de notificações WhatsApp
 * Este script adiciona logs detalhados em cada etapa
 *
 * Execute via CLI: php test_notificacao_debug.php
 */

// Define o nível de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o timezone padrão
date_default_timezone_set('America/Sao_Paulo');

// Carrega o autoloader do Composer
require __DIR__ . '/vendor/autoload.php';

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

// Carrega as variáveis de ambiente
$caminhoEnv = __DIR__ . '/.env';
$carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
$carregadorEnv->carregar($caminhoEnv);

// Inicializa a configuração
$config = \App\Core\Configuracao::obterInstancia();

use App\Core\BancoDados;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\Colaborador\ModelColaborador;
use App\Services\Whatsapp\ServiceWhatsapp;
use App\Services\Whatsapp\ServiceWhatsappEntidade;
use App\Models\Whatsapp\ModelWhatsappQueue;
use App\Helpers\AuxiliarWhatsapp;

echo "========================================\n";
echo "TESTE DETALHADO - NOTIFICAÇÃO WHATSAPP\n";
echo "========================================\n\n";

try {
    $db = BancoDados::obterInstancia();
    $model = new ModelFrotaAbastecimento();

    // 1. Busca último abastecimento
    echo "1. Buscando último abastecimento...\n";
    $sql = "SELECT id FROM frotas_abastecimentos ORDER BY criado_em DESC LIMIT 1";
    $resultado = $db->buscarUm($sql);

    if (!$resultado) {
        echo "❌ ERRO: Nenhum abastecimento encontrado\n";
        exit(1);
    }

    $abastecimentoId = $resultado['id'];
    echo "✅ Abastecimento ID: {$abastecimentoId}\n\n";

    // 2. Busca detalhes
    echo "2. Buscando detalhes...\n";
    $abastecimento = $model->buscarComDetalhes($abastecimentoId);
    $modelColaborador = new ModelColaborador();
    $motorista = $modelColaborador->buscarPorId($abastecimento['colaborador_id']);

    echo "✅ Motorista: {$motorista['nome']}\n";
    echo "✅ Celular: {$motorista['celular']}\n\n";

    // 3. Testa validação do número
    echo "3. Testando validação do número...\n";
    $numeroValido = AuxiliarWhatsapp::validarNumero($motorista['celular']);

    if ($numeroValido) {
        echo "✅ Número é válido segundo AuxiliarWhatsapp::validarNumero()\n\n";
    } else {
        echo "❌ PROBLEMA ENCONTRADO: Número é INVÁLIDO segundo AuxiliarWhatsapp::validarNumero()\n";
        echo "   Número: {$motorista['celular']}\n";
        echo "   A notificação não será enviada por causa disso!\n\n";

        // Tenta descobrir o formato esperado
        echo "4. Verificando formato esperado...\n";
        $numerosParaTestar = [
            $motorista['celular'],
            '55' . $motorista['celular'],
            preg_replace('/[^0-9]/', '', $motorista['celular']),
            '55' . preg_replace('/[^0-9]/', '', $motorista['celular'])
        ];

        foreach ($numerosParaTestar as $teste) {
            $valido = AuxiliarWhatsapp::validarNumero($teste);
            $status = $valido ? '✅ VÁLIDO' : '❌ INVÁLIDO';
            echo "   {$status}: '{$teste}'\n";
        }

        exit(1);
    }

    // 4. Testa ServiceWhatsappEntidade
    echo "4. Testando ServiceWhatsappEntidade...\n";
    $entidadeService = new ServiceWhatsappEntidade();

    try {
        $destinatario = [
            'tipo' => 'colaborador',
            'id' => $motorista['id']
        ];

        $destino = $entidadeService->resolverDestinatario($destinatario);
        echo "✅ ServiceWhatsappEntidade::resolverDestinatario() funcionou\n";
        echo "   Tipo: {$destino['tipo_entidade']}\n";
        echo "   ID: {$destino['entidade_id']}\n";
        echo "   Número: {$destino['numero']}\n";
        echo "   Nome: {$destino['nome']}\n\n";
    } catch (Exception $e) {
        echo "❌ PROBLEMA ENCONTRADO: ServiceWhatsappEntidade::resolverDestinatario() falhou\n";
        echo "   Erro: {$e->getMessage()}\n";
        echo "   Stack: {$e->getTraceAsString()}\n";
        exit(1);
    }

    // 5. Verifica se tabela whatsapp_queue existe
    echo "5. Verificando tabela whatsapp_queue...\n";
    try {
        $sql = "SHOW TABLES LIKE 'whatsapp_queue'";
        $tabela = $db->buscarUm($sql);

        if ($tabela) {
            echo "✅ Tabela whatsapp_queue existe\n\n";
        } else {
            echo "❌ PROBLEMA ENCONTRADO: Tabela whatsapp_queue NÃO EXISTE\n";
            echo "   Execute a migration: database/migrations/2025_01_12_create_whatsapp_tables.sql\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar tabela: {$e->getMessage()}\n";
        exit(1);
    }

    // 6. Testa inserção direta na fila
    echo "6. Testando inserção DIRETA na fila...\n";
    $queueModel = new ModelWhatsappQueue();

    try {
        $dadosTeste = [
            'tipo_entidade' => 'colaborador',
            'entidade_id' => $motorista['id'],
            'entidade_nome' => $motorista['nome'],
            'tipo_mensagem' => 'text',
            'destinatario' => $motorista['celular'],
            'mensagem' => 'TESTE DE DIAGNÓSTICO - Esta é uma mensagem de teste',
            'prioridade' => 'alta',
            'status' => 'pendente',
            'tentativas' => 0,
            'status_code' => 1,
            'dados_extras' => json_encode([
                'modulo' => 'teste',
                'tipo' => 'diagnostico'
            ])
        ];

        $queueId = $queueModel->adicionar($dadosTeste);
        echo "✅ Inserção DIRETA funcionou! Queue ID: {$queueId}\n\n";

        // Verifica se foi inserido
        $verificacao = $db->buscarUm("SELECT * FROM whatsapp_queue WHERE id = ?", [$queueId]);
        if ($verificacao) {
            echo "✅ Confirmado: Registro existe na fila\n";
            echo "   ID: {$verificacao['id']}\n";
            echo "   Destinatário: {$verificacao['destinatario']}\n";
            echo "   Status: {$verificacao['status']}\n\n";

            // Limpa o teste
            $db->deletar('whatsapp_queue', 'id = ?', [$queueId]);
            echo "   (Registro de teste removido)\n\n";
        }
    } catch (Exception $e) {
        echo "❌ PROBLEMA ENCONTRADO: Erro ao inserir diretamente na fila\n";
        echo "   Erro: {$e->getMessage()}\n";
        echo "   Stack: {$e->getTraceAsString()}\n";
        exit(1);
    }

    // 7. Testa ServiceWhatsapp completo
    echo "7. Testando ServiceWhatsapp::enviarMensagem()...\n";
    $serviceWhatsapp = new ServiceWhatsapp();

    try {
        $resultado = $serviceWhatsapp->enviarMensagem([
            'destinatario' => [
                'tipo' => 'colaborador',
                'id' => $motorista['id']
            ],
            'tipo' => 'text',
            'mensagem' => '🧪 TESTE DE DIAGNÓSTICO - Verificando sistema de notificações',
            'prioridade' => 'alta',
            'metadata' => [
                'modulo' => 'teste_diagnostico',
                'tipo' => 'teste_completo'
            ]
        ]);

        if ($resultado['sucesso']) {
            echo "✅ ServiceWhatsapp::enviarMensagem() retornou SUCESSO\n";
            echo "   Modo: {$resultado['modo']}\n";
            echo "   Queue ID: {$resultado['queue_id']}\n\n";

            // Verifica se foi inserido na fila
            $verificacao = $db->buscarUm("SELECT * FROM whatsapp_queue WHERE id = ?", [$resultado['queue_id']]);

            if ($verificacao) {
                echo "✅ MENSAGEM FOI INSERIDA NA FILA!\n";
                echo "   ID: {$verificacao['id']}\n";
                echo "   Destinatário: {$verificacao['destinatario']}\n";
                echo "   Mensagem: {$verificacao['mensagem']}\n";
                echo "   Status: {$verificacao['status']}\n\n";

                // Limpa
                $db->deletar('whatsapp_queue', 'id = ?', [$resultado['queue_id']]);
                echo "   (Registro de teste removido)\n\n";
            } else {
                echo "❌ PROBLEMA: ServiceWhatsapp retornou sucesso mas NÃO INSERIU na fila!\n";
                exit(1);
            }
        } else {
            echo "❌ PROBLEMA ENCONTRADO: ServiceWhatsapp::enviarMensagem() retornou FALHA\n";
            echo "   Erro: {$resultado['erro']}\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ PROBLEMA ENCONTRADO: Exception ao chamar ServiceWhatsapp::enviarMensagem()\n";
        echo "   Erro: {$e->getMessage()}\n";
        echo "   Stack: {$e->getTraceAsString()}\n";
        exit(1);
    }

    echo "========================================\n";
    echo "✅ TODOS OS TESTES PASSARAM!\n";
    echo "========================================\n\n";

    echo "CONCLUSÃO:\n";
    echo "- A validação de número está OK\n";
    echo "- A tabela whatsapp_queue existe\n";
    echo "- A inserção direta funciona\n";
    echo "- O ServiceWhatsapp funciona\n\n";

    echo "⚠️  PROBLEMA PROVÁVEL:\n";
    echo "O ServiceFrotaAbastecimentoNotificacao está capturando\n";
    echo "uma exceção silenciosamente no try-catch (linha 78).\n\n";

    echo "PRÓXIMO PASSO:\n";
    echo "Verifique os logs de erro do PHP para ver se há alguma\n";
    echo "exceção sendo capturada silenciosamente.\n\n";

    echo "Execute: tail -f /var/log/apache2/error.log\n";
    echo "ou: tail -f /var/log/php_errors.log\n";

} catch (Exception $e) {
    echo "\n❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
