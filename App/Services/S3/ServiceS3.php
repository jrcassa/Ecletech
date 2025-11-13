<?php

namespace App\Services\S3;

use App\Core\BancoDados;
use App\Models\S3\ModelS3Cliente;
use App\Models\S3\ModelS3Arquivo;
use App\Models\S3\ModelS3Historico;
use App\Models\S3\ModelS3Configuracao;
use Exception;

/**
 * Service principal para gerenciar operações S3
 * Orquestra Models e implementa lógica de negócio
 */
class ServiceS3
{
    private BancoDados $db;
    private ModelS3Cliente $cliente;
    private ModelS3Arquivo $modelArquivo;
    private ModelS3Historico $modelHistorico;
    private ModelS3Configuracao $modelConfig;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->modelArquivo = new ModelS3Arquivo();
        $this->modelHistorico = new ModelS3Historico();
        $this->modelConfig = new ModelS3Configuracao();

        // Cliente S3 será inicializado apenas quando necessário (lazy loading)
    }

    /**
     * Obtém cliente S3 (lazy loading)
     */
    private function obterCliente(): ModelS3Cliente
    {
        if (!isset($this->cliente)) {
            $this->cliente = new ModelS3Cliente();
        }

        return $this->cliente;
    }

    /**
     * Upload de arquivo (recurso ou string)
     */
    public function upload(array $params): array
    {
        $inicioTempo = microtime(true);

        try {
            // Valida parâmetros obrigatórios
            $this->validarParametrosUpload($params);

            // Extrai parâmetros
            $nomeOriginal = $params['nome_original'];
            $conteudo = $params['conteudo'];
            $bucket = $params['bucket'] ?? $this->obterCliente()->obterBucketPadrao();
            $caminhoS3 = $params['caminho_s3'] ?? $this->gerarCaminhoS3($nomeOriginal);
            $acl = $params['acl'] ?? $this->modelConfig->obter('aws_default_acl', 'private');
            $metadata = $params['metadata'] ?? [];

            // Informações do arquivo
            $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $tipoMime = $params['tipo_mime'] ?? $this->obterTipoMime($extensao);

            // Calcula hash e tamanho
            $hashMd5 = md5($conteudo);
            $tamanhoBytes = is_resource($conteudo) ? fstat($conteudo)['size'] : strlen($conteudo);

            // Verifica duplicata (opcional)
            if ($params['verificar_duplicata'] ?? false) {
                $duplicata = $this->modelArquivo->buscarPorHash($hashMd5);
                if ($duplicata) {
                    return [
                        'sucesso' => true,
                        'duplicata' => true,
                        'arquivo' => $duplicata
                    ];
                }
            }

            // Gera nome único para S3
            $nomeS3 = $this->gerarNomeUnico($nomeOriginal);

            // Upload para S3
            $resultadoS3 = $this->obterCliente()->putObject(
                $bucket,
                $caminhoS3 . '/' . $nomeS3,
                $conteudo,
                $tipoMime,
                $acl,
                $metadata
            );

            // URL pública se ACL for public-read
            $urlPublica = null;
            if ($acl === 'public-read') {
                $urlPublica = $resultadoS3['url'] ?? null;
            }

            // Registra no banco
            $arquivoId = $this->modelArquivo->criar([
                'nome_original' => $nomeOriginal,
                'nome_s3' => $nomeS3,
                'caminho_s3' => $caminhoS3 . '/' . $nomeS3,
                'bucket' => $bucket,
                'tipo_mime' => $tipoMime,
                'extensao' => $extensao,
                'tamanho_bytes' => $tamanhoBytes,
                'hash_md5' => $hashMd5,
                'acl' => $acl,
                'url_publica' => $urlPublica,
                'metadata' => $metadata,
                'entidade_tipo' => $params['entidade_tipo'] ?? null,
                'entidade_id' => $params['entidade_id'] ?? null,
                'categoria' => $params['categoria'] ?? null,
                'criado_por' => $params['criado_por'] ?? null
            ]);

            // Calcula tempo de execução
            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            // Registra histórico
            $this->modelHistorico->registrar([
                'arquivo_id' => $arquivoId,
                'operacao' => 'upload',
                'status' => 'sucesso',
                'bucket' => $bucket,
                'caminho_s3' => $caminhoS3 . '/' . $nomeS3,
                'tamanho_bytes' => $tamanhoBytes,
                'detalhes' => [
                    'acl' => $acl,
                    'tipo_mime' => $tipoMime,
                    'etag' => $resultadoS3['etag'] ?? null
                ],
                'tempo_execucao_ms' => $tempoExecucao,
                'colaborador_id' => $params['criado_por'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return [
                'sucesso' => true,
                'arquivo_id' => $arquivoId,
                'arquivo' => $this->modelArquivo->buscarPorId($arquivoId),
                's3' => $resultadoS3
            ];

        } catch (Exception $e) {
            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            // Registra falha no histórico
            $this->modelHistorico->registrar([
                'arquivo_id' => null,
                'operacao' => 'upload',
                'status' => 'falha',
                'bucket' => $params['bucket'] ?? null,
                'caminho_s3' => $params['caminho_s3'] ?? null,
                'erro' => $e->getMessage(),
                'tempo_execucao_ms' => $tempoExecucao,
                'colaborador_id' => $params['criado_por'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            throw $e;
        }
    }

    /**
     * Upload de arquivo base64
     */
    public function uploadBase64(array $params): array
    {
        // Remove prefixo data:* se existir
        $base64 = preg_replace('#^data:[\w\/]+;base64,#i', '', $params['base64']);

        // Decodifica
        $conteudo = base64_decode($base64);

        if ($conteudo === false) {
            throw new Exception("Falha ao decodificar Base64.");
        }

        // Substitui conteúdo e chama upload normal
        $params['conteudo'] = $conteudo;
        unset($params['base64']);

        return $this->upload($params);
    }

    /**
     * Gera URL assinada para download
     */
    public function gerarUrlAssinada(int $arquivoId, ?int $expiracaoSegundos = null): array
    {
        $inicioTempo = microtime(true);

        try {
            $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

            if (!$arquivo) {
                throw new Exception("Arquivo não encontrado.");
            }

            // Se já é público, retorna URL pública
            if ($arquivo['acl'] === 'public-read' && !empty($arquivo['url_publica'])) {
                return [
                    'sucesso' => true,
                    'url' => $arquivo['url_publica'],
                    'expira_em' => null,
                    'publico' => true
                ];
            }

            // Obtém tempo de expiração configurado
            if ($expiracaoSegundos === null) {
                $expiracaoSegundos = (int)$this->modelConfig->obter('aws_url_expiration', 7200);
            }

            // Gera URL assinada
            $url = $this->obterCliente()->getPresignedUrl(
                $arquivo['bucket'],
                $arquivo['caminho_s3'],
                $expiracaoSegundos
            );

            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            // Registra histórico
            $this->modelHistorico->registrar([
                'arquivo_id' => $arquivoId,
                'operacao' => 'presigned_url',
                'status' => 'sucesso',
                'bucket' => $arquivo['bucket'],
                'caminho_s3' => $arquivo['caminho_s3'],
                'detalhes' => [
                    'expiracao_segundos' => $expiracaoSegundos,
                    'expira_em' => date('Y-m-d H:i:s', time() + $expiracaoSegundos)
                ],
                'tempo_execucao_ms' => $tempoExecucao,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return [
                'sucesso' => true,
                'url' => $url,
                'expira_em' => date('Y-m-d H:i:s', time() + $expiracaoSegundos),
                'publico' => false
            ];

        } catch (Exception $e) {
            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            $this->modelHistorico->registrar([
                'arquivo_id' => $arquivoId ?? null,
                'operacao' => 'presigned_url',
                'status' => 'falha',
                'erro' => $e->getMessage(),
                'tempo_execucao_ms' => $tempoExecucao,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            throw $e;
        }
    }

    /**
     * Deleta arquivo (soft delete + S3)
     */
    public function deletar(int $arquivoId, bool $deletarS3 = true): array
    {
        $inicioTempo = microtime(true);

        try {
            $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

            if (!$arquivo) {
                throw new Exception("Arquivo não encontrado.");
            }

            // Deleta do S3 se solicitado
            if ($deletarS3) {
                $this->obterCliente()->deleteObject(
                    $arquivo['bucket'],
                    $arquivo['caminho_s3']
                );
            }

            // Soft delete no banco
            $this->modelArquivo->deletar($arquivoId);

            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            // Registra histórico
            $this->modelHistorico->registrar([
                'arquivo_id' => $arquivoId,
                'operacao' => 'delete',
                'status' => 'sucesso',
                'bucket' => $arquivo['bucket'],
                'caminho_s3' => $arquivo['caminho_s3'],
                'detalhes' => [
                    'deletado_s3' => $deletarS3
                ],
                'tempo_execucao_ms' => $tempoExecucao,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return [
                'sucesso' => true,
                'arquivo' => $arquivo
            ];

        } catch (Exception $e) {
            $tempoExecucao = (int)((microtime(true) - $inicioTempo) * 1000);

            $this->modelHistorico->registrar([
                'arquivo_id' => $arquivoId ?? null,
                'operacao' => 'delete',
                'status' => 'falha',
                'erro' => $e->getMessage(),
                'tempo_execucao_ms' => $tempoExecucao,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            throw $e;
        }
    }

    /**
     * Upload de pasta inteira
     */
    public function uploadPasta(string $caminhoLocal, ?string $bucket = null, string $caminhoS3 = ''): array
    {
        $bucket = $bucket ?? $this->obterCliente()->obterBucketPadrao();

        $resultados = $this->obterCliente()->uploadFolder($caminhoLocal, $bucket, $caminhoS3);

        return [
            'sucesso' => true,
            'total' => count($resultados),
            'arquivos' => $resultados
        ];
    }

    /**
     * Validação de parâmetros de upload
     */
    private function validarParametrosUpload(array $params): void
    {
        if (empty($params['nome_original'])) {
            throw new Exception("Nome original do arquivo é obrigatório.");
        }

        if (empty($params['conteudo'])) {
            throw new Exception("Conteúdo do arquivo é obrigatório.");
        }

        // Valida tamanho máximo
        $tamanhoMaximo = (int)$this->modelConfig->obter('aws_max_file_size', 52428800);
        $tamanho = is_resource($params['conteudo']) ?
            fstat($params['conteudo'])['size'] :
            strlen($params['conteudo']);

        if ($tamanho > $tamanhoMaximo) {
            $tamanhoMB = round($tamanhoMaximo / 1024 / 1024, 2);
            throw new Exception("Arquivo excede o tamanho máximo permitido de {$tamanhoMB}MB.");
        }
    }

    /**
     * Gera caminho S3 baseado em data
     */
    private function gerarCaminhoS3(string $nomeOriginal): string
    {
        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        return "uploads/{$ano}/{$mes}/{$dia}";
    }

    /**
     * Gera nome único para arquivo
     */
    private function gerarNomeUnico(string $nomeOriginal): string
    {
        $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
        $nomeBase = pathinfo($nomeOriginal, PATHINFO_FILENAME);

        // Limpa nome base
        $nomeBase = preg_replace('/[^a-zA-Z0-9-_]/', '_', $nomeBase);
        $nomeBase = substr($nomeBase, 0, 50);

        // Adiciona timestamp e hash aleatório
        $timestamp = time();
        $hash = substr(md5(uniqid()), 0, 8);

        return "{$nomeBase}_{$timestamp}_{$hash}.{$extensao}";
    }

    /**
     * Obtém tipo MIME baseado na extensão
     */
    private function obterTipoMime(string $extensao): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[strtolower($extensao)] ?? 'application/octet-stream';
    }
}
