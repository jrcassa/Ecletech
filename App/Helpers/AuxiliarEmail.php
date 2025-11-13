<?php

namespace App\Helpers;

/**
 * Helper para funções auxiliares do Email
 * Padrão: Segue estrutura do AuxiliarWhatsapp
 */
class AuxiliarEmail
{
    /**
     * Mapeia status code para nome
     */
    public static function statusParaNome(int $code): string
    {
        return match($code) {
            0 => 'Erro',
            1 => 'Pendente',
            2 => 'Enviado',
            3 => 'Bounce',
            4 => 'Aberto',
            5 => 'Clicado',
            default => 'Desconhecido'
        };
    }

    /**
     * Gera HTML de badge de status
     */
    public static function getBadgeHtml(int $code): string
    {
        $classes = [
            0 => 'badge bg-danger',      // Erro
            1 => 'badge bg-warning',     // Pendente
            2 => 'badge bg-info',        // Enviado
            3 => 'badge bg-danger',      // Bounce
            4 => 'badge bg-primary',     // Aberto
            5 => 'badge bg-success'      // Clicado
        ];

        $class = $classes[$code] ?? 'badge bg-secondary';
        $nome = self::statusParaNome($code);

        return "<span class=\"{$class}\">{$nome}</span>";
    }

    /**
     * Valida endereço de email
     */
    public static function validarEmail(string $email): bool
    {
        $email = trim($email);

        if (empty($email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Limpa e normaliza email
     */
    public static function limparEmail(string $email): string
    {
        return trim(strtolower($email));
    }

    /**
     * Extrai domínio do email
     */
    public static function extrairDominio(string $email): ?string
    {
        if (!self::validarEmail($email)) {
            return null;
        }

        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }

    /**
     * Verifica se email é de domínio público (Gmail, Hotmail, etc)
     */
    public static function isDominioPublico(string $email): bool
    {
        $dominio = self::extrairDominio($email);

        if ($dominio === null) {
            return false;
        }

        $dominiosPublicos = [
            'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'yahoo.com.br',
            'live.com', 'msn.com', 'icloud.com', 'me.com', 'aol.com',
            'uol.com.br', 'bol.com.br', 'terra.com.br', 'ig.com.br', 'globo.com'
        ];

        return in_array($dominio, $dominiosPublicos);
    }

    /**
     * Mascara email para privacidade (ex: jo***@example.com)
     */
    public static function mascarar(string $email): string
    {
        if (!self::validarEmail($email)) {
            return $email;
        }

        list($local, $dominio) = explode('@', $email);

        if (strlen($local) <= 3) {
            $localMascarado = $local[0] . '***';
        } else {
            $localMascarado = substr($local, 0, 2) . '***' . substr($local, -1);
        }

        return $localMascarado . '@' . $dominio;
    }

    /**
     * Parse de entidade no formato "tipo:id"
     */
    public static function parseEntidade(string $entidade): ?array
    {
        if (strpos($entidade, ':') === false) {
            return null;
        }

        list($tipo, $id) = explode(':', $entidade, 2);

        return [
            'tipo' => trim($tipo),
            'id' => (int) trim($id)
        ];
    }

    /**
     * Valida tipos de entidade permitidos
     */
    public static function tipoEntidadeValido(string $tipo): bool
    {
        $tiposValidos = ['cliente', 'colaborador', 'fornecedor', 'transportadora', 'outro'];
        return in_array(strtolower($tipo), $tiposValidos);
    }

    /**
     * Converte prioridade texto para ordem
     */
    public static function prioridadeParaOrdem(string $prioridade): int
    {
        return match(strtolower($prioridade)) {
            'urgente' => 1,
            'alta' => 2,
            'normal' => 3,
            'baixa' => 4,
            default => 3
        };
    }

    /**
     * Converte prioridade para nome amigável
     */
    public static function prioridadeParaNome(string $prioridade): string
    {
        return match(strtolower($prioridade)) {
            'urgente' => 'Urgente',
            'alta' => 'Alta',
            'normal' => 'Normal',
            'baixa' => 'Baixa',
            default => 'Normal'
        };
    }

    /**
     * Formata tamanho de arquivo
     */
    public static function formatarTamanhoArquivo(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $unidades[$i];
    }

    /**
     * Resumir texto para um número de caracteres
     */
    public static function resumirTexto(?string $texto, int $limite = 200): ?string
    {
        if ($texto === null) {
            return null;
        }

        // Remove tags HTML
        $texto = strip_tags($texto);

        // Remove múltiplos espaços
        $texto = preg_replace('/\s+/', ' ', $texto);

        $texto = trim($texto);

        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite) . '...';
    }

    /**
     * Detecta tipo MIME de anexo por extensão
     */
    public static function detectarMimeType(string $nomeArquivo): string
    {
        $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Documentos
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'rtf' => 'application/rtf',

            // Imagens
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',

            // Arquivos compactados
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',

            // Outros
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'htm' => 'text/html'
        ];

        return $mimeTypes[$extensao] ?? 'application/octet-stream';
    }

    /**
     * Valida extensão de arquivo permitida
     */
    public static function extensaoPermitida(string $nomeArquivo, array $permitidas): bool
    {
        $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        return in_array($extensao, $permitidas);
    }

    /**
     * Sanitiza nome de arquivo
     */
    public static function sanitizarNomeArquivo(string $nome): string
    {
        // Remove caracteres especiais mantendo extensão
        $extensao = pathinfo($nome, PATHINFO_EXTENSION);
        $base = pathinfo($nome, PATHINFO_FILENAME);

        // Remove caracteres não alfanuméricos (exceto - e _)
        $base = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $base);

        // Remove múltiplos underscores
        $base = preg_replace('/_+/', '_', $base);

        return $base . ($extensao ? '.' . $extensao : '');
    }

    /**
     * Converte HTML para texto plano
     */
    public static function htmlParaTexto(string $html): string
    {
        // Adiciona quebras de linha antes de tags de bloco
        $html = preg_replace('/<(br|p|div|h[1-6]|li)[^>]*>/i', "\n", $html);

        // Remove tags HTML
        $texto = strip_tags($html);

        // Decodifica entidades HTML
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove múltiplas quebras de linha
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        // Remove múltiplos espaços
        $texto = preg_replace('/[ \t]+/', ' ', $texto);

        return trim($texto);
    }

    /**
     * Formata data para exibição amigável
     */
    public static function formatarDataAmigavel(?string $data): string
    {
        if ($data === null) {
            return '-';
        }

        $timestamp = strtotime($data);
        $agora = time();
        $diferenca = $agora - $timestamp;

        // Menos de 1 minuto
        if ($diferenca < 60) {
            return 'Agora mesmo';
        }

        // Menos de 1 hora
        if ($diferenca < 3600) {
            $minutos = floor($diferenca / 60);
            return $minutos . ' min atrás';
        }

        // Menos de 24 horas
        if ($diferenca < 86400) {
            $horas = floor($diferenca / 3600);
            return $horas . 'h atrás';
        }

        // Menos de 7 dias
        if ($diferenca < 604800) {
            $dias = floor($diferenca / 86400);
            return $dias . 'd atrás';
        }

        // Mais de 7 dias: formato padrão
        return date('d/m/Y H:i', $timestamp);
    }
}
