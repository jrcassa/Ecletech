<?php
ob_start();
session_start();

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/autoload.php";

use Config\Database;
use Helpers\Utils;
use Models\Administrador\Administrador;
use Models\Sistema\Modulos;
use Models\Whatsapp\WhatsAppSenderBaileys;
use Models\Whatsapp\WhatsAppConfiguracao;
use Models\Callback\Callback;

// BASE DE DADOS
$database = new Database();
$conn = $database->getConnection();

// MODELS
$Administrador = new Administrador($conn);
$Modulos = new Modulos($conn);
$WhatsAppConfig = new WhatsAppConfiguracao($conn);

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

        // Instancia API Baileys
        $WhatsAppSender = new WhatsAppSenderBaileys($conn);

        // ============================================
        // MÉTODO GET
        // ============================================

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $operacao = Utils::getQueryParam('op');

            // STATUS DA INSTÂNCIA
            if ($operacao == 'status-whatsapp') {
                $retorno_json['status'] = 'sucesso';

                // Busca informações da instância
                $informacao_instancia = json_decode($WhatsAppSender->info_instancia(), true);
                $retorno_json['status_instancia'] = 'qrcode';

                // INSTÂNCIA CONECTADA
                if (isset($informacao_instancia['error']) &&
                    $informacao_instancia['error'] == false &&
                    isset($informacao_instancia['instance_data']['phone_connected']) &&
                    $informacao_instancia['instance_data']['phone_connected'] == true) {

                    $retorno_json['status_instancia'] = 'conectado';

                    // Extrai número do telefone
                    if (isset($informacao_instancia['instance_data']['user']['id'])) {
                        preg_match('/^(\d+):/', $informacao_instancia['instance_data']['user']['id'], $matches);
                        $telefone = $matches[1] ?? '';

                        // Atualiza configurações
                        $WhatsAppConfig->salvar('instancia_status', 'conectado');
                        $WhatsAppConfig->salvar('instancia_telefone', $telefone);
                        $WhatsAppConfig->salvar('instancia_nome', $informacao_instancia['instance_data']['user']['name'] ?? '');
                        $WhatsAppConfig->salvar('instancia_data_conexao', date('Y-m-d H:i:s'));
                    }
                }
                // INSTÂNCIA NÃO EXISTE - CRIA
                elseif (isset($informacao_instancia['error']) &&
                        $informacao_instancia['error'] == true &&
                        $informacao_instancia['message'] == 'invalid key supplied') {

                    $WhatsAppSender->cria_instancia();
                    $retorno_json['instancia_criada'] = true;
                    $retorno_json['status_instancia'] = 'qrcode';
                    $retorno_json['instancia'] = json_decode($WhatsAppSender->status_instancia(), true);

                    // Atualiza status
                    $WhatsAppConfig->salvar('instancia_status', 'qrcode');
                }
                // AGUARDANDO QR CODE
                elseif (isset($informacao_instancia['error']) &&
                        $informacao_instancia['error'] == false &&
                        (!isset($informacao_instancia['instance_data']['phone_connected']) ||
                         $informacao_instancia['instance_data']['phone_connected'] == false)) {

                    $retorno_json['instancia'] = json_decode($WhatsAppSender->status_instancia(), true);
                    $retorno_json['status_instancia'] = 'qrcode';

                    // Atualiza status
                    $WhatsAppConfig->salvar('instancia_status', 'qrcode');
                }

                $retorno_json['instance_data'] = $informacao_instancia;
            }

            // DESCONECTAR INSTÂNCIA
            elseif ($operacao == 'desconectar-whatsapp') {

                // VERIFICA PERMISSÃO DE ALTERAÇÃO
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para desconectar a instância';
                } else {
                    $WhatsAppSender->logout_instancia();

                    // Atualiza configurações
                    $WhatsAppConfig->salvar('instancia_status', 'desconectado');
                    $WhatsAppConfig->salvar('instancia_telefone', '');
                    $WhatsAppConfig->salvar('instancia_nome', '');

                    $retorno_json['status'] = 'sucesso';
                    $retorno_json['mensagem'] = 'Instância desconectada com sucesso';
                }
            }

            // INFO DA INSTÂNCIA
            elseif ($operacao == 'info-instancia') {
                $info = json_decode($WhatsAppSender->info_instancia(), true);
                $retorno_json['status'] = 'sucesso';
                $retorno_json['info'] = $info;
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

            // CRIAR INSTÂNCIA
            if ($operacao == 'criar-instancia') {

                // VERIFICA PERMISSÃO DE ALTERAÇÃO
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para criar instância';
                } else {
                    $response = json_decode($WhatsAppSender->cria_instancia(), true);

                    if (isset($response['error']) && $response['error'] == false) {
                        $WhatsAppConfig->salvar('instancia_status', 'qrcode');
                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = 'Instância criada com sucesso';
                        $retorno_json['response'] = $response;
                    } else {
                        $retorno_json['status'] = 'erro';
                        $retorno_json['mensagem'] = $response['message'] ?? 'Erro ao criar instância';
                    }
                }
            }

            // DELETAR INSTÂNCIA - BLOQUEADO
            elseif ($operacao == 'deletar-instancia') {
                $retorno_json['status'] = 'permissao';
                $retorno_json['mensagem'] = 'A exclusão de instâncias não é permitida por questões de segurança';
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
