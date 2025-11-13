<?php

namespace App\Middleware;

use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para verificar permissões específicas (ACL)
 *
 * Uso:
 * - Como middleware de rota com permissões específicas
 * - Permite verificar uma ou múltiplas permissões
 * - Suporta lógica AND (todas as permissões) ou OR (qualquer permissão)
 */
class MiddlewareAcl
{
    private Autenticacao $auth;
    private BancoDados $db;
    private array $permissoesRequeridas = [];
    private string $logica = 'AND'; // AND ou OR

    public function __construct(array $permissoes = [], string $logica = 'AND')
    {
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
        $this->permissoesRequeridas = $permissoes;
        $this->logica = strtoupper($logica);
    }

    /**
     * Processa a requisição verificando as permissões
     */
    public function handle(): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            AuxiliarResposta::naoAutorizado('Autenticação necessária');
            return false;
        }

        // Se não há permissões requeridas, permite o acesso
        if (empty($this->permissoesRequeridas)) {
            return true;
        }

        // Busca todas as permissões do usuário
        $permissoesUsuario = $this->obterPermissoesUsuario($usuario['nivel_id']);

        // Verifica as permissões de acordo com a lógica
        if ($this->logica === 'OR') {
            // Usuário precisa ter PELO MENOS UMA das permissões
            $temPermissao = $this->verificarPermissoesOr($permissoesUsuario);
        } else {
            // Usuário precisa ter TODAS as permissões
            $temPermissao = $this->verificarPermissoesAnd($permissoesUsuario);
        }

        if (!$temPermissao) {
            AuxiliarResposta::proibido(
                'Acesso negado. Você não tem as permissões necessárias para esta ação.'
            );
            return false;
        }

        return true;
    }

    /**
     * Verifica se o usuário tem permissão específica
     */
    public function verificarPermissao(string $permissao): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            return false;
        }

        $permissoesUsuario = $this->obterPermissoesUsuario($usuario['nivel_id']);
        return in_array($permissao, $permissoesUsuario);
    }

    /**
     * Verifica se o usuário tem TODAS as permissões (lógica AND)
     */
    public function verificarPermissoes(array $permissoes): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            return false;
        }

        $permissoesUsuario = $this->obterPermissoesUsuario($usuario['nivel_id']);

        foreach ($permissoes as $permissao) {
            if (!in_array($permissao, $permissoesUsuario)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se o usuário tem PELO MENOS UMA das permissões (lógica OR)
     */
    public function verificarAlgumaPermissao(array $permissoes): bool
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            return false;
        }

        $permissoesUsuario = $this->obterPermissoesUsuario($usuario['nivel_id']);

        foreach ($permissoes as $permissao) {
            if (in_array($permissao, $permissoesUsuario)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém todas as permissões de um usuário através de seu nível/role
     */
    private function obterPermissoesUsuario(int $nivelId): array
    {
        $permissoes = $this->db->buscarTodos("
            SELECT DISTINCT p.codigo
            FROM colaborador_permissions p
            INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
            INNER JOIN colaborador_roles r ON r.id = rp.role_id
            WHERE r.nivel_id = ? AND r.ativo = 1 AND p.ativo = 1
        ", [$nivelId]);

        return array_column($permissoes, 'codigo');
    }

    /**
     * Verifica permissões com lógica AND (todas as permissões)
     */
    private function verificarPermissoesAnd(array $permissoesUsuario): bool
    {
        foreach ($this->permissoesRequeridas as $permissao) {
            if (!in_array($permissao, $permissoesUsuario)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verifica permissões com lógica OR (qualquer permissão)
     */
    private function verificarPermissoesOr(array $permissoesUsuario): bool
    {
        foreach ($this->permissoesRequeridas as $permissao) {
            if (in_array($permissao, $permissoesUsuario)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtém todas as permissões do usuário autenticado
     */
    public function obterPermissoesUsuarioAtual(): array
    {
        $usuario = $this->auth->obterUsuarioAutenticado();

        if (!$usuario) {
            return [];
        }

        return $this->obterPermissoesUsuario($usuario['nivel_id']);
    }

    /**
     * Factory method para criar middleware com permissões específicas
     */
    public static function requer(string|array $permissoes, string $logica = 'AND'): self
    {
        $permissoes = is_array($permissoes) ? $permissoes : [$permissoes];
        return new self($permissoes, $logica);
    }

    /**
     * Factory method para criar middleware que requer pelo menos uma das permissões (lógica OR)
     */
    public static function requerUm(array $permissoes): self
    {
        return new self($permissoes, 'OR');
    }
}
