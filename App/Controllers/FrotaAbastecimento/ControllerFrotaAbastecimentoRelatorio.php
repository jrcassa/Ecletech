<?php

namespace App\Controllers\FrotaAbastecimento;

use App\Controllers\BaseController;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioLog;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioSnapshot;
use App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar relatórios automáticos de abastecimentos
 */
class ControllerFrotaAbastecimentoRelatorio extends BaseController
{
    private ModelFrotaAbastecimentoRelatorioConfiguracao $modelConfiguracao;
    private ModelFrotaAbastecimentoRelatorioLog $modelLog;
    private ModelFrotaAbastecimentoRelatorioSnapshot $modelSnapshot;
    private ServiceFrotaAbastecimentoRelatorio $service;

    public function __construct()
    {
        $this->modelConfiguracao = new ModelFrotaAbastecimentoRelatorioConfiguracao();
        $this->modelLog = new ModelFrotaAbastecimentoRelatorioLog();
        $this->modelSnapshot = new ModelFrotaAbastecimentoRelatorioSnapshot();
        $this->service = new ServiceFrotaAbastecimentoRelatorio();
    }

    // ========== CONFIGURAÇÕES ==========

    /**
     * Lista configurações de relatórios do usuário logado
     */
    public function minhasConfiguracoes(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();

            $configuracoes = $this->modelConfiguracao->buscarPorColaborador($usuarioLogado['id']);

            $this->sucesso($configuracoes, 'Configurações listadas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria/atualiza configuração de relatório
     */
    public function configurar(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_relatorio' => 'obrigatorio|em:semanal,mensal',
                'ativo' => 'opcional|booleano',
                'dia_envio_semanal' => 'opcional|em:segunda,terca,quarta,quinta,sexta,sabado,domingo',
                'dia_envio_mensal' => 'opcional|inteiro|min:1|max:28',
                'hora_envio' => 'opcional',
                'formato_relatorio' => 'opcional|em:resumido,detalhado,completo'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se já existe configuração
            $configExistente = $this->modelConfiguracao->buscarPorColaboradorETipo(
                $usuarioLogado['id'],
                $dados['tipo_relatorio']
            );

            $dados['colaborador_id'] = $usuarioLogado['id'];

            if ($configExistente) {
                // Atualiza
                $dados['atualizado_por'] = $usuarioLogado['id'];
                $this->modelConfiguracao->atualizar($configExistente['id'], $dados);
                $id = $configExistente['id'];
            } else {
                // Cria
                $dados['criado_por'] = $usuarioLogado['id'];
                $id = $this->modelConfiguracao->criar($dados);
            }

            $this->sucesso(['id' => $id], 'Configuração salva com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Desativa configuração de relatório
     */
    public function desativarConfiguracao(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();

            $config = $this->modelConfiguracao->buscarPorId((int) $id);
            if (!$config) {
                $this->naoEncontrado('Configuração não encontrada');
                return;
            }

            // Valida propriedade
            if ($config['colaborador_id'] != $usuarioLogado['id']) {
                $this->proibido('Esta configuração não pertence a você');
                return;
            }

            $this->modelConfiguracao->atualizar((int) $id, [
                'ativo' => false,
                'atualizado_por' => $usuarioLogado['id']
            ]);

            $this->sucesso(['id' => (int) $id], 'Configuração desativada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    // ========== HISTÓRICO DE ENVIOS ==========

    /**
     * Lista histórico de relatórios enviados
     */
    public function historico(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();

            $filtros = [
                'destinatario_id' => $usuarioLogado['id'],
                'tipo_relatorio' => $_GET['tipo_relatorio'] ?? null,
                'status_envio' => $_GET['status_envio'] ?? null
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 20);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            $logs = $this->modelLog->listar($filtros);
            $total = $this->modelLog->contar(array_diff_key($filtros, array_flip(['limite', 'offset'])));

            $this->paginado(
                $logs,
                $total,
                $paginaAtual,
                $porPagina,
                'Histórico listado com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca log de envio específico
     */
    public function buscarLog(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();

            $log = $this->modelLog->buscarPorId((int) $id);

            if (!$log) {
                $this->naoEncontrado('Log não encontrado');
                return;
            }

            // Valida propriedade
            if ($log['destinatario_id'] != $usuarioLogado['id']) {
                $this->proibido('Este log não pertence a você');
                return;
            }

            $this->sucesso($log, 'Log encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    // ========== GERAÇÃO MANUAL ==========

    /**
     * Gera relatório manualmente (sem enviar, apenas retorna)
     */
    public function gerarManual(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_relatorio' => 'obrigatorio|em:semanal,mensal',
                'periodo_inicio' => 'obrigatorio|data',
                'periodo_fim' => 'obrigatorio|data',
                'formato' => 'opcional|em:resumido,detalhado,completo'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $relatorio = $this->service->gerarRelatorioManual(
                $dados['tipo_relatorio'],
                $dados['periodo_inicio'],
                $dados['periodo_fim'],
                $dados['formato'] ?? 'detalhado'
            );

            $this->sucesso($relatorio, 'Relatório gerado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Envia relatório manual via WhatsApp
     */
    public function enviarManual(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_relatorio' => 'obrigatorio|em:semanal,mensal',
                'periodo_inicio' => 'obrigatorio|data',
                'periodo_fim' => 'obrigatorio|data',
                'formato' => 'opcional|em:resumido,detalhado,completo'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $logId = $this->service->enviarRelatorioManual(
                $usuarioLogado['id'],
                $dados['tipo_relatorio'],
                $dados['periodo_inicio'],
                $dados['periodo_fim'],
                $dados['formato'] ?? 'detalhado'
            );

            $this->sucesso(['log_id' => $logId], 'Relatório enviado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    // ========== SNAPSHOTS ==========

    /**
     * Lista snapshots disponíveis
     */
    public function listarSnapshots(): void
    {
        try {
            $tipo_periodo = $_GET['tipo_periodo'] ?? 'mensal';
            $limite = (int) ($_GET['limite'] ?? 12);

            if (!in_array($tipo_periodo, ['semanal', 'mensal'])) {
                $this->erro('Tipo de período inválido');
                return;
            }

            $snapshots = $this->modelSnapshot->buscarHistorico($tipo_periodo, $limite);

            $this->sucesso($snapshots, 'Snapshots listados com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca snapshot específico
     */
    public function buscarSnapshot(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $snapshot = $this->modelSnapshot->buscarPorId((int) $id);

            if (!$snapshot) {
                $this->naoEncontrado('Snapshot não encontrado');
                return;
            }

            // Decodifica JSONs
            $snapshot['dados_por_frota'] = json_decode($snapshot['dados_por_frota'], true);
            $snapshot['dados_por_motorista'] = json_decode($snapshot['dados_por_motorista'], true);
            $snapshot['dados_por_combustivel'] = json_decode($snapshot['dados_por_combustivel'], true);
            $snapshot['ranking_consumo'] = json_decode($snapshot['ranking_consumo'], true);
            $snapshot['ranking_economia'] = json_decode($snapshot['ranking_economia'], true);

            $this->sucesso($snapshot, 'Snapshot encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Força recálculo de snapshot (Admin)
     */
    public function recalcularSnapshot(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_periodo' => 'obrigatorio|em:semanal,mensal',
                'periodo_inicio' => 'obrigatorio|data',
                'periodo_fim' => 'obrigatorio|data'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $snapshotId = $this->service->recalcularSnapshot(
                $dados['tipo_periodo'],
                $dados['periodo_inicio'],
                $dados['periodo_fim']
            );

            $this->sucesso(['snapshot_id' => $snapshotId], 'Snapshot recalculado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
