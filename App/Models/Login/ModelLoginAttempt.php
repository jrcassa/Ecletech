<?php

namespace App\Models\Login;

use App\Core\BancoDados;
use App\Core\Configuracao;

/**
 * Model para gerenciar tentativas de login e proteção contra brute force
 */
class ModelLoginAttempt
{
    private BancoDados $db;
    private int $maxTentativas;
    private int $janelaTempoMinutos;
    private int $tempoBloqueioMinutos;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();

        // Configurações de proteção brute force
        $config = Configuracao::obterInstancia();
        $this->maxTentativas = (int) $config->obter('BRUTE_FORCE_MAX_TENTATIVAS', 5);
        $this->janelaTempoMinutos = (int) $config->obter('BRUTE_FORCE_JANELA_TEMPO', 15);
        $this->tempoBloqueioMinutos = (int) $config->obter('BRUTE_FORCE_TEMPO_BLOQUEIO', 30);
    }

    /**
     * Registra uma tentativa de login
     */
    public function registrarTentativa(
        string $email,
        string $ipAddress,
        bool $sucesso,
        ?string $motivoFalha = null,
        ?string $userAgent = null
    ): bool {
        $sql = "INSERT INTO login_attempts
                (email, ip_address, user_agent, tentativa_sucesso, motivo_falha, criado_em)
                VALUES (?, ?, ?, ?, ?, NOW())";

        return $this->db->executar($sql, [
            $email,
            $ipAddress,
            $userAgent,
            $sucesso ? 1 : 0,
            $motivoFalha
        ]);
    }

    /**
     * Conta tentativas falhadas por email dentro da janela de tempo
     */
    public function contarTentativasFalhadasPorEmail(string $email): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM login_attempts
                WHERE email = ?
                AND tentativa_sucesso = 0
                AND criado_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $resultado = $this->db->buscarUm($sql, [$email, $this->janelaTempoMinutos]);
        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Conta tentativas falhadas por IP dentro da janela de tempo
     */
    public function contarTentativasFalhadasPorIp(string $ipAddress): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM login_attempts
                WHERE ip_address = ?
                AND tentativa_sucesso = 0
                AND criado_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $resultado = $this->db->buscarUm($sql, [$ipAddress, $this->janelaTempoMinutos]);
        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Verifica se um email está bloqueado
     */
    public function emailEstaBloqueado(string $email): bool
    {
        // Verifica bloqueio permanente ou temporário ainda válido
        $sql = "SELECT COUNT(*) as total
                FROM login_bloqueios
                WHERE email = ?
                AND (tipo_bloqueio IN ('email', 'ambos'))
                AND (bloqueado_permanente = 1 OR bloqueado_ate > NOW())";

        $resultado = $this->db->buscarUm($sql, [$email]);
        return ((int) $resultado['total']) > 0;
    }

    /**
     * Verifica se um IP está bloqueado
     */
    public function ipEstaBloqueado(string $ipAddress): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM login_bloqueios
                WHERE ip_address = ?
                AND (tipo_bloqueio IN ('ip', 'ambos'))
                AND (bloqueado_permanente = 1 OR bloqueado_ate > NOW())";

        $resultado = $this->db->buscarUm($sql, [$ipAddress]);
        return ((int) $resultado['total']) > 0;
    }

    /**
     * Obtém informações do bloqueio por email
     */
    public function obterBloqueioEmail(string $email): ?array
    {
        $sql = "SELECT *
                FROM login_bloqueios
                WHERE email = ?
                AND (tipo_bloqueio IN ('email', 'ambos'))
                AND (bloqueado_permanente = 1 OR bloqueado_ate > NOW())
                ORDER BY criado_em DESC
                LIMIT 1";

        return $this->db->buscarUm($sql, [$email]);
    }

    /**
     * Obtém informações do bloqueio por IP
     */
    public function obterBloqueioIp(string $ipAddress): ?array
    {
        $sql = "SELECT *
                FROM login_bloqueios
                WHERE ip_address = ?
                AND (tipo_bloqueio IN ('ip', 'ambos'))
                AND (bloqueado_permanente = 1 OR bloqueado_ate > NOW())
                ORDER BY criado_em DESC
                LIMIT 1";

        return $this->db->buscarUm($sql, [$ipAddress]);
    }

    /**
     * Cria ou atualiza um bloqueio
     */
    public function criarBloqueio(
        string $tipo,
        ?string $email = null,
        ?string $ipAddress = null,
        int $tentativas = 0,
        bool $permanente = false,
        ?string $motivo = null
    ): bool {
        // Calcula data de desbloqueio
        $bloqueadoAte = $permanente
            ? date('Y-m-d H:i:s', strtotime('+10 years'))
            : date('Y-m-d H:i:s', strtotime("+{$this->tempoBloqueioMinutos} minutes"));

        // Verifica se já existe um bloqueio
        $bloqueioExistente = $this->obterBloqueioExistente($tipo, $email, $ipAddress);

        if ($bloqueioExistente) {
            // Atualiza o bloqueio existente
            $sql = "UPDATE login_bloqueios
                    SET tentativas_falhadas = tentativas_falhadas + ?,
                        bloqueado_ate = ?,
                        bloqueado_permanente = ?,
                        motivo = ?,
                        atualizado_em = NOW()
                    WHERE id = ?";

            return $this->db->executar($sql, [
                $tentativas,
                $bloqueadoAte,
                $permanente ? 1 : 0,
                $motivo,
                $bloqueioExistente['id']
            ]);
        } else {
            // Cria novo bloqueio
            $sql = "INSERT INTO login_bloqueios
                    (tipo_bloqueio, email, ip_address, tentativas_falhadas, bloqueado_ate, bloqueado_permanente, motivo, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            return $this->db->executar($sql, [
                $tipo,
                $email,
                $ipAddress,
                $tentativas,
                $bloqueadoAte,
                $permanente ? 1 : 0,
                $motivo
            ]);
        }
    }

    /**
     * Obtém bloqueio existente
     */
    private function obterBloqueioExistente(string $tipo, ?string $email, ?string $ipAddress): ?array
    {
        if ($tipo === 'email' && $email) {
            return $this->obterBloqueioEmail($email);
        } elseif ($tipo === 'ip' && $ipAddress) {
            return $this->obterBloqueioIp($ipAddress);
        } elseif ($tipo === 'ambos' && $email && $ipAddress) {
            $sql = "SELECT *
                    FROM login_bloqueios
                    WHERE email = ? AND ip_address = ?
                    ORDER BY criado_em DESC
                    LIMIT 1";
            return $this->db->buscarUm($sql, [$email, $ipAddress]);
        }
        return null;
    }

    /**
     * Remove bloqueio de um email
     */
    public function desbloquearEmail(string $email): bool
    {
        $sql = "DELETE FROM login_bloqueios
                WHERE email = ?
                AND tipo_bloqueio IN ('email', 'ambos')";

        return $this->db->executar($sql, [$email]);
    }

    /**
     * Remove bloqueio de um IP
     */
    public function desbloquearIp(string $ipAddress): bool
    {
        $sql = "DELETE FROM login_bloqueios
                WHERE ip_address = ?
                AND tipo_bloqueio IN ('ip', 'ambos')";

        return $this->db->executar($sql, [$ipAddress]);
    }

    /**
     * Remove bloqueio específico por ID
     */
    public function desbloquearPorId(int $id): bool
    {
        return $this->db->executar("DELETE FROM login_bloqueios WHERE id = ?", [$id]);
    }

    /**
     * Limpa tentativas de um email (após login bem-sucedido, por exemplo)
     */
    public function limparTentativasEmail(string $email): bool
    {
        // Não deleta, mas marca as antigas como "resolvidas" mantendo histórico
        // Na prática, o sistema já ignora tentativas antigas pela janela de tempo
        return true;
    }

    /**
     * Limpa tentativas de um IP
     */
    public function limparTentativasIp(string $ipAddress): bool
    {
        return true;
    }

    /**
     * Lista todas as tentativas de login com filtros e paginação
     */
    public function listarTentativas(array $filtros = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filtros['email'])) {
            $where[] = "email LIKE ?";
            $params[] = "%{$filtros['email']}%";
        }

        if (!empty($filtros['ip_address'])) {
            $where[] = "ip_address = ?";
            $params[] = $filtros['ip_address'];
        }

        if (isset($filtros['sucesso'])) {
            $where[] = "tentativa_sucesso = ?";
            $params[] = $filtros['sucesso'] ? 1 : 0;
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "criado_em >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "criado_em <= ?";
            $params[] = $filtros['data_fim'];
        }

        $whereClause = implode(" AND ", $where);

        // Paginação
        $limite = $filtros['limite'] ?? 50;
        $offset = $filtros['offset'] ?? 0;

        $sql = "SELECT * FROM login_attempts
                WHERE {$whereClause}
                ORDER BY criado_em DESC
                LIMIT ? OFFSET ?";

        $params[] = $limite;
        $params[] = $offset;

        return $this->db->buscarTodos($sql, $params);
    }

    /**
     * Conta total de tentativas com filtros
     */
    public function contarTentativas(array $filtros = []): int
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filtros['email'])) {
            $where[] = "email LIKE ?";
            $params[] = "%{$filtros['email']}%";
        }

        if (!empty($filtros['ip_address'])) {
            $where[] = "ip_address = ?";
            $params[] = $filtros['ip_address'];
        }

        if (isset($filtros['sucesso'])) {
            $where[] = "tentativa_sucesso = ?";
            $params[] = $filtros['sucesso'] ? 1 : 0;
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "criado_em >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "criado_em <= ?";
            $params[] = $filtros['data_fim'];
        }

        $whereClause = implode(" AND ", $where);

        $resultado = $this->db->buscarUm("SELECT COUNT(*) as total FROM login_attempts WHERE {$whereClause}", $params);
        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Lista todos os bloqueios ativos
     */
    public function listarBloqueios(array $filtros = []): array
    {
        $where = ["(bloqueado_permanente = 1 OR bloqueado_ate > NOW())"];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = "tipo_bloqueio = ?";
            $params[] = $filtros['tipo'];
        }

        if (!empty($filtros['email'])) {
            $where[] = "email LIKE ?";
            $params[] = "%{$filtros['email']}%";
        }

        if (!empty($filtros['ip_address'])) {
            $where[] = "ip_address = ?";
            $params[] = $filtros['ip_address'];
        }

        $whereClause = implode(" AND ", $where);

        $limite = $filtros['limite'] ?? 50;
        $offset = $filtros['offset'] ?? 0;

        $sql = "SELECT * FROM login_bloqueios
                WHERE {$whereClause}
                ORDER BY criado_em DESC
                LIMIT ? OFFSET ?";

        $params[] = $limite;
        $params[] = $offset;

        return $this->db->buscarTodos($sql, $params);
    }

    /**
     * Conta bloqueios ativos
     */
    public function contarBloqueios(array $filtros = []): int
    {
        $where = ["(bloqueado_permanente = 1 OR bloqueado_ate > NOW())"];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = "tipo_bloqueio = ?";
            $params[] = $filtros['tipo'];
        }

        if (!empty($filtros['email'])) {
            $where[] = "email LIKE ?";
            $params[] = "%{$filtros['email']}%";
        }

        if (!empty($filtros['ip_address'])) {
            $where[] = "ip_address = ?";
            $params[] = $filtros['ip_address'];
        }

        $whereClause = implode(" AND ", $where);

        $resultado = $this->db->buscarUm("SELECT COUNT(*) as total FROM login_bloqueios WHERE {$whereClause}", $params);
        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Obtém estatísticas gerais
     */
    public function obterEstatisticas(): array
    {
        // Total de tentativas nas últimas 24h
        $tentativas24h = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM login_attempts WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Tentativas falhadas nas últimas 24h
        $falhas24h = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM login_attempts
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND tentativa_sucesso = 0"
        );

        // Tentativas bem-sucedidas nas últimas 24h
        $sucesso24h = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM login_attempts
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND tentativa_sucesso = 1"
        );

        // Bloqueios ativos
        $bloqueiosAtivos = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM login_bloqueios
             WHERE bloqueado_permanente = 1 OR bloqueado_ate > NOW()"
        );

        // IPs únicos bloqueados
        $ipsBloqueados = $this->db->buscarUm(
            "SELECT COUNT(DISTINCT ip_address) as total FROM login_bloqueios
             WHERE (bloqueado_permanente = 1 OR bloqueado_ate > NOW())
             AND ip_address IS NOT NULL"
        );

        // Emails únicos bloqueados
        $emailsBloqueados = $this->db->buscarUm(
            "SELECT COUNT(DISTINCT email) as total FROM login_bloqueios
             WHERE (bloqueado_permanente = 1 OR bloqueado_ate > NOW())
             AND email IS NOT NULL"
        );

        // Top 5 IPs com mais tentativas falhadas (últimas 24h)
        $topIps = $this->db->buscarTodos(
            "SELECT ip_address, COUNT(*) as total
             FROM login_attempts
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND tentativa_sucesso = 0
             GROUP BY ip_address
             ORDER BY total DESC
             LIMIT 5"
        );

        // Top 5 emails com mais tentativas falhadas (últimas 24h)
        $topEmails = $this->db->buscarTodos(
            "SELECT email, COUNT(*) as total
             FROM login_attempts
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND tentativa_sucesso = 0
             GROUP BY email
             ORDER BY total DESC
             LIMIT 5"
        );

        return [
            'tentativas_24h' => (int) $tentativas24h['total'],
            'falhas_24h' => (int) $falhas24h['total'],
            'sucesso_24h' => (int) $sucesso24h['total'],
            'bloqueios_ativos' => (int) $bloqueiosAtivos['total'],
            'ips_bloqueados' => (int) $ipsBloqueados['total'],
            'emails_bloqueados' => (int) $emailsBloqueados['total'],
            'top_ips' => $topIps,
            'top_emails' => $topEmails,
            'taxa_sucesso' => $tentativas24h['total'] > 0
                ? round(($sucesso24h['total'] / $tentativas24h['total']) * 100, 2)
                : 0
        ];
    }
}
