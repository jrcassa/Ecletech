<?php

namespace App\Models\Dashboard;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar dashboards customizáveis
 */
class ModelDashboard
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Cria um novo dashboard
     */
    public function criar(int $colaboradorId, array $dados): int
    {
        $id = $this->db->inserir('dashboards', [
            'colaborador_id' => $colaboradorId,
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'icone' => $dados['icone'] ?? 'fa-chart-line',
            'cor' => $dados['cor'] ?? '#3498db',
            'is_padrao' => $dados['is_padrao'] ?? 0,
            'ordem' => $dados['ordem'] ?? 0
        ]);

        $this->auditoria->registrar(
            'dashboard',
            'criar',
            $id,
            null,
            $dados,
            $colaboradorId
        );

        return $id;
    }

    /**
     * Lista dashboards de um colaborador
     */
    public function listarPorColaborador(int $colaboradorId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM dashboards
             WHERE colaborador_id = ?
             ORDER BY is_padrao DESC, ordem ASC, nome ASC",
            [$colaboradorId]
        );
    }

    /**
     * Busca dashboard por ID
     */
    public function buscarPorId(int $id, int $colaboradorId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM dashboards WHERE id = ? AND colaborador_id = ?",
            [$id, $colaboradorId]
        );
    }

    /**
     * Busca dashboard padrão do colaborador
     */
    public function obterPadrao(int $colaboradorId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM dashboards
             WHERE colaborador_id = ? AND is_padrao = 1
             LIMIT 1",
            [$colaboradorId]
        );
    }

    /**
     * Atualiza um dashboard
     */
    public function atualizar(int $id, int $colaboradorId, array $dados): bool
    {
        $dadosAtuais = $this->buscarPorId($id, $colaboradorId);
        if (!$dadosAtuais) {
            return false;
        }

        $camposPermitidos = ['nome', 'descricao', 'icone', 'cor', 'ordem'];
        $dadosAtualizar = [];

        foreach ($camposPermitidos as $campo) {
            if (isset($dados[$campo])) {
                $dadosAtualizar[$campo] = $dados[$campo];
            }
        }

        if (empty($dadosAtualizar)) {
            return true;
        }

        $resultado = $this->db->atualizar(
            'dashboards',
            $dadosAtualizar,
            "id = ? AND colaborador_id = ?",
            [$id, $colaboradorId]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard',
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
     * Deleta um dashboard
     */
    public function deletar(int $id, int $colaboradorId): bool
    {
        $dashboard = $this->buscarPorId($id, $colaboradorId);
        if (!$dashboard) {
            return false;
        }

        $resultado = $this->db->deletar(
            'dashboards',
            "id = ? AND colaborador_id = ?",
            [$id, $colaboradorId]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard',
                'deletar',
                $id,
                $dashboard,
                null,
                $colaboradorId
            );
        }

        return $resultado;
    }

    /**
     * Define dashboard como padrão
     */
    public function definirComoPadrao(int $id, int $colaboradorId): bool
    {
        // Primeiro remove o padrão de todos os outros
        $this->db->atualizar(
            'dashboards',
            ['is_padrao' => 0],
            "colaborador_id = ?",
            [$colaboradorId]
        );

        // Define o novo padrão
        $resultado = $this->db->atualizar(
            'dashboards',
            ['is_padrao' => 1],
            "id = ? AND colaborador_id = ?",
            [$id, $colaboradorId]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'dashboard',
                'definir_padrao',
                $id,
                null,
                ['is_padrao' => 1],
                $colaboradorId
            );
        }

        return $resultado;
    }

    /**
     * Duplica um dashboard
     */
    public function duplicar(int $id, int $colaboradorId, string $novoNome): ?int
    {
        $dashboard = $this->buscarPorId($id, $colaboradorId);
        if (!$dashboard) {
            return null;
        }

        // Cria novo dashboard
        $novoId = $this->criar($colaboradorId, [
            'nome' => $novoNome,
            'descricao' => $dashboard['descricao'],
            'icone' => $dashboard['icone'],
            'cor' => $dashboard['cor'],
            'is_padrao' => 0,
            'ordem' => $dashboard['ordem']
        ]);

        // Copia widgets
        $widgets = $this->db->buscarTodos(
            "SELECT * FROM dashboard_widgets WHERE dashboard_id = ?",
            [$id]
        );

        foreach ($widgets as $widget) {
            $this->db->inserir('dashboard_widgets', [
                'dashboard_id' => $novoId,
                'widget_tipo_id' => $widget['widget_tipo_id'],
                'titulo' => $widget['titulo'],
                'config' => $widget['config'],
                'posicao_x' => $widget['posicao_x'],
                'posicao_y' => $widget['posicao_y'],
                'largura' => $widget['largura'],
                'altura' => $widget['altura'],
                'ordem' => $widget['ordem'],
                'ativo' => $widget['ativo']
            ]);
        }

        $this->auditoria->registrar(
            'dashboard',
            'duplicar',
            $novoId,
            null,
            ['dashboard_origem_id' => $id, 'nome' => $novoNome],
            $colaboradorId
        );

        return $novoId;
    }

    /**
     * Conta dashboards de um colaborador
     */
    public function contarPorColaborador(int $colaboradorId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM dashboards WHERE colaborador_id = ?",
            [$colaboradorId]
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Valida se dashboard pertence ao colaborador
     */
    public function validarPropriedade(int $dashboardId, int $colaboradorId): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM dashboards WHERE id = ? AND colaborador_id = ?",
            [$dashboardId, $colaboradorId]
        );

        return $resultado !== null;
    }
}
