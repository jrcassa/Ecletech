<?php

namespace App\Models\S3;

use App\Helpers\ErrorLogger;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

/**
 * Model para gerenciar cliente AWS S3
 * Encapsula toda a lógica de interação com o SDK da AWS
 */
class ModelS3Cliente
{
    private ?S3Client $s3Client = null;
    private ModelS3Configuracao $config;

    // Configurações carregadas
    private ?string $accessKeyId = null;
    private ?string $secretAccessKey = null;
    private ?string $region = null;
    private ?string $endpoint = null;
    private ?string $defaultBucket = null;
    private bool $usePathStyleEndpoint = false;
    private string $version = 'latest';

    public function __construct()
    {
        $this->config = new ModelS3Configuracao();
        $this->carregarConfiguracoes();
    }

    /**
     * Carrega configurações do banco de dados
     */
    private function carregarConfiguracoes(): void
    {
        $this->accessKeyId = $this->config->obter('aws_access_key_id');
        $this->secretAccessKey = $this->config->obter('aws_secret_access_key');
        $this->region = $this->config->obter('aws_region', 'us-east-1');
        $this->endpoint = $this->config->obter('aws_endpoint');
        $this->defaultBucket = $this->config->obter('aws_default_bucket');
        $this->usePathStyleEndpoint = (bool)$this->config->obter('aws_use_path_style_endpoint', false);
        $this->version = $this->config->obter('aws_s3_version', 'latest');
    }

    /**
     * Verifica se as configurações estão válidas
     */
    private function validarConfiguracao(): void
    {
        if (empty($this->accessKeyId)) {
            throw new Exception('AWS Access Key ID não configurado. Configure em s3_configuracoes.');
        }

        if (empty($this->secretAccessKey)) {
            throw new Exception('AWS Secret Access Key não configurado. Configure em s3_configuracoes.');
        }

        if (empty($this->region)) {
            throw new Exception('AWS Region não configurada. Configure em s3_configuracoes.');
        }
    }

    /**
     * Obtém instância do S3Client (lazy loading)
     */
    private function obterCliente(): S3Client
    {
        if ($this->s3Client !== null) {
            return $this->s3Client;
        }

        $this->validarConfiguracao();

        $configuracao = [
            'version' => $this->version,
            'region'  => $this->region,
            'credentials' => [
                'key'    => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ]
        ];

        // Adiciona endpoint customizado se configurado (para Contabo, MinIO, etc)
        if (!empty($this->endpoint)) {
            $configuracao['endpoint'] = $this->endpoint;
        }

        // Path-style endpoint (necessário para Contabo, MinIO)
        if ($this->usePathStyleEndpoint) {
            $configuracao['use_path_style_endpoint'] = true;
        }

        $this->s3Client = new S3Client($configuracao);

        return $this->s3Client;
    }

    /**
     * Upload de arquivo para S3 (recurso stream)
     */
    public function putObject(
        string $bucket,
        string $key,
        $body,
        string $contentType = 'application/octet-stream',
        string $acl = 'private',
        array $metadata = []
    ): array {
        try {
            $params = [
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => $body,
                'ACL'    => $acl,
                'ContentType' => $contentType
            ];

            // Adiciona metadados se fornecidos
            if (!empty($metadata)) {
                $params['Metadata'] = $metadata;
            }

            $resultado = $this->obterCliente()->putObject($params);

            return [
                'sucesso' => true,
                'url' => $resultado['ObjectURL'] ?? null,
                'etag' => $resultado['ETag'] ?? null,
                'versao_id' => $resultado['VersionId'] ?? null
            ];
        } catch (AwsException $e) {
            throw new Exception("Erro ao fazer upload para S3: " . $e->getMessage());
        }
    }

    /**
     * Upload de arquivo base64
     */
    public function putBase64Object(
        string $bucket,
        string $key,
        string $base64,
        string $extension,
        string $acl = 'private',
        array $metadata = []
    ): array {
        try {
            // Remove prefixo data:* se existir
            $base64 = preg_replace('#^data:[\w\/]+;base64,#i', '', $base64);

            // Decodifica
            $binaryContent = base64_decode($base64);

            if ($binaryContent === false) {
                throw new Exception("Falha ao decodificar Base64.");
            }

            // Determina content type
            $contentType = $this->obterContentType($extension);

            return $this->putObject($bucket, $key, $binaryContent, $contentType, $acl, $metadata);
        } catch (AwsException $e) {
            throw new Exception("Erro ao fazer upload Base64: " . $e->getMessage());
        }
    }

    /**
     * Upload de arquivo base64 usando stream (mais eficiente)
     */
    public function putBase64ObjectStream(
        string $bucket,
        string $key,
        string $base64,
        string $extension,
        string $acl = 'private',
        array $metadata = []
    ): array {
        // Remove prefixo data:* se existir
        $base64 = preg_replace('#^data:[\w\/]+;base64,#i', '', $base64);

        // Decodifica
        $binary = base64_decode($base64);

        if ($binary === false) {
            throw new Exception("Falha ao decodificar Base64.");
        }

        // Cria stream em memória
        $resource = fopen('php://temp', 'r+b');
        fwrite($resource, $binary);
        rewind($resource);

        // Define ContentType
        $contentType = $this->obterContentType($extension);

        // Faz upload
        $resultado = $this->putObject($bucket, $key, $resource, $contentType, $acl, $metadata);

        // Fecha stream
        fclose($resource);

        return $resultado;
    }

