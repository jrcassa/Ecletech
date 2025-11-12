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
use Models\Whatsapp\WhatsAppQueue;
use Models\Whatsapp\WhatsAppHistorico;
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

        // Instancia Services e Models
        $WhatsAppService = new WhatsAppService($conn);
        $QueueModel = new WhatsAppQueue($conn);
        $HistoricoModel = new WhatsAppHistorico($conn);

        // ============================================
        // MÉTODO GET
        // ============================================

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $operacao = Utils::getQueryParam('op');

            // DASHBOARD - ESTATÍSTICAS GERAIS
            if ($operacao == 'dashboard') {
                $retorno_json['status'] = 'sucesso';
                $retorno_json['dashboard'] = $WhatsAppService->obterDashboard();
            }

            // CARREGAR FILA
            elseif ($operacao == 'carregar-fila') {
                $limit = (int) (Utils::getQueryParam('limit') ?? 50);
                $offset = (int) (Utils::getQueryParam('offset') ?? 0);
                $status = Utils::getQueryParam('status');

                $query = "SELECT q.*,
                          CASE
                              WHEN q.status_code = 0 THEN 'Erro'
                              WHEN q.status_code = 1 THEN 'Pendente'
                              WHEN q.status_code = 2 THEN 'Enviado'
                              WHEN q.status_code = 3 THEN 'Entregue'
                              WHEN q.status_code = 4 THEN 'Lido'
                              ELSE 'Desconhecido'
                          END as status_nome
                          FROM whatsapp_queue q";

                $where = [];
                $params = [];

                if ($status !== null && $status !== '') {
                    $where[] = "q.status_code = :status";
                    $params[':status'] = $status;
                }

                if (!empty($where)) {
                    $query .= " WHERE " . implode(" AND ", $where);
                }

                $query .= " ORDER BY q.prioridade DESC, q.criado_em DESC LIMIT :limit OFFSET :offset";

                $stmt = $conn->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
                $stmt->execute();

                // Conta total
                $queryCount = "SELECT COUNT(*) as total FROM whatsapp_queue q";
                if (!empty($where)) {
                    $queryCount .= " WHERE " . implode(" AND ", $where);
                }
                $stmtCount = $conn->prepare($queryCount);
                foreach ($params as $key => $value) {
                    $stmtCount->bindValue($key, $value);
                }
                $stmtCount->execute();
                $total = $stmtCount->fetch(\PDO::FETCH_ASSOC)['total'];

                $retorno_json['status'] = 'sucesso';
                $retorno_json['fila'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $retorno_json['total'] = $total;
                $retorno_json['limit'] = $limit;
                $retorno_json['offset'] = $offset;
            }

            // CARREGAR HISTÓRICO
            elseif ($operacao == 'carregar-historico') {
                $limit = (int) (Utils::getQueryParam('limit') ?? 50);
                $offset = (int) (Utils::getQueryParam('offset') ?? 0);
                $data_inicio = Utils::getQueryParam('data_inicio');
                $data_fim = Utils::getQueryParam('data_fim');
                $tipo_evento = Utils::getQueryParam('tipo_evento');

                $query = "SELECT h.*, q.destinatario, q.tipo_mensagem
                          FROM whatsapp_historico h
                          LEFT JOIN whatsapp_queue q ON h.queue_id = q.id";

                $where = [];
                $params = [];

                if ($data_inicio) {
                    $where[] = "h.criado_em >= :data_inicio";
                    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
                }

                if ($data_fim) {
                    $where[] = "h.criado_em <= :data_fim";
                    $params[':data_fim'] = $data_fim . ' 23:59:59';
                }

                if ($tipo_evento) {
                    $where[] = "h.tipo_evento = :tipo_evento";
                    $params[':tipo_evento'] = $tipo_evento;
                }

                if (!empty($where)) {
                    $query .= " WHERE " . implode(" AND ", $where);
                }

                $query .= " ORDER BY h.criado_em DESC LIMIT :limit OFFSET :offset";

                $stmt = $conn->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
                $stmt->execute();

                // Conta total
                $queryCount = "SELECT COUNT(*) as total FROM whatsapp_historico h";
                if (!empty($where)) {
                    $queryCount .= " WHERE " . implode(" AND ", $where);
                }
                $stmtCount = $conn->prepare($queryCount);
                foreach ($params as $key => $value) {
                    $stmtCount->bindValue($key, $value);
                }
                $stmtCount->execute();
                $total = $stmtCount->fetch(\PDO::FETCH_ASSOC)['total'];

                $retorno_json['status'] = 'sucesso';
                $retorno_json['historico'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $retorno_json['total'] = $total;
                $retorno_json['limit'] = $limit;
                $retorno_json['offset'] = $offset;
            }

            // ESTATÍSTICAS DA FILA
            elseif ($operacao == 'estatisticas-fila') {
                $retorno_json['status'] = 'sucesso';
                $retorno_json['estatisticas'] = $WhatsAppService->obterEstatisticasFila();
            }

            // ESTATÍSTICAS DE RETRY
            elseif ($operacao == 'estatisticas-retry') {
                $retorno_json['status'] = 'sucesso';
                $retorno_json['estatisticas'] = $WhatsAppService->obterEstatisticasRetry();
            }

            // BUSCAR MENSAGEM POR ID
            elseif ($operacao == 'buscar-mensagem') {
                $queue_id = (int) Utils::getQueryParam('queue_id');

                if ($queue_id <= 0) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'queue_id é obrigatório';
                } else {
                    $mensagem = $QueueModel->buscarPorId($queue_id);

                    if ($mensagem) {
                        // Busca histórico da mensagem
                        $historico = $HistoricoModel->buscarPorQueueId($queue_id);

                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = $mensagem;
                        $retorno_json['historico'] = $historico;
                    } else {
                        $retorno_json['status'] = 'erro';
                        $retorno_json['mensagem'] = 'Mensagem não encontrada';
                    }
                }
            }

            // VERIFICAR PERMISSÕES DO USUÁRIO
            elseif ($operacao == 'verificar-permissoes') {
                $retorno_json['status'] = 'sucesso';
                $retorno_json['permissoes'] = [
                    'acessar' => $pode_acessar,
                    'alterar' => $pode_alterar,
                    'deletar' => $pode_deletar
                ];
            }

            // OPERAÇÃO NÃO RECONHECIDA
            else {
                $retorno_json['status'] = 'parametros';
                $retorno_json['mensagem'] = 'Operação não reconhecida';
            }
        }

        // ============================================
        // MÉTODO POST
        // ============================================

        elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $operacao = Utils::protect($_POST['op'] ?? '');

            // PROCESSAR FILA MANUALMENTE
            if ($operacao == 'processar-fila') {
                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para processar a fila';
                } else {
                    $limit = (int) ($_POST['limit'] ?? 10);
                    $resultado = $WhatsAppService->processarFila($limit);

                    $retorno_json['status'] = 'sucesso';
                    $retorno_json['resultado'] = $resultado;
                    $retorno_json['mensagem'] = "Processadas: {$resultado['processadas']}, Sucesso: {$resultado['sucesso']}, Erro: {$resultado['erro']}";
                }
            }

            // EXECUTAR LIMPEZA
            elseif ($operacao == 'executar-limpeza') {
                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para executar limpeza';
                } else {
                    $resultado = $WhatsAppService->executarLimpeza();

                    $retorno_json['status'] = 'sucesso';
                    $retorno_json['resultado'] = $resultado;
                    $retorno_json['mensagem'] = "Removidos {$resultado['total_removidos']} registros antigos";
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
