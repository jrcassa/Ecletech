<?php

namespace App\Controllers\Auditoria;

use App\Core\Autenticacao;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarResposta;
use App\Middleware\MiddlewareAcl;

/**
 * Controlador para gerenciar auditoria
 */
class ControllerAuditoria
{
    private RegistroAuditoria $auditoria;
    private Autenticacao $auth;
    private MiddlewareAcl $acl;

    public function __construct()
    {
        $this->auditoria = new RegistroAuditoria();
        $this->auth = new Autenticacao();
        $this->acl = new MiddlewareAcl();
    }

    /**
     * Lista todos os registros de auditoria
     */
    public function listar(): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            $filtros = [];

            // Obtém parâmetros de query
            if (isset($_GET['colaborador_id'])) {
                $filtros['colaborador_id'] = (int) $_GET['colaborador_id'];
            }

            if (isset($_GET['acao'])) {
                $filtros['acao'] = $_GET['acao'];
            }

            if (isset($_GET['tabela'])) {
                $filtros['tabela'] = $_GET['tabela'];
            }

            if (isset($_GET['registro_id'])) {
                $filtros['registro_id'] = (int) $_GET['registro_id'];
            }

            if (isset($_GET['data_inicio'])) {
                $filtros['data_inicio'] = $_GET['data_inicio'];
            }

            if (isset($_GET['data_fim'])) {
                $filtros['data_fim'] = $_GET['data_fim'];
            }

            // Paginação
            $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
            $porPagina = isset($_GET['por_pagina']) ? (int) $_GET['por_pagina'] : 20;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = ($pagina - 1) * $porPagina;

            $registros = $this->auditoria->buscarHistorico($filtros);

            // Conta total sem limite
            $filtrosSemLimite = $filtros;
            unset($filtrosSemLimite['limite'], $filtrosSemLimite['offset']);
            $total = count($this->auditoria->buscarHistorico($filtrosSemLimite));

            // Decodifica JSON
            foreach ($registros as &$registro) {
                if ($registro['dados_antigos']) {
                    $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
                }
                if ($registro['dados_novos']) {
                    $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
                }
            }

            AuxiliarResposta::paginado($registros, $total, $pagina, $porPagina);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca um registro de auditoria por ID
     */
    public function buscar(string $id): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            $registros = $this->auditoria->buscarHistorico(['registro_id' => (int) $id]);

            if (empty($registros)) {
                AuxiliarResposta::naoEncontrado('Registro de auditoria não encontrado');
                return;
            }

            $registro = $registros[0];

            // Decodifica JSON
            if ($registro['dados_antigos']) {
                $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
            }
            if ($registro['dados_novos']) {
                $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
            }

