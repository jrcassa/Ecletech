<?php

namespace App\Models\Dashboard;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar widgets dos dashboards
 */
class ModelDashboardWidget
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Adiciona widget ao dashboard
     */
    public function adicionar(int $dashboardId, array $dados, int $colaboradorId): int
    {
        $id = $this->db->inserir('dashboard_widgets', [
            'dashboard_id' => $dashboardId,
            'widget_tipo_id' => $dados['widget_tipo_id'],
            'titulo' => $dados['titulo'] ?? null,
            'config' => isset($dados['config']) ? json_encode($dados['config']) : null,
            'posicao_x' => $dados['posicao_x'] ?? 0,
            'posicao_y' => $dados['posicao_y'] ?? 0,
            'largura' => $dados['largura'] ?? 4,
            'altura' => $dados['altura'] ?? 3,
            'ordem' => $dados['ordem'] ?? 0,
            'ativo' => $dados['ativo'] ?? 1
        ]);

        $this->auditoria->registrar(
            'dashboard_widget',
            'adicionar',
            $id,
            null,
            $dados,
            $colaboradorId
        );

        return $id;
    }

    /**
     * Lista widgets de um dashboard
     */
    public function listarPorDashboard(int $dashboardId): array
    {
        $widgets = $this->db->buscarTodos(
            "SELECT
                dw.*,
                wt.codigo as widget_tipo_codigo,
                wt.nome as widget_tipo_nome,
                wt.tipo_visual,
                wt.categoria,
                wt.icone,
                wt.intervalo_atualizacao
             FROM dashboard_widgets dw
             INNER JOIN widget_tipos wt ON dw.widget_tipo_id = wt.id
             WHERE dw.dashboard_id = ? AND dw.ativo = 1
             ORDER BY dw.ordem ASC, dw.id ASC",
            [$dashboardId]
        );

        // Decodifica JSON da config
        foreach ($widgets as &$widget) {
            if ($widget['config']) {
                $widget['config'] = json_decode($widget['config'], true);
            } else {
                $widget['config'] = [];
            }
        }

        return $widgets;
    }

    /**
     * Busca widget por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $widget = $this->db->buscarUm(
            "SELECT
                dw.*,
                wt.codigo as widget_tipo_codigo,
                wt.nome as widget_tipo_nome,
                wt.tipo_visual,
                wt.categoria,
                wt.icone,
                wt.intervalo_atualizacao
             FROM dashboard_widgets dw
             INNER JOIN widget_tipos wt ON dw.widget_tipo_id = wt.id
             WHERE dw.id = ?",
            [$id]
        );

        if ($widget && $widget['config']) {
            $widget['config'] = json_decode($widget['config'], true);
        }

        return $widget;
    }

    /**
     * Atualiza widget
     */
    public function atualizar(int $id, array $dados, int $colaboradorId): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $camposPermitidos = ['titulo', 'config', 'posicao_x', 'posicao_y', 'largura', 'altura', 'ordem', 'ativo'];
        $dadosAtualizar = [];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                if ($campo === 'config' && is_array($dados[$campo])) {
                    $dadosAtualizar[$campo] = json_encode($dados[$campo]);
                } else {
                    $dadosAtualizar[$campo] = $dados[$campo];
                }
            }
        }

        if (empty($dadosAtualizar)) {
            return true;
        }

        $resultado = $this->db->atualizar(
            'dashboard_widgets',
            $dadosAtualizar,
            "id = ?",
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard_widget',
                'atualizar',
                $id,
                $dadosAtuais,
                $dadosAtualizar,
                $colaboradorId
            );
        }

        return $resultado;
    }

    /**
     * Remove widget
     */
    public function remover(int $id, int $colaboradorId): bool
    {
        $widget = $this->buscarPorId($id);
        if (!$widget) {
            return false;
        }

        $resultado = $this->db->deletar(
            'dashboard_widgets',
            "id = ?",
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard_widget',
                'remover',
                $id,
                $widget,
                null,
                $colaboradorId
            );
        }

        return $resultado;
    }

    /**
     * Atualiza posições de múltiplos widgets (bulk update)
     */
    public function atualizarPosicoes(array $widgets, int $colaboradorId): bool
    {
        try {
            $this->db->iniciarTransacao();

            foreach ($widgets as $widget) {
                if (!isset($widget['id'])) {
                    continue;
                }

                $dadosAtualizar = [];
                if (isset($widget['x'])) $dadosAtualizar['posicao_x'] = $widget['x'];
                if (isset($widget['y'])) $dadosAtualizar['posicao_y'] = $widget['y'];
                if (isset($widget['w'])) $dadosAtualizar['largura'] = $widget['w'];
                if (isset($widget['h'])) $dadosAtualizar['altura'] = $widget['h'];

                if (!empty($dadosAtualizar)) {
                    $this->db->atualizar(
                        'dashboard_widgets',
                        $dadosAtualizar,
                        "id = ?",
                        [$widget['id']]
                    );
                }
            }

            $this->db->commit();

            $this->auditoria->registrar(
                'dashboard_widget',
                'atualizar_posicoes',
                null,
                null,
                ['widgets' => $widgets],
                $colaboradorId
            );

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Ativa widget
     */
    public function ativar(int $id, int $colaboradorId): bool
    {
        $resultado = $this->db->atualizar(
            'dashboard_widgets',
            ['ativo' => 1],
            "id = ?",
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard_widget',
                'ativar',
                $id,
                ['ativo' => 0],
                ['ativo' => 1],
                $colaboradorId
            );
        }

        return $resultado;
    }

    /**
     * Desativa widget
     */
    public function desativar(int $id, int $colaboradorId): bool
    {
        $resultado = $this->db->atualizar(
            'dashboard_widgets',
            ['ativo' => 0],
            "id = ?",
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard_widget',
                'desativar',
                $id,
                ['ativo' => 1],
                ['ativo' => 0],
                $colaboradorId
            );
        }

        return $resultado;
    }
}
