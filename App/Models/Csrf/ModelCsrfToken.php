<?php

namespace App\Models\Csrf;

use App\Core\BancoDados;

/**
 * Model para gerenciar tokens CSRF no banco de dados
 */
class ModelCsrfToken
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria um novo token CSRF no banco de dados
     */
    public function criar(array $dados): int
    {
        return $this->db->inserir('csrf_tokens', [
            'token' => $dados['token'],
            'session_id' => $dados['session_id'] ?? null,
            'usuario_id' => $dados['usuario_id'] ?? null,
            'ip_address' => $dados['ip_address'] ?? null,
            'user_agent' => $dados['user_agent'] ?? null,
            'usado' => 0,
            'criado_em' => date('Y-m-d H:i:s'),
            'expira_em' => $dados['expira_em']
        ]);
    }

    /**
     * Busca um token CSRF específico
     */
    public function buscarPorToken(string $token): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM csrf_tokens WHERE token = ? AND usado = 0 AND expira_em > NOW()",
            [$token]
        );
    }

    /**
     * Marca um token como usado
     */
    public function marcarComoUsado(string $token): bool
    {
        return $this->db->atualizar(
            'csrf_tokens',
            [
                'usado' => 1,
                'usado_em' => date('Y-m-d H:i:s')
            ],
            'token = ?',
            [$token]
        ) > 0;
    }

    /**
     * Valida um token CSRF
     */
    public function validar(string $token, ?string $sessionId = null, ?int $usuarioId = null): bool
    {
        $sql = "SELECT * FROM csrf_tokens WHERE token = ? AND usado = 0 AND expira_em > NOW()";
        $parametros = [$token];

        // Valida também o session_id se fornecido
        if ($sessionId !== null) {
            $sql .= " AND session_id = ?";
            $parametros[] = $sessionId;
        }

        // Valida também o usuario_id se fornecido
        if ($usuarioId !== null) {
            $sql .= " AND usuario_id = ?";
            $parametros[] = $usuarioId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado !== null;
    }

    /**
     * Remove tokens expirados
     */
    public function limparExpirados(): int
    {
        return $this->db->executar(
            "DELETE FROM csrf_tokens WHERE expira_em < NOW()"
        )->rowCount();
    }

    /**
     * Remove tokens usados antigos
     */
    public function limparUsados(int $diasAtras = 1): int
    {
        return $this->db->executar(
            "DELETE FROM csrf_tokens WHERE usado = 1 AND usado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$diasAtras]
        )->rowCount();
    }

    /**
     * Busca todos os tokens de uma sessão
     */
    public function buscarPorSessao(string $sessionId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM csrf_tokens WHERE session_id = ? ORDER BY criado_em DESC",
            [$sessionId]
        );
    }

    /**
     * Busca todos os tokens de um usuário
     */
    public function buscarPorUsuario(int $usuarioId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM csrf_tokens WHERE usuario_id = ? ORDER BY criado_em DESC",
            [$usuarioId]
        );
    }

    /**
     * Remove todos os tokens de uma sessão
     */
    public function deletarPorSessao(string $sessionId): int
    {
        return $this->db->deletar(
            'csrf_tokens',
            'session_id = ?',
            [$sessionId]
        );
    }

    /**
     * Remove todos os tokens de um usuário
     */
    public function deletarPorUsuario(int $usuarioId): int
    {
        return $this->db->deletar(
            'csrf_tokens',
            'usuario_id = ?',
            [$usuarioId]
        );
    }

    /**
     * Conta tokens ativos (não expirados e não usados)
     */
    public function contarAtivos(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM csrf_tokens WHERE usado = 0 AND expira_em > NOW()"
        );
        return (int) $resultado['total'];
    }

    /**
     * Conta tokens por sessão
     */
    public function contarPorSessao(string $sessionId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM csrf_tokens WHERE session_id = ? AND usado = 0 AND expira_em > NOW()",
            [$sessionId]
        );
        return (int) $resultado['total'];
    }

    /**
     * Verifica se um token existe e está válido
     */
    public function existe(string $token): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM csrf_tokens WHERE token = ? AND usado = 0 AND expira_em > NOW()",
            [$token]
        );
        return $resultado !== null;
    }
}
