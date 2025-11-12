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
use Models\Whatsapp\WhatsAppConfiguracao;
use Services\Whatsapp\WhatsAppEntidadeService;
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
        $ConfigModel = new WhatsAppConfiguracao($conn);

        // ============================================
        // MÉTODO GET
        // ============================================

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $operacao = Utils::getQueryParam('op');

            // CARREGAR TODAS AS CONFIGURAÇÕES
            if ($operacao == 'carregar') {
                $categoria = Utils::getQueryParam('categoria');

                if ($categoria) {
                    // Busca por categoria
                    $query = "SELECT * FROM whatsapp_configuracoes WHERE categoria = :categoria ORDER BY chave";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([':categoria' => $categoria]);
                } else {
                    // Busca todas
                    $query = "SELECT * FROM whatsapp_configuracoes ORDER BY categoria, chave";
                    $stmt = $conn->query($query);
                }

                $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Organiza por categoria
                $organizadas = [];
                foreach ($configs as $config) {
                    $cat = $config['categoria'];
                    if (!isset($organizadas[$cat])) {
                        $organizadas[$cat] = [];
                    }
                    $organizadas[$cat][] = $config;
                }

                $retorno_json['status'] = 'sucesso';
                $retorno_json['configuracoes'] = $organizadas;
            }

            // OBTER CONFIGURAÇÃO ESPECÍFICA
            elseif ($operacao == 'obter') {
                $chave = Utils::getQueryParam('chave');

                if (empty($chave)) {
                    $retorno_json['status'] = 'parametros';
                    $retorno_json['mensagem'] = 'Chave é obrigatória';
                } else {
                    $valor = $ConfigModel->obter($chave);

                    $retorno_json['status'] = 'sucesso';
                    $retorno_json['chave'] = $chave;
                    $retorno_json['valor'] = $valor;
                }
            }

            // LISTAR CATEGORIAS
            elseif ($operacao == 'categorias') {
                $query = "SELECT DISTINCT categoria FROM whatsapp_configuracoes ORDER BY categoria";
                $stmt = $conn->query($query);
                $categorias = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                $retorno_json['status'] = 'sucesso';
                $retorno_json['categorias'] = $categorias;
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

            // SALVAR CONFIGURAÇÃO
            if ($operacao == 'salvar') {

                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para alterar configurações';
                } else {
                    $chave = Utils::protect($_POST['chave'] ?? '');
                    $valor = $_POST['valor'] ?? '';

                    if (empty($chave)) {
                        $retorno_json['status'] = 'parametros';
                        $retorno_json['mensagem'] = 'Chave é obrigatória';
                    } else {
                        $sucesso = $ConfigModel->salvar($chave, $valor);

                        if ($sucesso) {
                            $retorno_json['status'] = 'sucesso';
                            $retorno_json['mensagem'] = 'Configuração salva com sucesso';
                        } else {
                            $retorno_json['status'] = 'erro';
                            $retorno_json['mensagem'] = 'Erro ao salvar configuração';
                        }
                    }
                }
            }

            // SALVAR MÚLTIPLAS CONFIGURAÇÕES
            elseif ($operacao == 'salvar-multiplas') {

                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para alterar configurações';
                } else {
                    $configuracoes = $_POST['configuracoes'] ?? [];

                    if (empty($configuracoes) || !is_array($configuracoes)) {
                        $retorno_json['status'] = 'parametros';
                        $retorno_json['mensagem'] = 'Array de configurações é obrigatório';
                    } else {
                        $salvos = 0;
                        $erros = 0;

                        foreach ($configuracoes as $chave => $valor) {
                            if ($ConfigModel->salvar($chave, $valor)) {
                                $salvos++;
                            } else {
                                $erros++;
                            }
                        }

                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = "Salvos: {$salvos}, Erros: {$erros}";
                        $retorno_json['salvos'] = $salvos;
                        $retorno_json['erros'] = $erros;
                    }
                }
            }

            // SINCRONIZAR ENTIDADE
            elseif ($operacao == 'sincronizar-entidade') {

                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para sincronizar entidades';
                } else {
                    $tipo = Utils::protect($_POST['tipo'] ?? '');
                    $id = (int) ($_POST['id'] ?? 0);

                    if (empty($tipo) || $id <= 0) {
                        $retorno_json['status'] = 'parametros';
                        $retorno_json['mensagem'] = 'Tipo e ID são obrigatórios';
                    } else {
                        $resultado = $WhatsAppService->sincronizarEntidade($tipo, $id);

                        if ($resultado['sucesso']) {
                            $retorno_json['status'] = 'sucesso';
                            $retorno_json['mensagem'] = 'Entidade sincronizada com sucesso';
                            $retorno_json['entidade'] = $resultado['entidade'];
                        } else {
                            $retorno_json['status'] = 'erro';
                            $retorno_json['mensagem'] = $resultado['erro'];
                        }
                    }
                }
            }

            // SINCRONIZAR LOTE DE ENTIDADES
            elseif ($operacao == 'sincronizar-lote') {

                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para sincronizar entidades';
                } else {
                    $tipo = Utils::protect($_POST['tipo'] ?? '');
                    $limit = (int) ($_POST['limit'] ?? 100);
                    $offset = (int) ($_POST['offset'] ?? 0);

                    if (empty($tipo)) {
                        $retorno_json['status'] = 'parametros';
                        $retorno_json['mensagem'] = 'Tipo é obrigatório';
                    } else {
                        $resultado = $WhatsAppService->sincronizarLote($tipo, $limit, $offset);

                        $retorno_json['status'] = 'sucesso';
                        $retorno_json['mensagem'] = "Sincronizados: {$resultado['sincronizados']}, Erros: {$resultado['erros']}";
                        $retorno_json['sincronizados'] = $resultado['sincronizados'];
                        $retorno_json['erros'] = $resultado['erros'];
                    }
                }
            }

            // RESETAR CONFIGURAÇÃO PARA PADRÃO
            elseif ($operacao == 'resetar') {

                // Verifica permissão de alteração
                if (!$pode_alterar) {
                    $retorno_json['status'] = 'permissao';
                    $retorno_json['mensagem'] = 'Você não tem permissão para resetar configurações';
                } else {
                    $chave = Utils::protect($_POST['chave'] ?? '');

                    if (empty($chave)) {
                        $retorno_json['status'] = 'parametros';
                        $retorno_json['mensagem'] = 'Chave é obrigatória';
                    } else {
                        // Busca valor padrão
                        $query = "SELECT valor_padrao FROM whatsapp_configuracoes WHERE chave = :chave";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([':chave' => $chave]);
                        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($config) {
                            $ConfigModel->salvar($chave, $config['valor_padrao']);

                            $retorno_json['status'] = 'sucesso';
                            $retorno_json['mensagem'] = 'Configuração resetada para padrão';
                            $retorno_json['valor'] = $config['valor_padrao'];
                        } else {
                            $retorno_json['status'] = 'erro';
                            $retorno_json['mensagem'] = 'Configuração não encontrada';
                        }
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