    /**
     * Baixa objeto do S3 e retorna em base64
     */
    public function getObjectBase64(string $bucket, string $key): array
    {
        try {
            $result = $this->obterCliente()->getObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $conteudo = (string) $result['Body'];
            $base64 = base64_encode($conteudo);

            // Extrai nome do arquivo do caminho
            $pathParts = explode('/', $key);
            $filename = end($pathParts);

            return [
                'base64' => $base64,
                'filename' => $filename,
                'content_type' => $result['ContentType'] ?? 'application/octet-stream',
                'size' => $result['ContentLength'] ?? strlen($conteudo)
            ];
        } catch (AwsException $e) {
            throw new Exception("Erro ao baixar objeto: " . $e->getMessage());
        }
    }

    /**
     * Gera URL assinada (presigned URL) para download
     */
    public function getPresignedUrl(
        string $bucket,
        string $key,
        int $expiracaoSegundos = 7200
    ): string {
        try {
            $cmd = $this->obterCliente()->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $request = $this->obterCliente()->createPresignedRequest(
                $cmd,
                "+{$expiracaoSegundos} seconds"
            );

            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new Exception("Erro ao gerar URL assinada: " . $e->getMessage());
        }
    }

    /**
     * Deleta um objeto do S3
     */
    public function deleteObject(string $bucket, string $key): bool
    {
        try {
            $this->obterCliente()->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            return true;
        } catch (AwsException $e) {
            throw new Exception("Erro ao deletar objeto: " . $e->getMessage());
        }
    }

    /**
     * Verifica se um objeto existe
     */
    public function objectExists(string $bucket, string $key): bool
    {
        try {
            return $this->obterCliente()->doesObjectExist($bucket, $key);
        } catch (AwsException $e) {
            ErrorLogger::log($e, 's3', 'alto', [
                'contexto' => 'verificar_objeto_existe',
                'bucket' => $bucket,
                'key' => $key
            ]);
            return false;
        }
    }

    /**
     * Lista objetos em um bucket
     */
    public function listObjects(
        string $bucket,
        string $prefix = '',
        int $maxKeys = 1000
    ): array {
        try {
            $resultado = $this->obterCliente()->listObjects([
                'Bucket' => $bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys
            ]);

            return $resultado['Contents'] ?? [];
        } catch (AwsException $e) {
            throw new Exception("Erro ao listar objetos: " . $e->getMessage());
        }
    }

    /**
     * Obtém metadados de um objeto
     */
    public function getObjectMetadata(string $bucket, string $key): array
    {
        try {
            $resultado = $this->obterCliente()->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            return [
                'content_type' => $resultado['ContentType'] ?? null,
                'content_length' => $resultado['ContentLength'] ?? null,
                'etag' => $resultado['ETag'] ?? null,
                'last_modified' => $resultado['LastModified'] ?? null,
                'metadata' => $resultado['Metadata'] ?? []
            ];
        } catch (AwsException $e) {
            throw new Exception("Erro ao obter metadados: " . $e->getMessage());
        }
    }

    /**
     * Testa conexão com S3
     */
    public function testarConexao(): array
    {
        try {
            $bucket = $this->defaultBucket ?? 'test-bucket';

            // Tenta listar objetos (sem precisar de permissão de escrita)
            $this->obterCliente()->listObjects([
                'Bucket' => $bucket,
                'MaxKeys' => 1
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Conexão com S3 estabelecida com sucesso!'
            ];
        } catch (AwsException $e) {
            ErrorLogger::log($e, 's3', 'alto', [
                'contexto' => 'teste_conexao_s3',
                'bucket' => $this->defaultBucket ?? 'test-bucket',
                'codigo_erro' => $e->getAwsErrorCode()
            ]);
            return [
                'sucesso' => false,
                'mensagem' => 'Falha na conexão: ' . $e->getMessage(),
                'codigo_erro' => $e->getAwsErrorCode()
            ];
        } catch (Exception $e) {
            ErrorLogger::log($e, 's3', 'alto', [
                'contexto' => 'teste_conexao_s3',
                'bucket' => $this->defaultBucket ?? 'test-bucket'
            ]);
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém bucket padrão configurado
     */
    public function obterBucketPadrao(): ?string
    {
        return $this->defaultBucket;
    }

    /**
     * Determina Content-Type baseado na extensão
     */
    private function obterContentType(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Upload de pasta inteira recursivamente
     */
    public function uploadFolder(
        string $localPath,
        string $bucket,
        string $s3Path = ''
    ): array {
        $resultados = [];

        if (!is_dir($localPath)) {
            throw new Exception("Caminho local não é um diretório: {$localPath}");
        }

        $items = scandir($localPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $localPath . '/' . $item;
            $s3Key = $s3Path ? $s3Path . '/' . $item : $item;

            if (is_dir($fullPath)) {
                // Pasta: chama recursivamente
                $resultados = array_merge(
                    $resultados,
                    $this->uploadFolder($fullPath, $bucket, $s3Key)
                );
            } else {
                // Arquivo: faz upload
                $ext = pathinfo($item, PATHINFO_EXTENSION);
                $contentType = $this->obterContentType($ext);

                $resource = fopen($fullPath, 'rb');
                $resultado = $this->putObject($bucket, $s3Key, $resource, $contentType);
                fclose($resource);

                $resultados[] = [
                    'caminho_local' => $fullPath,
                    's3_key' => $s3Key,
                    'sucesso' => $resultado['sucesso']
                ];
            }
        }

        return $resultados;
    }
}
