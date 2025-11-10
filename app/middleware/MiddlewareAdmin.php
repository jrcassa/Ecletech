<?php

namespace App\Middleware;

use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para verificar permissões de administrador
 */
class MiddlewareAdmin
{
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            AuxiliarResposta::naoAutorizado('Autenticação necessária');
            return false;
        }

        // Verifica se o usuário tem permissões de admin
        if (!$this->verificarPermissaoAdmin($usuario['nivel_id'])) {
            AuxiliarResposta::proibido('Acesso negado. Permissões de administrador necessárias.');
            return false;
        }

        return true;
    }

    /**
     * Verifica se o nível tem permissão de administrador
     */
    private function verificarPermissaoAdmin(int $nivelId): bool
    {
        $nivel = $this->db->buscarUm(
            "SELECT * FROM administrador_niveis WHERE id = ? AND ativo = 1",
            [$nivelId]
        );

        if (!$nivel) {
            return false;
        }

        // Verifica se o nível é admin (código 'admin' ou 'superadmin')
        return in_array($nivel['codigo'], ['admin', 'superadmin']);
    }

    /**
     * Verifica permissão específica
     */
    public function verificarPermissao(string $permissao): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            return false;
        }

        // Busca as permissões do usuário através de suas roles
        $permissoes = $this->db->buscarTodos("
            SELECT p.codigo
            FROM administrador_permissions p
            INNER JOIN administrador_role_permissions rp ON rp.permission_id = p.id
            INNER JOIN administrador_roles r ON r.id = rp.role_id
            WHERE r.nivel_id = ? AND r.ativo = 1 AND p.ativo = 1
        ", [$usuario['nivel_id']]);

        $codigosPermissoes = array_column($permissoes, 'codigo');

        return in_array($permissao, $codigosPermissoes);
    }
}
