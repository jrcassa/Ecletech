<?php

namespace App\Services\Whatsapp;

use App\Models\Whatsapp\ModelWhatsappEntidade;
use App\Models\Whatsapp\ModelWhatsappConfiguracao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarWhatsapp;

/**
 * Service para gerenciar entidades do WhatsApp
 */
class ServiceWhatsappEntidade
{
    private ModelWhatsappEntidade $model;
    private ModelWhatsappConfiguracao $config;
    private BancoDados $db;
    private array $cache = [];

    public function __construct()
    {
        $this->model = new ModelWhatsappEntidade();
        $this->config = new ModelWhatsappConfiguracao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Resolve destinatário (entidade ou número direto)
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
        $parsed = AuxiliarWhatsapp::parseEntidade($destinatario);
        if ($parsed !== null) {
            return $this->resolverPorEntidade($parsed['tipo'], $parsed['id']);
        }

        // Caso 3: Número direto (fallback)
        if ($this->config->obter('entidade_permitir_numero_direto', true)) {
            return [
                'numero' => AuxiliarWhatsapp::limparNumero($destinatario),
                'nome' => null,
                'email' => null,
                'tipo_entidade' => null,
                'entidade_id' => null
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
        if (!AuxiliarWhatsapp::tipoEntidadeValido($tipo)) {
            throw new \Exception("Tipo de entidade inválido: {$tipo}");
        }

        // Verifica cache
        $cacheKey = "{$tipo}:{$id}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Busca na tabela whatsapp_entidades
        $entidade = $this->model->buscarPorEntidade($tipo, $id);

        // Se não encontrou, tenta sincronizar
        if (!$entidade && $this->config->obter('entidade_auto_sync', true)) {
            $this->sincronizarEntidade($tipo, $id);
            $entidade = $this->model->buscarPorEntidade($tipo, $id);
        }

        if (!$entidade) {
            throw new \Exception("Entidade não encontrada: {$tipo} #{$id}");
        }

        // Verifica se está bloqueada
        if ($entidade['bloqueado']) {
            throw new \Exception("Entidade bloqueada: {$entidade['motivo_bloqueio']}");
        }

        // Verifica se número é válido
        if (!$entidade['whatsapp_valido']) {
            throw new \Exception("Número de WhatsApp inválido para esta entidade");
        }

        $resultado = [
            'numero' => $entidade['numero_whatsapp'],
            'nome' => $entidade['nome'],
            'email' => $entidade['email'],
            'tipo_entidade' => $tipo,
            'entidade_id' => $id
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
        $campoTelefone = $this->config->obter("entidade_{$tipo}_campo_telefone", 'celular');
        $campoEmail = $this->config->obter("entidade_{$tipo}_campo_email", 'email');

        if (!$tabela) {
            throw new \Exception("Tabela não configurada para tipo: {$tipo}");
        }

        // Busca na tabela de origem
        $registro = $this->db->buscarUm(
            "SELECT
                {$campoId} as id,
                {$campoNome} as nome,
                {$campoTelefone} as telefone,
                {$campoEmail} as email
            FROM {$tabela}
            WHERE {$campoId} = ?",
            [$id]
        );

        if (!$registro) {
            throw new \Exception("Registro não encontrado na tabela {$tabela} com ID {$id}");
        }

        // Valida se tem telefone
        if (empty($registro['telefone'])) {
            throw new \Exception("Entidade {$tipo} #{$id} não possui telefone cadastrado");
        }

        // Limpa e formata número
        $numeroLimpo = AuxiliarWhatsapp::limparNumero($registro['telefone']);
        $numeroFormatado = AuxiliarWhatsapp::formatarNumero($numeroLimpo);

        // Sincroniza
        $this->model->sincronizar([
            'tipo_entidade' => $tipo,
            'entidade_id' => $id,
            'numero_whatsapp' => $numeroLimpo,
            'numero_formatado' => $numeroFormatado,
            'nome' => $registro['nome'],
            'email' => $registro['email'] ?? null
        ]);

        return [
            'numero' => $numeroLimpo,
            'nome' => $registro['nome'],
            'tipo_entidade' => $tipo,
            'entidade_id' => $id
        ];
    }

    /**
     * Sincronização em lote
     */
    public function sincronizarLote(string $tipo, int $limit = 100, int $offset = 0): array
    {
        $tabela = $this->config->obter("entidade_{$tipo}_tabela");
        $campoId = $this->config->obter("entidade_{$tipo}_campo_id", 'id');
        $campoNome = $this->config->obter("entidade_{$tipo}_campo_nome", 'nome');
        $campoTelefone = $this->config->obter("entidade_{$tipo}_campo_telefone", 'celular');
        $campoEmail = $this->config->obter("entidade_{$tipo}_campo_email", 'email');

        if (!$tabela) {
            throw new \Exception("Tabela não configurada para tipo: {$tipo}");
        }

        $registros = $this->db->buscarTodos(
            "SELECT
                {$campoId} as id,
                {$campoNome} as nome,
                {$campoTelefone} as telefone,
                {$campoEmail} as email
            FROM {$tabela}
            WHERE {$campoTelefone} IS NOT NULL
            AND {$campoTelefone} != ''
            LIMIT ? OFFSET ?",
            [$limit, $offset]
        );

        $sincronizados = 0;
        $erros = 0;

        foreach ($registros as $registro) {
            try {
                $numeroLimpo = AuxiliarWhatsapp::limparNumero($registro['telefone']);
                $numeroFormatado = AuxiliarWhatsapp::formatarNumero($numeroLimpo);

                $this->model->sincronizar([
                    'tipo_entidade' => $tipo,
                    'entidade_id' => $registro['id'],
                    'numero_whatsapp' => $numeroLimpo,
                    'numero_formatado' => $numeroFormatado,
                    'nome' => $registro['nome'],
                    'email' => $registro['email'] ?? null
                ]);

                $sincronizados++;
            } catch (\Exception $e) {
                $erros++;
            }
        }

        return [
            'sincronizados' => $sincronizados,
            'erros' => $erros
        ];
    }

    /**
     * Registra envio bem-sucedido
     */
    public function registrarEnvio(?string $tipoEntidade, ?int $entidadeId): void
    {
        if ($tipoEntidade && $entidadeId) {
            $this->model->registrarEnvio($tipoEntidade, $entidadeId);
        }
    }
}