            AuxiliarResposta::sucesso($registro, 'Registro de auditoria encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca registros de auditoria por usuário
     */
    public function buscarPorUsuario(string $usuarioId): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            // Paginação
            $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
            $porPagina = isset($_GET['por_pagina']) ? (int) $_GET['por_pagina'] : 20;

            $filtros = [
                'colaborador_id' => (int) $usuarioId,
                'limite' => $porPagina,
                'offset' => ($pagina - 1) * $porPagina
            ];

            $registros = $this->auditoria->buscarHistorico($filtros);

            // Conta total
            $total = count($this->auditoria->buscarHistorico(['colaborador_id' => (int) $usuarioId]));

            // Decodifica JSON
            foreach ($registros as &$registro) {
                if ($registro['dados_antigos']) {
                    $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
                }
                if ($registro['dados_novos']) {
                    $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
                }
            }

            AuxiliarResposta::paginado($registros, $total, $pagina, $porPagina);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca registros de auditoria por tabela
     */
    public function buscarPorTabela(string $tabela): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            // Paginação
            $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
            $porPagina = isset($_GET['por_pagina']) ? (int) $_GET['por_pagina'] : 20;

            $filtros = [
                'tabela' => $tabela,
                'limite' => $porPagina,
                'offset' => ($pagina - 1) * $porPagina
            ];

            $registros = $this->auditoria->buscarHistorico($filtros);

            // Conta total
            $total = count($this->auditoria->buscarHistorico(['tabela' => $tabela]));

            // Decodifica JSON
            foreach ($registros as &$registro) {
                if ($registro['dados_antigos']) {
                    $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
                }
                if ($registro['dados_novos']) {
                    $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
                }
            }

            AuxiliarResposta::paginado($registros, $total, $pagina, $porPagina);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Lista histórico de login
     */
    public function listarLogin(): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            $colaboradorId = isset($_GET['colaborador_id']) ? (int) $_GET['colaborador_id'] : null;
            $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 50;

            if ($colaboradorId) {
                $registros = $this->auditoria->buscarHistoricoLogin($colaboradorId, $limite);
            } else {
                // Se não especificar colaborador, busca todos (precisa adicionar método)
                $registros = [];
            }

            AuxiliarResposta::sucesso($registros, 'Histórico de login encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém estatísticas de auditoria
     */
    public function estatisticas(): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            // Busca estatísticas dos últimos 30 dias
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
            $todos = $this->auditoria->buscarHistorico(['data_inicio' => $dataInicio]);

            $estatisticas = [
                'total' => count($todos),
                'por_acao' => [],
                'por_tabela' => [],
                'por_usuario' => [],
                'ultimas_acoes' => array_slice($todos, 0, 10)
            ];

            // Agrupa por ação
            foreach ($todos as $registro) {
                $acao = $registro['acao'];
                if (!isset($estatisticas['por_acao'][$acao])) {
                    $estatisticas['por_acao'][$acao] = 0;
                }
                $estatisticas['por_acao'][$acao]++;

                // Agrupa por tabela
                $tabela = $registro['tabela'];
                if (!isset($estatisticas['por_tabela'][$tabela])) {
                    $estatisticas['por_tabela'][$tabela] = 0;
                }
                $estatisticas['por_tabela'][$tabela]++;

                // Agrupa por colaborador
                $colaboradorId = $registro['colaborador_id'] ?? 'sistema';
                if (!isset($estatisticas['por_colaborador'][$colaboradorId])) {
                    $estatisticas['por_colaborador'][$colaboradorId] = 0;
                }
                $estatisticas['por_colaborador'][$colaboradorId]++;
            }

            // Decodifica JSON das últimas ações
            foreach ($estatisticas['ultimas_acoes'] as &$registro) {
                if ($registro['dados_antigos']) {
                    $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
                }
                if ($registro['dados_novos']) {
                    $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
                }
            }

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas de auditoria');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Limpa registros antigos de auditoria
     */
    public function limpar(): void
    {
        try {
            // Verifica permissão (apenas admin)
            if (!$this->acl->verificarPermissao('auditoria.gerenciar')) {
                AuxiliarResposta::erro('Sem permissão para gerenciar auditoria', 403);
                return;
            }

            $dados = AuxiliarResposta::obterDados();
            $dias = isset($dados['dias']) ? (int) $dados['dias'] : 90;

            $registrosDeletados = $this->auditoria->limparAntigos($dias);

            AuxiliarResposta::sucesso([
                'registros_deletados' => $registrosDeletados
            ], "Registros de auditoria com mais de {$dias} dias foram removidos");
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca registros de auditoria de um registro específico
     */
    public function buscarPorRegistro(string $tabela, string $registroId): void
    {
        try {
            // Verifica permissão
            if (!$this->acl->verificarPermissao('auditoria.visualizar')) {
                AuxiliarResposta::erro('Sem permissão para visualizar auditoria', 403);
                return;
            }

            $filtros = [
                'tabela' => $tabela,
                'registro_id' => (int) $registroId
            ];

            $registros = $this->auditoria->buscarHistorico($filtros);

            // Decodifica JSON
            foreach ($registros as &$registro) {
                if ($registro['dados_antigos']) {
                    $registro['dados_antigos'] = json_decode($registro['dados_antigos'], true);
                }
                if ($registro['dados_novos']) {
                    $registro['dados_novos'] = json_decode($registro['dados_novos'], true);
                }
            }

            AuxiliarResposta::sucesso($registros, 'Histórico do registro encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }
}
