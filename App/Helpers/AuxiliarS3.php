<?php

namespace App\Helpers;

/**
 * Auxiliar para operações relacionadas ao S3
 * Funções utilitárias para validação, conversão e formatação
 */
class AuxiliarS3
{
    /**
     * Valida se uma string está em formato base64 válido
     */
    public static function validarBase64(string $string): bool
    {
        // Remove prefixo data:* se existir
        $string = preg_replace('#^data:[\w\/]+;base64,#i', '', $string);

        // Valida se é base64 válido
        if (base64_encode(base64_decode($string, true)) === $string) {
            return true;
        }

        return false;
    }

    /**
     * Extrai extensão de uma string base64 com data URI
     */
    public static function extrairExtensaoBase64(string $base64): ?string
    {
        if (preg_match('#^data:[\w\/]+;base64,#i', $base64, $matches)) {
            $dataUri = $matches[0];

            if (preg_match('#data:([\w\/]+);#', $dataUri, $mimeMatches)) {
                $mime = $mimeMatches[1];

                return self::obterExtensaoPorMime($mime);
            }
        }

        return null;
    }

    /**
     * Obtém extensão baseada em tipo MIME
     */
    public static function obterExtensaoPorMime(string $mime): string
    {
        $mapeamento = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        return $mapeamento[$mime] ?? 'bin';
    }

    /**
     * Obtém tipo MIME baseado em extensão
     */
    public static function obterMimePorExtensao(string $extensao): string
    {
        $mapeamento = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return $mapeamento[strtolower($extensao)] ?? 'application/octet-stream';
    }

