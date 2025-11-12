<?php
ob_start();
session_start();

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/autoload.php";

use Config\Database;
use Helpers\Utils;
use Models\Administrador\Administrador;
use Models\Sistema\Modulos;
use Services\Whatsapp\WhatsAppService;
use Models\Callback\Callback;

// BASE DE DADOS
$database = new Database();
$conn = $database->getConnection();

// MODELS
$Administrador = new Administrador($conn);
$Modulos = new Modulos($conn);

// VALIDAÇÃO DE SESSÃO
$retorno_json = ['sessao' => $Administrador->valida_sessao(), 'status' => 'parametros'];

try {
    $conn->beginTransaction();

    if ($retorno_json['sessao']) {

        // ============================================
        // VERIFICAÇÃO DE PERMISSÕES (ACL)
        // ============================================

        $modulo_id = 'whatsapp';
        $permissoes = $Modulos->verificar_permissoes($Administrador->id, $modulo_id);

        // Define permissões padrão
        if (!$permissoes) {
            $tem_acesso = ($Administrador->nivel == "5" || $Administrador->nivel == "0");
            $pode_acessar = $tem_acesso;
            $pode_alterar = $tem_acesso;
            $pode_deletar = false; // NUNCA pode deletar
        } else {
            $pode_acessar = $permissoes['acessar'] ?? false;
            $pode_alterar = $permissoes['alterar'] ?? false;
            $pode_deletar = false; // FORÇA false - NÃO pode deletar
        }

        // Verifica permissão de acesso
        if (!$pode_acessar) {
            $retorno_json['status'] = 'permissao';
            $retorno_json['mensagem'] = 'Você não tem permissão para acessar este módulo';
            echo json_encode($retorno_json);
            exit;
        }

        // Verifica permissão de alteração (enviar requer permissão de alterar)
        if (!$pode_alterar) {
            $retorno_json['status'] = 'permissao';
            $retorno_json['mensagem'] = 'Você não tem permissão para enviar mensagens';
            echo json_encode($retorno_json);
            exit;
        }

        // Instancia WhatsApp Service
        $WhatsAppService = new WhatsAppService($conn);

        // ============================================
        // MÉTODO POST
        // ============================================

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $operacao = Utils::protect($_POST['op'] ?? '');

            // ENVIAR MENSAGEM
            if ($operacao == 'enviar') {
                $destinatario = Utils::protect($_POST['destinatario'] ?? '');
                $tipo = Utils::protect($_POST['tipo'] ?? 'text');
                $mensagem = $_POST['mensagem'] ?? $_POST['conteudo'] ?? '';
                $arquivo_url = $_POST['arquivo_url'] ?? null;
                $arquivo_base64 = $_POST['arquivo_base64'] ?? null;
                $arquivo_nome = Utils::protect($_POST['arquivo_nome'] ?? '');
                $prioridade = (int) ($_POST['prioridade'] ?? 5);
                $agendado_para = $_POST['agendado_para'] ?? null;
                $metadata = $_POST['metadata'] ?? null;

                // Valida destinatário
                if (empty($destinatario)) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'Destinatário é obrigatório';
                }
                // Valida tipo
                elseif (!in_array($tipo, ['text', 'image', 'pdf', 'audio', 'video', 'document'])) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'Tipo de mensagem inválido';
                }
                // Valida conteúdo para texto
                elseif ($tipo == 'text' && empty($mensagem)) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'Mensagem é obrigatória para tipo texto';
                }
                // Valida arquivo para outros tipos
                elseif ($tipo != 'text' && empty($arquivo_url) && empty($arquivo_base64)) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'URL ou base64 do arquivo é obrigatório';
                }
                else {
                    // Prepara dados
                    $dados = [
                        'destinatario' => $destinatario,
                        'tipo' => $tipo,
                        'mensagem' => $mensagem,
                        'arquivo_url' => $arquivo_url,
                        'arquivo_base64' => $arquivo_base64,
                        'arquivo_nome' => $arquivo_nome,
                        'prioridade' => $prioridade,
                        'agendado_para' => $agendado_para,
                        'metadata' => $metadata
                    ];

                    // Envia
                    $resultado = $WhatsAppService->enviar($dados);

                    if ($resultado['sucesso']) {
                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = $resultado['mensagem'];
                        $retorno_json['queue_id'] = $resultado['queue_id'];
                    } else {
                        $retorno_json['status'] = 'erro';
                        $retorno_json['mensagem'] = $resultado['erro'];
                    }
                }
            }

            // CANCELAR MENSAGEM
            elseif ($operacao == 'cancelar') {
                $queue_id = (int) ($_POST['queue_id'] ?? 0);

                if ($queue_id <= 0) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'ID da mensagem é obrigatório';
                } else {
                    $resultado = $WhatsAppService->cancelarMensagem($queue_id);

                    if ($resultado['sucesso']) {
                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = $resultado['mensagem'];
                    } else {
                        $retorno_json['status'] = 'erro';
                        $retorno_json['mensagem'] = $resultado['erro'];
                    }
                }
            }

            // REPROCESSAR MENSAGEM
            elseif ($operacao == 'reprocessar') {
                $queue_id = (int) ($_POST['queue_id'] ?? 0);

                if ($queue_id <= 0) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'ID da mensagem é obrigatório';
                } else {
                    $resultado = $WhatsAppService->reprocessarMensagem($queue_id);

                    if ($resultado['sucesso']) {
                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = $resultado['mensagem'];
                    } else {
                        $retorno_json['status'] = 'erro';
                        $retorno_json['mensagem'] = $resultado['erro'];
                    }
                }
            }

            // OPERAÇÃO NÃO RECONHECIDA
            else {
                $retorno_json['status'] = 'parametros';
                $retorno_json['mensagem'] = 'Operação não reconhecida';
            }
        }

        // ============================================
        // MÉTODO GET
        // ============================================

        elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $operacao = Utils::getQueryParam('op');

            // LISTAR FILA
            if ($operacao == 'listar-fila') {
                $limit = (int) (Utils::getQueryParam('limit') ?? 50);
                $offset = (int) (Utils::getQueryParam('offset') ?? 0);
                $status = Utils::getQueryParam('status');

                $query = "SELECT * FROM whatsapp_queue";
                $where = [];
                $params = [];

                if ($status !== null) {
                    $where[] = "status_code = :status";
                    $params[':status'] = $status;
                }

                if (!empty($where)) {
                    $query .= " WHERE " . implode(" AND ", $where);
                }

                $query .= " ORDER BY prioridade DESC, criado_em DESC LIMIT :limit OFFSET :offset";

                $stmt = $conn->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
                $stmt->execute();

                $retorno_json['status'] = 'sucesso';
                $retorno_json['mensagens'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            // ESTATÍSTICAS
            elseif ($operacao == 'estatisticas') {
                $retorno_json['status'] = 'sucesso';
                $retorno_json['estatisticas'] = $WhatsAppService->obterEstatisticasFila();
            }

            // HISTÓRICO DE MENSAGEM
            elseif ($operacao == 'historico-mensagem') {
                $message_id = Utils::getQueryParam('message_id');

                if (empty($message_id)) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'message_id é obrigatório';
                } else {
                    $retorno_json['status'] = 'sucesso';
                    $retorno_json['historico'] = $WhatsAppService->obterHistoricoMensagem($message_id);
                }
            }

            // OPERAÇÃO NÃO RECONHECIDA
            else {
                $retorno_json['status'] = 'parametros';
                $retorno_json['mensagem'] = 'Operação não reconhecida';
            }
        }

        // ============================================
        // MÉTODO NÃO SUPORTADO
        // ============================================

        else {
            $retorno_json['status'] = 'erro';
            $retorno_json['mensagem'] = 'Método HTTP não suportado';
        }

    } else {
        // Sessão inválida
        $retorno_json['status'] = 'sessao';
        $retorno_json['mensagem'] = 'Sessão expirada';
    }

    $conn->commit();

} catch (\Throwable $e) {
    $conn->rollBack();

    // Registra callback de erro
    $Callback = new Callback($conn);
    $method = strtoupper($_SERVER['REQUEST_METHOD']);
    $context = $method === 'POST' ? $_POST : $_GET;
    $Callback->adicionaCallback($Administrador->id ?? 0, $e, $context);

    $retorno_json['status'] = 'erro';
    $retorno_json['mensagem'] = 'Erro interno: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($retorno_json);
ob_end_flush();
