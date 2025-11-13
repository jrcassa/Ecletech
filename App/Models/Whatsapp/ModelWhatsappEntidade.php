<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para gerenciar entidades do WhatsApp (cliente, colaborador, fornecedor, etc)
 */
class ModelWhatsappEntidade
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
            "SELECT * FROM whatsapp_entidades
             WHERE tipo_entidade = ? AND entidade_id = ?",
            [$tipo, $id]
        );
    }

    /**
     * Busca entidade por número
     */
    public function buscarPorNumero(string $numero): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM whatsapp_entidades WHERE numero_whatsapp = ?",
            [$numero]
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
            'numero_whatsapp' => $dados['numero_whatsapp'],
            'numero_formatado' => $dados['numero_formatado'] ?? null,
            'nome' => $dados['nome'] ?? null,
            'email' => $dados['email'] ?? null,
            'whatsapp_valido' => true
        ];

        if ($existente) {
            // Atualiza
            $this->db->atualizar(
                'whatsapp_entidades',
                $dadosEntidade,
                'tipo_entidade = ? AND entidade_id = ?',
                [$dados['tipo_entidade'], $dados['entidade_id']]
            );
        } else {
            // Insere
            $dadosEntidade['criado_em'] = date('Y-m-d H:i:s');
            $this->db->inserir('whatsapp_entidades', $dadosEntidade);
        }
    }

    /**
     * Marca entidade como bloqueada
     */
    public function bloquear(string $tipo, int $id, string $motivo): void
    {
        $this->db->atualizar(
            'whatsapp_entidades',
            [
                'bloqueado' => true,
                'motivo_bloqueio' => $motivo
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
            'whatsapp_entidades',
            [
                'bloqueado' => false,
                'motivo_bloqueio' => null
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
        $sql = "UPDATE whatsapp_entidades
                SET total_envios = total_envios + 1,
                    ultimo_envio = ?
                WHERE tipo_entidade = ? AND entidade_id = ?";

        $this->db->executar($sql, [date('Y-m-d H:i:s'), $tipo, $id]);
    }

    /**
     * Marca número como inválido
     */
    public function marcarInvalido(string $tipo, int $id): void
    {
        $this->db->atualizar(
            'whatsapp_entidades',
            ['whatsapp_valido' => false],
            'tipo_entidade = ? AND entidade_id = ?',
            [$tipo, $id]
        );
    }

    /**
     * Busca entidades não sincronizadas recentemente
     */
    public function buscarDesatualizadas(string $tipo, int $dias = 30, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_entidades
             WHERE tipo_entidade = ?
             AND atualizado_em < DATE_SUB(NOW(), INTERVAL ? DAY)
             LIMIT ?",
            [$tipo, $dias, $limit]
        );
    }
}