    /**
     * Formata tamanho em bytes para formato legível
     */
    public static function formatarTamanho(int $bytes, int $decimais = 2): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024 && $i < count($unidades) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $decimais) . ' ' . $unidades[$i];
    }

    /**
     * Converte tamanho legível para bytes
     */
    public static function tamanhoParaBytes(string $tamanho): int
    {
        $tamanho = trim($tamanho);
        $ultimo = strtolower($tamanho[strlen($tamanho) - 1]);
        $numero = (int) $tamanho;

        switch ($ultimo) {
            case 'g':
                $numero *= 1024;
                // no break - continua para MB
            case 'm':
                $numero *= 1024;
                // no break - continua para KB
            case 'k':
                $numero *= 1024;
        }

        return $numero;
    }

    /**
     * Valida nome de arquivo
     */
    public static function validarNomeArquivo(string $nome): bool
    {
        // Não pode conter caracteres especiais perigosos
        $caracteresProibidos = ['/', '\\', '..', '<', '>', ':', '"', '|', '?', '*'];

        foreach ($caracteresProibidos as $char) {
            if (strpos($nome, $char) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitiza nome de arquivo
     */
    public static function sanitizarNomeArquivo(string $nome): string
    {
        // Remove acentos
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);

        // Remove caracteres especiais
        $nome = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nome);

        // Remove underscores duplicados
        $nome = preg_replace('/_+/', '_', $nome);

        // Limita tamanho
        if (strlen($nome) > 255) {
            $extensao = pathinfo($nome, PATHINFO_EXTENSION);
            $nomeBase = pathinfo($nome, PATHINFO_FILENAME);
            $nomeBase = substr($nomeBase, 0, 255 - strlen($extensao) - 1);
            $nome = $nomeBase . '.' . $extensao;
        }

        return $nome;
    }

    /**
     * Verifica se arquivo é imagem
     */
    public static function eImagem(string $nomeOuMime): bool
    {
        // Se tem extensão
        if (strpos($nomeOuMime, '.') !== false) {
            $extensao = strtolower(pathinfo($nomeOuMime, PATHINFO_EXTENSION));
            $extensoesImagem = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];

            return in_array($extensao, $extensoesImagem);
        }

        // Se é MIME type
        return strpos($nomeOuMime, 'image/') === 0;
    }

    /**
     * Verifica se arquivo é vídeo
     */
    public static function eVideo(string $nomeOuMime): bool
    {
        if (strpos($nomeOuMime, '.') !== false) {
            $extensao = strtolower(pathinfo($nomeOuMime, PATHINFO_EXTENSION));
            $extensoesVideo = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];

            return in_array($extensao, $extensoesVideo);
        }

        return strpos($nomeOuMime, 'video/') === 0;
    }

    /**
     * Verifica se arquivo é documento
     */
    public static function eDocumento(string $nomeOuMime): bool
    {
        if (strpos($nomeOuMime, '.') !== false) {
            $extensao = strtolower(pathinfo($nomeOuMime, PATHINFO_EXTENSION));
            $extensoesDoc = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

            return in_array($extensao, $extensoesDoc);
        }

        $mimesDocs = ['application/pdf', 'application/msword', 'text/plain', 'text/csv'];

        foreach ($mimesDocs as $mime) {
            if (strpos($nomeOuMime, $mime) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera caminho S3 organizado por data
     */
    public static function gerarCaminhoData(string $prefixo = 'uploads'): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            $prefixo,
            date('Y'),
            date('m'),
            date('d')
        );
    }

    /**
     * Gera nome único para arquivo
     */
    public static function gerarNomeUnico(string $nomeOriginal): string
    {
        $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
        $nomeBase = pathinfo($nomeOriginal, PATHINFO_FILENAME);

        // Sanitiza nome base
        $nomeBase = self::sanitizarNomeArquivo($nomeBase);
        $nomeBase = substr($nomeBase, 0, 50);

        // Adiciona timestamp e hash
        $timestamp = time();
        $hash = substr(md5(uniqid()), 0, 8);

        return "{$nomeBase}_{$timestamp}_{$hash}.{$extensao}";
    }

    /**
     * Valida ACL do S3
     */
    public static function validarAcl(string $acl): bool
    {
        $aclsValidos = [
            'private',
            'public-read',
            'public-read-write',
            'authenticated-read',
            'aws-exec-read',
            'bucket-owner-read',
            'bucket-owner-full-control'
        ];

        return in_array($acl, $aclsValidos);
    }

    /**
     * Calcula hash MD5 de arquivo
     */
    public static function calcularHashArquivo(string $caminho): string
    {
        return md5_file($caminho);
    }

    /**
     * Calcula hash MD5 de conteúdo
     */
    public static function calcularHashConteudo(string $conteudo): string
    {
        return md5($conteudo);
    }

    /**
     * Valida nome de bucket S3
     */
    public static function validarNomeBucket(string $bucket): bool
    {
        // Regras AWS:
        // - 3-63 caracteres
        // - Apenas letras minúsculas, números, hífens e pontos
        // - Deve começar e terminar com letra ou número
        if (strlen($bucket) < 3 || strlen($bucket) > 63) {
            return false;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket)) {
            return false;
        }

        // Não pode ter pontos consecutivos
        if (strpos($bucket, '..') !== false) {
            return false;
        }

        // Não pode parecer IP
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $bucket)) {
            return false;
        }

        return true;
    }

    /**
     * Converte metadata para formato aceito pelo S3
     */
    public static function formatarMetadata(array $metadata): array
    {
        $formatado = [];

        foreach ($metadata as $chave => $valor) {
            // Remove caracteres especiais da chave
            $chave = preg_replace('/[^a-zA-Z0-9-_]/', '-', $chave);

            // Converte valor para string
            if (is_array($valor) || is_object($valor)) {
                $valor = json_encode($valor);
            } else {
                $valor = (string) $valor;
            }

            $formatado[$chave] = $valor;
        }

        return $formatado;
    }

    /**
     * Valida URL do S3
     */
    public static function validarUrlS3(string $url): bool
    {
        // Padrões de URL S3
        $padroes = [
            '#^https?://[a-z0-9.-]+\.s3\.amazonaws\.com/#i',
            '#^https?://s3\.[a-z0-9-]+\.amazonaws\.com/#i',
            '#^https?://[a-z0-9.-]+\.s3-[a-z0-9-]+\.amazonaws\.com/#i',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $url)) {
                return true;
            }
        }

        return false;
    }
}
