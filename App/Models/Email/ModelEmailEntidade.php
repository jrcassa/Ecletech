<?php

namespace App\Models\Email;

use App\Core\BancoDados;

/**
 * Model para gerenciar entidades de Email (cliente, colaborador, fornecedor, transportadora)
 * Padrão: Segue estrutura do ModelWhatsappEntidade
 */
class ModelEmailEntidade
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca entidade por tipo e ID
     */
    public function buscarPorEntidade(string $tipo, int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_entidades
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );
    }

    /**
     * Busca entidade por email
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_entidades WHERE email = ?",
            [$email]
        );
    }

    /**
     * Sincroniza (insere ou atualiza) entidade
     */
    public function sincronizar(array $dados): void
    {
        $existente = $this->buscarPorEntidade($dados['tipo_entidade'], $dados['entidade_id']);

        $dadosEntidade = [
            'tipo_entidade' => $dados['tipo_entidade'],
            'entidade_id' => $dados['entidade_id'],
            'email' => $dados['email'],
            'nome' => $dados['nome'] ?? null,
            'email_valido' => $dados['email_valido'] ?? true,
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        if ($existente) {
            // Atualiza
            $this->db->atualizar(
                'email_entidades',
                $dadosEntidade,
                'tipo_entidade = ? AND entidade_id = ?',
                [$dados['tipo_entidade'], $dados['entidade_id']]
            );
        } else {
            // Insere
            $dadosEntidade['criado_em'] = date('Y-m-d H:i:s');
            $this->db->inserir('email_entidades', $dadosEntidade);
        }
    }

    /**
     * Marca entidade como bloqueada
     */
    public function bloquear(string $tipo, int $id, string $motivo): void
    {
        $this->db->atualizar(
            'email_entidades',
            [
                'bloqueado' => true,
                'motivo_bloqueio' => $motivo,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'tipo_entidade = ? AND entidade_id = ?',
            [$tipo, $id]
        );
    }

    /**
     * Desbloqueia entidade
     */
    public function desbloquear(string $tipo, int $id): void
    {
        $this->db->atualizar(
            'email_entidades',
            [
                'bloqueado' => false,
                'motivo_bloqueio' => null,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'tipo_entidade = ? AND entidade_id = ?',
            [$tipo, $id]
        );
    }

    /**
     * Registra envio para entidade
     */
    public function registrarEnvio(string $tipo, int $id): void
    {
        $this->db->executar(
            "UPDATE email_entidades
             SET total_envios = total_envios + 1,
                 ultimo_envio = NOW(),
                 atualizado_em = NOW()
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );
    }

    /**
     * Registra abertura de email
     */
    public function registrarAbertura(string $tipo, int $id): void
    {
        $this->db->executar(
            "UPDATE email_entidades
             SET total_abertos = total_abertos + 1,
                 ultimo_aberto = NOW(),
                 atualizado_em = NOW()
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );
    }

    /**
     * Registra clique em email
     */
    public function registrarClique(string $tipo, int $id): void
    {
        $this->db->executar(
            "UPDATE email_entidades
             SET total_clicados = total_clicados + 1,
                 atualizado_em = NOW()
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );
    }

    /**
     * Incrementa contador de bounces
     */
    public function incrementarBounce(string $tipo, int $id): void
    {
        $this->db->executar(
            "UPDATE email_entidades
             SET bounce_count = bounce_count + 1,
                 atualizado_em = NOW()
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );

        // Se atingiu 3 bounces, bloqueia automaticamente
        $entidade = $this->buscarPorEntidade($tipo, $id);
        if ($entidade && $entidade['bounce_count'] >= 3) {
            $this->bloquear($tipo, $id, 'Bloqueado automaticamente após 3 bounces');
        }
    }

    /**
     * Marca email como inválido
     */
    public function marcarInvalido(string $tipo, int $id): void
    {
        $this->db->atualizar(
            'email_entidades',
            [
                'email_valido' => false,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'tipo_entidade = ? AND entidade_id = ?',
            [$tipo, $id]
        );
    }

    /**
     * Marca email como verificado
     */
    public function marcarVerificado(string $tipo, int $id, bool $verificado = true): void
    {
        $this->db->atualizar(
            'email_entidades',
            [
                'verificado' => $verificado,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'tipo_entidade = ? AND entidade_id = ?',
            [$tipo, $id]
        );
    }

    /**
     * Lista todas as entidades de um tipo
     */
    public function listarPorTipo(string $tipo, int $limit = 100, int $offset = 0): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_entidades
             WHERE tipo_entidade = ?
             ORDER BY nome ASC
             LIMIT ? OFFSET ?",
            [$tipo, $limit, $offset]
        );
    }

    /**
     * Conta entidades por tipo
     */
    public function contarPorTipo(string $tipo): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM email_entidades WHERE tipo_entidade = ?",
            [$tipo]
        );
        return $resultado['total'] ?? 0;
    }

    /**
     * Lista entidades bloqueadas
     */
    public function listarBloqueadas(int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_entidades
             WHERE bloqueado = true
             ORDER BY atualizado_em DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Lista entidades com bounces altos
     */
    public function listarComBouncesAltos(int $minBounces = 2, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_entidades
             WHERE bounce_count >= ?
             ORDER BY bounce_count DESC
             LIMIT ?",
            [$minBounces, $limit]
        );
    }
}
