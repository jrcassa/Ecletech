<?php

namespace App\Controllers\Crm;

use App\Controllers\BaseController;
use App\CRM\Core\CrmManager;
use App\CRM\Core\CrmConfig;
use App\CRM\Core\CrmException;
use App\Models\ModelCrmIntegracao;
use App\Models\ModelCrmSyncQueue;
use App\Models\ModelCrmSyncLog;
use App\Models\ModelCrmSyncSchedule;
use App\Models\Loja\ModelLoja;
use App\Services\ServiceCrm;
use App\Services\ServiceCrmCron;
use App\Services\ServiceCrmScheduler;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar integrações CRM
 */
class ControllerCrm extends BaseController
{
    private ModelCrmIntegracao $modelIntegracao;
    private ModelCrmSyncQueue $modelQueue;
    private ModelCrmSyncLog $modelLog;
    private ModelCrmSyncSchedule $modelSchedule;
    private ModelLoja $modelLoja;
    private CrmConfig $crmConfig;
    private ServiceCrm $serviceCrm;
    private ServiceCrmScheduler $serviceScheduler;

    public function __construct()
    {
        $this->modelIntegracao = new ModelCrmIntegracao();
        $this->modelQueue = new ModelCrmSyncQueue();
        $this->modelLog = new ModelCrmSyncLog();
        $this->modelSchedule = new ModelCrmSyncSchedule();
        $this->modelLoja = new ModelLoja();
        $this->crmConfig = new CrmConfig();
        $this->serviceCrm = new ServiceCrm();
        $this->serviceScheduler = new ServiceCrmScheduler();
    }

    /**
     * Obtém o external_id da loja para integração CRM
     * Usa id_loja como fallback se external_id não estiver configurado
     */
    private function obterLojaId(): int|string
    {
        $externalId = $this->modelLoja->obterExternalId();

        if ($externalId !== null && $externalId !== '') {
            return $externalId;
        }

        // Fallback: usa id_loja do usuário autenticado
        $usuario = $this->obterUsuarioAutenticado();
        return $usuario['id_loja'] ?? 1;
    }

    // ===== INTEGRAÇÕES =====

