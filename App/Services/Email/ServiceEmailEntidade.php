<?php

namespace App\Services\Email;

use App\Models\Email\ModelEmailEntidade;
use App\Models\Email\ModelEmailConfiguracao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarEmail;

/**
 * Service para gerenciar entidades do Email
 * Padrão: Segue estrutura do ServiceWhatsappEntidade
 */
class ServiceEmailEntidade
{
    private ModelEmailEntidade $model;
    private ModelEmailConfiguracao $config;
    private BancoDados $db;
    private array $cache = [];

    public function __construct()
    {
        $this->model = new ModelEmailEntidade();
        $this->config = new ModelEmailConfiguracao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Resolve destinatário (entidade ou email direto)
     */
    public function resolverDestinatario(string|array $destinatario): array
    {
        // Caso 1: Array estruturado
        if (is_array($destinatario)) {
            if (isset($destinatario['tipo']) && isset($destinatario['id'])) {
                return $this->resolverPorEntidade($destinatario['tipo'], $destinatario['id']);
            }
            throw new \Exception('Array de destinatário deve conter "tipo" e "id"');
        }

        // Caso 2: String no formato "tipo:id"
        $parsed = AuxiliarEmail::parseEntidade($destinatario);
        if ($parsed !== null) {
            return $this->resolverPorEntidade($parsed['tipo'], $parsed['id']);
        }

        // Caso 3: Email direto (fallback)
        if (AuxiliarEmail::validarEmail($destinatario)) {
            return [
                'email' => $destinatario,
                'nome' => null,
                'tipo_entidade' => null,
                'entidade_id' => null,
                'bloqueado' => false
            ];
        }

        throw new \Exception('Formato de destinatário inválido');
    }

    /**
     * Resolve por tipo e ID da entidade
     */
    private function resolverPorEntidade(string $tipo, int $id): array
    {
        // Valida tipo
        if (!AuxiliarEmail::tipoEntidadeValido($tipo)) {
            throw new \Exception("Tipo de entidade inválido: {$tipo}");
        }

        // Verifica cache
        $cacheKey = "{$tipo}:{$id}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Busca na tabela email_entidades
        $entidade = $this->model->buscarPorEntidade($tipo, $id);

        // Se não encontrou, tenta sincronizar
        if (!$entidade) {
            $this->sincronizarEntidade($tipo, $id);
            $entidade = $this->model->buscarPorEntidade($tipo, $id);
        }

        if (!$entidade) {
            throw new \Exception("Entidade não encontrada: {$tipo} #{$id}");
        }

        // Monta resultado
        $resultado = [
            'email' => $entidade['email'],
            'nome' => $entidade['nome'],
            'tipo_entidade' => $tipo,
            'entidade_id' => $id,
            'bloqueado' => (bool)$entidade['bloqueado'],
            'motivo_bloqueio' => $entidade['motivo_bloqueio'],
            'email_valido' => (bool)$entidade['email_valido']
        ];

        // Armazena em cache
        $this->cache[$cacheKey] = $resultado;

        return $resultado;
    }

    /**
     * Sincroniza entidade da tabela de origem
     */
    public function sincronizarEntidade(string $tipo, int $id): array
    {
        // Busca configurações da entidade
        $tabela = $this->config->obter("entidade_{$tipo}_tabela");
        $campoId = $this->config->obter("entidade_{$tipo}_campo_id", 'id');
        $campoNome = $this->config->obter("entidade_{$tipo}_campo_nome", 'nome');
        $campoEmail = $this->config->obter("entidade_{$tipo}_campo_email", 'email');

        if (empty($tabela)) {
            throw new \Exception("Tabela não configurada para entidade: {$tipo}");
        }

        // Busca dados da origem
        $sql = "SELECT
                    {$campoId} as id,
                    {$campoNome} as nome,
                    {$campoEmail} as email
                FROM {$tabela}
                WHERE {$campoId} = ?
                LIMIT 1";

        $origem = $this->db->buscarUm($sql, [$id]);

        if (!$origem) {
            throw new \Exception("Registro não encontrado na tabela {$tabela}: ID {$id}");
        }

        // Valida e limpa email
        $email = trim($origem['email']);

        if (empty($email)) {
            throw new \Exception("Email vazio para entidade {$tipo} #{$id}");
        }

        $emailValido = AuxiliarEmail::validarEmail($email);

        // Sincroniza na tabela email_entidades
        $this->model->sincronizar([
            'tipo_entidade' => $tipo,
            'entidade_id' => $id,
            'email' => $email,
            'nome' => $origem['nome'] ?? null,
            'email_valido' => $emailValido
        ]);

        return [
            'sucesso' => true,
            'tipo' => $tipo,
            'id' => $id,
            'email' => $email,
            'nome' => $origem['nome'] ?? null,
            'email_valido' => $emailValido
        ];
    }

    /**
     * Sincroniza entidades em lote
     */
    public function sincronizarLote(string $tipo, int $limit = 100): array
    {
        $tabela = $this->config->obter("entidade_{$tipo}_tabela");
        $campoId = $this->config->obter("entidade_{$tipo}_campo_id", 'id');
        $campoNome = $this->config->obter("entidade_{$tipo}_campo_nome", 'nome');
        $campoEmail = $this->config->obter("entidade_{$tipo}_campo_email", 'email');

        if (empty($tabela)) {
            throw new \Exception("Tabela não configurada para entidade: {$tipo}");
        }

        // Busca registros da origem
        $sql = "SELECT
                    {$campoId} as id,
                    {$campoNome} as nome,
                    {$campoEmail} as email
                FROM {$tabela}
                WHERE {$campoEmail} IS NOT NULL
                AND {$campoEmail} != ''
                LIMIT ?";

        $registros = $this->db->buscarTodos($sql, [$limit]);

        $sincronizados = 0;
        $erros = 0;

        foreach ($registros as $registro) {
            try {
                $email = trim($registro['email']);
                $emailValido = AuxiliarEmail::validarEmail($email);

                $this->model->sincronizar([
                    'tipo_entidade' => $tipo,
                    'entidade_id' => $registro['id'],
                    'email' => $email,
                    'nome' => $registro['nome'] ?? null,
                    'email_valido' => $emailValido
                ]);

                $sincronizados++;
            } catch (\Exception $e) {
                $erros++;
            }
        }

        return [
            'total' => count($registros),
            'sincronizados' => $sincronizados,
            'erros' => $erros
        ];
    }

    /**
     * Registra envio para entidade
     */
    public function registrarEnvio(string $tipo, int $id): void
    {
        $this->model->registrarEnvio($tipo, $id);

        // Limpa cache
        unset($this->cache["{$tipo}:{$id}"]);
    }

    /**
     * Registra abertura
     */
    public function registrarAbertura(string $tipo, int $id): void
    {
        $this->model->registrarAbertura($tipo, $id);
    }

    /**
     * Registra clique
     */
    public function registrarClique(string $tipo, int $id): void
    {
        $this->model->registrarClique($tipo, $id);
    }

    /**
     * Bloqueia entidade
     */
    public function bloquear(string $tipo, int $id, string $motivo): void
    {
        $this->model->bloquear($tipo, $id, $motivo);

        // Limpa cache
        unset($this->cache["{$tipo}:{$id}"]);
    }

    /**
     * Desbloqueia entidade
     */
    public function desbloquear(string $tipo, int $id): void
    {
        $this->model->desbloquear($tipo, $id);

        // Limpa cache
        unset($this->cache["{$tipo}:{$id}"]);
    }
}
