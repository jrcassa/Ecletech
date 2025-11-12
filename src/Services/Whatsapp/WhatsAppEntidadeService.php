<?php

namespace Services\Whatsapp;

use Models\Whatsapp\WhatsAppEntidade;
use Models\Whatsapp\WhatsAppConfiguracao;
use Helpers\Utils;

class WhatsAppEntidadeService
{
    private $conn;
    private $entidadeModel;
    private $config;
    private $cache = [];

    // Mapeamento de tipos válidos
    private $tiposValidos = ['cliente', 'colaborador', 'fornecedor', 'transportadora'];

    public function __construct($db)
    {
        $this->conn = $db;
        $this->entidadeModel = new WhatsAppEntidade($db);
        $this->config = new WhatsAppConfiguracao($db);
    }

    /**
     * Resolve entidade para número de WhatsApp
     *
     * @param string|array $destinatario - Pode ser "cliente:123", ["tipo" => "cliente", "id" => 123], ou número direto
     * @return array ['numero', 'nome', 'tipo_entidade', 'entidade_id']
     */
    public function resolverDestinatario($destinatario)
    {
        // Caso 1: Array estruturado
        if (is_array($destinatario)) {
            if (isset($destinatario['tipo']) && isset($destinatario['id'])) {
                return $this->resolverPorEntidade($destinatario['tipo'], $destinatario['id']);
            }
            throw new \Exception('Array de destinatário deve conter "tipo" e "id"');
        }

        // Caso 2: String no formato "tipo:id"
        if (is_string($destinatario) && strpos($destinatario, ':') !== false) {
            list($tipo, $id) = explode(':', $destinatario, 2);
            return $this->resolverPorEntidade($tipo, $id);
        }

        // Caso 3: Número direto (fallback)
        if ($this->config->obter('entidade_permitir_numero_direto', true)) {
            return [
                'numero' => $this->limparNumero($destinatario),
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
    private function resolverPorEntidade($tipo, $id)
    {
        // Valida tipo
        if (!in_array($tipo, $this->tiposValidos)) {
            throw new \Exception("Tipo de entidade inválido: {$tipo}");
        }

        // Verifica cache
        $cacheKey = "{$tipo}:{$id}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Busca na tabela whatsapp_entidades
        $entidade = $this->entidadeModel->buscarPorEntidade($tipo, $id);

        // Se não encontrou, tenta sincronizar
        if (!$entidade && $this->config->obter('entidade_auto_sync', true)) {
            $this->sincronizarEntidade($tipo, $id);
            $entidade = $this->entidadeModel->buscarPorEntidade($tipo, $id);
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
    public function sincronizarEntidade($tipo, $id)
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
        $query = "SELECT
            {$campoId} as id,
            {$campoNome} as nome,
            {$campoTelefone} as telefone,
            {$campoEmail} as email
            FROM {$tabela}
            WHERE {$campoId} = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new \Exception("Registro não encontrado na tabela {$tabela} com ID {$id}");
        }

        // Valida se tem telefone
        if (empty($registro['telefone'])) {
            throw new \Exception("Entidade {$tipo} #{$id} não possui telefone cadastrado");
        }

        // Limpa e formata número
        $numeroLimpo = $this->limparNumero($registro['telefone']);
        $numeroFormatado = $this->formatarNumero($numeroLimpo);

        // Sincroniza
        $this->entidadeModel->sincronizar([
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
    public function sincronizarLote($tipo, $limit = 100, $offset = 0)
    {
        $tabela = $this->config->obter("entidade_{$tipo}_tabela");
        $campoId = $this->config->obter("entidade_{$tipo}_campo_id", 'id');
        $campoNome = $this->config->obter("entidade_{$tipo}_campo_nome", 'nome');
        $campoTelefone = $this->config->obter("entidade_{$tipo}_campo_telefone", 'celular');
        $campoEmail = $this->config->obter("entidade_{$tipo}_campo_email", 'email');

        if (!$tabela) {
            throw new \Exception("Tabela não configurada para tipo: {$tipo}");
        }

        $query = "SELECT
            {$campoId} as id,
            {$campoNome} as nome,
            {$campoTelefone} as telefone,
            {$campoEmail} as email
            FROM {$tabela}
            WHERE {$campoTelefone} IS NOT NULL
            AND {$campoTelefone} != ''
            LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sincronizados = 0;
        $erros = 0;

        while ($registro = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $numeroLimpo = $this->limparNumero($registro['telefone']);
                $numeroFormatado = $this->formatarNumero($numeroLimpo);

                $this->entidadeModel->sincronizar([
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
     * Limpa número (remove caracteres especiais)
     */
    private function limparNumero($numero)
    {
        // Remove tudo exceto números
        $numero = preg_replace('/[^0-9]/', '', $numero);

        // Remove 0 do início (DDD)
        $numero = ltrim($numero, '0');

        // Adiciona código do país se não tiver (55 = Brasil)
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        return $numero;
    }

    /**
     * Formata número para exibição
     */
    private function formatarNumero($numero)
    {
        // Formato: +55 (15) 99999-9999
        if (strlen($numero) == 13) { // Com código país
            return '+' . substr($numero, 0, 2) . ' (' . substr($numero, 2, 2) . ') ' .
                   substr($numero, 4, 5) . '-' . substr($numero, 9);
        }

        return $numero;
    }

    /**
     * Registrar envio bem-sucedido
     */
    public function registrarEnvio($tipoEntidade, $entidadeId)
    {
        if ($tipoEntidade && $entidadeId) {
            $this->entidadeModel->registrarEnvio($tipoEntidade, $entidadeId);
        }
    }
}