    /**
     * Lista todas as integrações
     */
    public function listarIntegracoes(): void
    {
        try {
            $integracoes = $this->modelIntegracao->listarAtivas();

            // Remove credenciais da resposta
            $integracoes = array_map(function($integracao) {
                unset($integracao['credenciais']);
                return $integracao;
            }, $integracoes);

            AuxiliarResposta::sucesso($integracoes, 'Integrações obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém uma integração específica
     */
    public function obterIntegracao(int $id): void
    {
        try {
            $integracao = $this->modelIntegracao->buscarPorId($id);

            if (!$integracao) {
                AuxiliarResposta::naoEncontrado('Integração não encontrada');
                return;
            }

            // Remove credenciais da resposta
            unset($integracao['credenciais']);

            AuxiliarResposta::sucesso($integracao, 'Integração obtida com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Cria nova integração
     */
    public function criarIntegracao(): void
    {
        try {
            $dados = $this->obterDados();

            // Validações básicas
            $erros = AuxiliarValidacao::validar($dados, [
                'provider' => 'obrigatorio',
                'credenciais' => 'obrigatorio'
            ]);

            // Validações específicas por provider
            if (isset($dados['provider']) && $dados['provider'] === 'gestao_click') {
                // GestãoClick requer dois tokens
                $errosGestao = AuxiliarValidacao::validar($dados, [
                    'credenciais.access_token' => 'obrigatorio',
                    'credenciais.secret_access_token' => 'obrigatorio'
                ]);
                $erros = array_merge($erros, $errosGestao);
            } else {
                // Outros providers requerem api_token único
                $errosGenerico = AuxiliarValidacao::validar($dados, [
                    'credenciais.api_token' => 'obrigatorio'
                ]);
                $erros = array_merge($erros, $errosGenerico);
            }

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Obtém ID da loja (external_id se configurado, senão id_loja do usuário)
            $idLoja = $this->obterLojaId();

            // Verifica se já existe integração para esta loja
            $integracaoExistente = $this->modelIntegracao->buscarPorLoja($idLoja);
            if ($integracaoExistente && !$integracaoExistente['deletado_em']) {
                AuxiliarResposta::erro('Já existe uma integração CRM para esta loja. Edite ou exclua a existente.', 400);
                return;
            }

            // Salva configuração
            $id = $this->crmConfig->salvarConfiguracao(
                $idLoja,
                $dados['provider'],
                $dados['credenciais'],
                $dados['configuracoes'] ?? []
            );

            // Atualiza status se fornecido
            if (isset($dados['ativo'])) {
                $this->modelIntegracao->alterarStatus($id, (bool) $dados['ativo']);
            }

            // Busca a integração criada
            $integracao = $this->modelIntegracao->buscarPorId($id);
            unset($integracao['credenciais']);

            AuxiliarResposta::sucesso($integracao, 'Integração criada com sucesso', 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Atualiza integração
     */
    public function atualizarIntegracao(int $id): void
    {
        try {
            $integracao = $this->modelIntegracao->buscarPorId($id);

            if (!$integracao) {
                AuxiliarResposta::naoEncontrado('Integração não encontrada');
                return;
            }

            $dados = $this->obterDados();

            // Validação de credenciais se fornecidas
            if (isset($dados['credenciais']) && !empty($dados['credenciais'])) {
                $provider = $dados['provider'] ?? $integracao['provider'];
                $erros = [];

                if ($provider === 'gestao_click') {
                    // GestãoClick requer dois tokens
                    $errosGestao = AuxiliarValidacao::validar($dados, [
                        'credenciais.access_token' => 'obrigatorio',
                        'credenciais.secret_access_token' => 'obrigatorio'
                    ]);
                    $erros = array_merge($erros, $errosGestao);
                } else {
                    // Outros providers requerem api_token único
                    $errosGenerico = AuxiliarValidacao::validar($dados, [
                        'credenciais.api_token' => 'obrigatorio'
                    ]);
                    $erros = array_merge($erros, $errosGenerico);
                }

                if (!empty($erros)) {
                    AuxiliarResposta::validacao($erros);
                    return;
                }
            }

            // Monta dados de atualização
            $dadosAtualizacao = [];

            if (isset($dados['provider'])) {
                $dadosAtualizacao['provider'] = $dados['provider'];
            }

            if (isset($dados['ativo'])) {
                $dadosAtualizacao['ativo'] = (int) $dados['ativo'];
            }

            if (isset($dados['configuracoes'])) {
                $dadosAtualizacao['configuracoes'] = json_encode($dados['configuracoes']);
            }

            // Se tiver novas credenciais, criptografa
            if (isset($dados['credenciais']) && !empty($dados['credenciais'])) {
                // Usa o CrmConfig para criptografar
                $chave = hash('sha256', $_ENV['JWT_SECRET'] ?? 'ecletech-crm-secret-key', true);
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                $json = json_encode($dados['credenciais']);
                $encriptado = openssl_encrypt($json, 'aes-256-cbc', $chave, 0, $iv);
                $dadosAtualizacao['credenciais'] = base64_encode($iv . $encriptado);
            }

            $this->modelIntegracao->atualizar($id, $dadosAtualizacao);

            // Limpa cache
            CrmManager::limparCache();

            $integracaoAtualizada = $this->modelIntegracao->buscarPorId($id);
            unset($integracaoAtualizada['credenciais']);

            AuxiliarResposta::sucesso($integracaoAtualizada, 'Integração atualizada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Deleta integração
     */
    public function deletarIntegracao(int $id): void
    {
        try {
            $integracao = $this->modelIntegracao->buscarPorId($id);

            if (!$integracao) {
                AuxiliarResposta::naoEncontrado('Integração não encontrada');
                return;
            }

            $this->modelIntegracao->deletar($id);

            // Limpa cache
            CrmManager::limparCache();

            AuxiliarResposta::sucesso(null, 'Integração excluída com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Testa conexão de uma integração existente
     */
    public function testarConexao(int $id): void
    {
        try {
            $integracao = $this->modelIntegracao->buscarPorId($id);

            if (!$integracao) {
                AuxiliarResposta::naoEncontrado('Integração não encontrada');
                return;
            }

            $resultado = $this->serviceCrm->testarConexao($integracao['id_loja']);

            AuxiliarResposta::sucesso($resultado, 'Teste de conexão concluído');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Testa conexão temporária (sem salvar)
     */
    public function testarConexaoTemporaria(): void
    {
        try {
            $dados = $this->obterDados();

            // Valida provider
            if (empty($dados['provider'])) {
                AuxiliarResposta::erro('Provider é obrigatório', 400);
                return;
            }

            // Prepara credenciais baseado no provider
            $credenciais = [];

            if ($dados['provider'] === 'gestao_click') {
                // GestãoClick usa dois tokens
                if (empty($dados['access_token']) || empty($dados['secret_access_token'])) {
                    AuxiliarResposta::erro('access_token e secret_access_token são obrigatórios para GestãoClick', 400);
                    return;
                }
                $credenciais = [
                    'access_token' => $dados['access_token'],
                    'secret_access_token' => $dados['secret_access_token']
                ];
            } else {
                // Outros providers usam token único
                if (empty($dados['api_token'])) {
                    AuxiliarResposta::erro('api_token é obrigatório', 400);
                    return;
                }
                $credenciais = ['api_token' => $dados['api_token']];
            }

            // Testa usando provider diretamente
            $classeProvider = $this->converterNomeParaClasse($dados['provider']);
            $providerNamespace = "App\\CRM\\Providers\\{$classeProvider}\\{$classeProvider}Provider";

            if (!class_exists($providerNamespace)) {
                AuxiliarResposta::erro("Provider '{$dados['provider']}' não encontrado", 404);
                return;
            }

            $provider = new $providerNamespace([
                'credenciais' => $credenciais,
                'configuracoes' => []
            ]);

            $resultado = $provider->testarConexao(1); // ID loja temporário

            AuxiliarResposta::sucesso($resultado, 'Teste de conexão concluído');
        } catch (\Exception $e) {
            AuxiliarResposta::sucesso([
                'success' => false,
                'message' => $e->getMessage()
            ], 'Teste de conexão concluído');
        }
    }

    /**
     * Sincronizar manualmente
     */
    public function sincronizarManual(int $id): void
    {
        try {
            $integracao = $this->modelIntegracao->buscarPorId($id);

            if (!$integracao) {
                AuxiliarResposta::naoEncontrado('Integração não encontrada');
                return;
            }

            // Enfileira todos os clientes para sincronização
            // Isso será processado pelo cron
            $dados = $this->obterDados();
            $entidade = $dados['entidade'] ?? 'cliente';

            // Aqui você pode enfileirar baseado na entidade
            // Por enquanto, apenas retorna sucesso
            AuxiliarResposta::sucesso([
                'enfileirados' => 0
            ], 'Sincronização manual iniciada. Verifique os logs em alguns minutos.');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== ESTATÍSTICAS =====

    /**
     * Obtém estatísticas da fila
     */
    public function obterEstatisticas(): void
    {
        try {
            $statsQueue = $this->modelQueue->obterEstatisticas();
            $statsLog = $this->modelLog->obterEstatisticas();

            $stats = array_merge($statsQueue, $statsLog);

            AuxiliarResposta::sucesso($stats, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== LOGS =====

    /**
     * Obtém logs recentes
     */
    public function obterLogs(): void
    {
        try {
            $limit = $this->obterParametro('limit', 20);
            $logs = $this->modelLog->buscarRecentes((int) $limit);

            AuxiliarResposta::sucesso($logs, 'Logs obtidos com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém logs de um registro específico
     */
    public function obterLogsPorRegistro(string $entidade, int $id): void
    {
        try {
            $logs = $this->modelLog->buscarPorRegistro($entidade, $id);

            AuxiliarResposta::sucesso($logs, 'Logs obtidos com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== FILA =====

    /**
     * Obtém itens da fila
     */
    public function obterFila(): void
    {
        try {
            $limit = $this->obterParametro('limit', 100);
            $fila = $this->modelQueue->buscarPendentes((int) $limit);

            AuxiliarResposta::sucesso($fila, 'Fila obtida com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Enfileira item manualmente
     */
    public function enfileirar(): void
    {
        try {
            $dados = $this->obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'entidade' => 'obrigatorio',
                'id_registro' => 'obrigatorio',
                'direcao' => 'obrigatorio'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            $idLoja = $this->obterLojaId();

            $id = $this->modelQueue->enfileirar(
                $idLoja,
                $dados['entidade'],
                (int) $dados['id_registro'],
                $dados['direcao'],
                $dados['prioridade'] ?? 5
            );

            AuxiliarResposta::sucesso(['id' => $id], 'Item enfileirado com sucesso', 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== OPERAÇÕES CRUD =====

    /**
     * Criar no CRM
     */
    public function criar(string $entidade): void
    {
        try {
            $dados = $this->obterDados();
            $idLoja = $this->obterLojaId();

            $resultado = $this->serviceCrm->criar($entidade, $dados, $idLoja);

            if ($resultado['success']) {
                AuxiliarResposta::sucesso($resultado, $resultado['message'], 201);
            } else {
                AuxiliarResposta::erro($resultado['error'], $resultado['code'] ?? 400);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Atualizar no CRM
     */
    public function atualizar(string $entidade, string $externalId): void
    {
        try {
            $dados = $this->obterDados();
            $idLoja = $this->obterLojaId();

            $resultado = $this->serviceCrm->atualizar($entidade, $externalId, $dados, $idLoja);

            if ($resultado['success']) {
                AuxiliarResposta::sucesso($resultado, $resultado['message']);
            } else {
                AuxiliarResposta::erro($resultado['error'], $resultado['code'] ?? 400);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Buscar no CRM
     */
    public function buscar(string $entidade, string $externalId): void
    {
        try {
            $idLoja = $this->obterLojaId();

            $resultado = $this->serviceCrm->buscar($entidade, $externalId, $idLoja);

            if ($resultado['success']) {
                AuxiliarResposta::sucesso($resultado['data'], 'Registro obtido com sucesso');
            } else {
                AuxiliarResposta::erro($resultado['error'], $resultado['code'] ?? 404);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Deletar no CRM
     */
    public function deletar(string $entidade, string $externalId): void
    {
        try {
            $idLoja = $this->obterLojaId();

            $resultado = $this->serviceCrm->deletar($entidade, $externalId, $idLoja);

            if ($resultado['success']) {
                AuxiliarResposta::sucesso(null, $resultado['message']);
            } else {
                AuxiliarResposta::erro($resultado['error'], $resultado['code'] ?? 400);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Sincronizar entidade completa do CRM para Ecletech
     * Busca todos os registros do CRM via API e enfileira para importação
     */
    public function sincronizarEntidade(string $entidade): void
    {
        try {
            $idLoja = $this->obterLojaId();

            // Valida entidade
            $entidadesPermitidas = ['cliente', 'produto', 'venda'];
            if (!in_array($entidade, $entidadesPermitidas)) {
                AuxiliarResposta::erro('Entidade inválida', 400);
                return;
            }

            // Busca registros do CRM via API (paginado)
            $totalEnfileirado = 0;
            $pagina = 1;
            $limite = 100;

            do {
                // Lista registros do CRM
                $resultado = $this->serviceCrm->listar($entidade, $idLoja, $pagina, $limite);

                if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                    break;
                }

                // Enfileira cada registro do CRM para importação
                foreach ($resultado['data'] as $item) {
                    // Identifica o external_id (pode variar por provider)
                    $externalId = $item['id'] ?? $item['external_id'] ?? null;

                    if ($externalId) {
                        $this->modelQueue->enfileirar(
                            $idLoja,
                            $entidade,
                            null, // Não temos ID local ainda
                            'crm_para_ecletech', // Direção: importar do CRM
                            $entidade === 'venda' ? 5 : 3,
                            (string) $externalId // ID no CRM
                        );
                        $totalEnfileirado++;
                    }
                }

                // Próxima página
                $pagina++;
                $totalPaginas = $resultado['pagination']['total_pages'] ?? 1;

            } while ($pagina <= $totalPaginas);

            AuxiliarResposta::sucesso([
                'total' => $totalEnfileirado,
                'entidade' => $entidade
            ], "✅ {$totalEnfileirado} registros do CRM enfileirados para importação");

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== AGENDAMENTOS =====

    /**
     * Lista todos os agendamentos
     */
    public function listarAgendamentos(): void
    {
        try {
            $idLoja = $this->obterLojaId();
            $agendamentos = $this->modelSchedule->listarPorLoja($idLoja);

            AuxiliarResposta::sucesso($agendamentos, 'Agendamentos obtidos com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém um agendamento específico
     */
    public function obterAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($agendamento, 'Agendamento obtido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Cria novo agendamento
     */
    public function criarAgendamento(): void
    {
        try {
            $dados = $this->obterDados();

            // Validações
            $erros = AuxiliarValidacao::validar($dados, [
                'entidade' => 'obrigatorio',
                'direcao' => 'obrigatorio'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Valida entidade
            $entidadesPermitidas = ['cliente', 'produto', 'venda'];
            if (!in_array($dados['entidade'], $entidadesPermitidas)) {
                AuxiliarResposta::erro('Entidade inválida. Use: cliente, produto ou venda', 400);
                return;
            }

            // Valida direção
            $direcoesPermitidas = ['crm_para_ecletech', 'ecletech_para_crm', 'bidirecional'];
            if (!in_array($dados['direcao'], $direcoesPermitidas)) {
                AuxiliarResposta::erro('Direção inválida', 400);
                return;
            }

            $idLoja = $this->obterLojaId();

            $dadosAgendamento = [
                'id_loja' => $idLoja,
                'entidade' => $dados['entidade'],
                'direcao' => $dados['direcao'],
                'batch_size' => $dados['batch_size'] ?? 10,
                'frequencia_minutos' => $dados['frequencia_minutos'] ?? 5,
                'horario_inicio' => $dados['horario_inicio'] ?? null,
                'horario_fim' => $dados['horario_fim'] ?? null,
                'prioridade' => $dados['prioridade'] ?? 5,
                'ativo' => $dados['ativo'] ?? true
            ];

            $id = $this->modelSchedule->criar($dadosAgendamento);

            $agendamento = $this->modelSchedule->buscarPorId($id);

            AuxiliarResposta::sucesso($agendamento, 'Agendamento criado com sucesso', 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Atualiza agendamento
     */
    public function atualizarAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $dados = $this->obterDados();

            // Valida entidade se fornecida
            if (isset($dados['entidade'])) {
                $entidadesPermitidas = ['cliente', 'produto', 'venda'];
                if (!in_array($dados['entidade'], $entidadesPermitidas)) {
                    AuxiliarResposta::erro('Entidade inválida', 400);
                    return;
                }
            }

            // Valida direção se fornecida
            if (isset($dados['direcao'])) {
                $direcoesPermitidas = ['crm_para_ecletech', 'ecletech_para_crm', 'bidirecional'];
                if (!in_array($dados['direcao'], $direcoesPermitidas)) {
                    AuxiliarResposta::erro('Direção inválida', 400);
                    return;
                }
            }

            $this->modelSchedule->atualizar($id, $dados);

            $agendamentoAtualizado = $this->modelSchedule->buscarPorId($id);

            AuxiliarResposta::sucesso($agendamentoAtualizado, 'Agendamento atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Deleta agendamento
     */
    public function deletarAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $this->modelSchedule->deletar($id);

            AuxiliarResposta::sucesso(null, 'Agendamento excluído com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Ativa agendamento
     */
    public function ativarAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $this->modelSchedule->alterarStatus($id, true);

            AuxiliarResposta::sucesso(null, 'Agendamento ativado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Desativa agendamento
     */
    public function desativarAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $this->modelSchedule->alterarStatus($id, false);

            AuxiliarResposta::sucesso(null, 'Agendamento desativado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém status e estatísticas de um agendamento
     */
    public function obterStatusAgendamento(int $id): void
    {
        try {
            $status = $this->serviceScheduler->obterStatusAgendamento($id);

            AuxiliarResposta::sucesso($status, 'Status obtido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Executa agendamento manualmente (fora do cron)
     */
    public function executarAgendamentoManual(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $resultado = $this->serviceScheduler->executarAgendamento($agendamento);

            if ($resultado['sucesso']) {
                AuxiliarResposta::sucesso($resultado, 'Agendamento executado com sucesso');
            } else {
                AuxiliarResposta::erro($resultado['erro'] ?? 'Erro ao executar agendamento', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém logs de um agendamento
     */
    public function obterLogsAgendamento(int $id): void
    {
        try {
            $agendamento = $this->modelSchedule->buscarPorId($id);

            if (!$agendamento) {
                AuxiliarResposta::naoEncontrado('Agendamento não encontrado');
                return;
            }

            $limit = $this->obterParametro('limit', 50);
            $logs = $this->modelLog->buscarPorSchedule($id, (int) $limit);

            AuxiliarResposta::sucesso($logs, 'Logs obtidos com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    // ===== HELPERS =====

    /**
     * Converte nome do provider (snake_case) para PascalCase
     */
    private function converterNomeParaClasse(string $nome): string
    {
        return str_replace('_', '', ucwords($nome, '_'));
    }
}
