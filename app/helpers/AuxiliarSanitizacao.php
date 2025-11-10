<?php

namespace App\Helpers;

/**
 * Classe auxiliar para sanitização de dados
 */
class AuxiliarSanitizacao
{
    /**
     * Sanitiza string removendo tags HTML
     */
    public static function string(string $valor): string
    {
        return strip_tags(trim($valor));
    }

    /**
     * Sanitiza email
     */
    public static function email(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitiza URL
     */
    public static function url(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitiza inteiro
     */
    public static function inteiro(mixed $valor): int
    {
        return (int) filter_var($valor, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitiza float
     */
    public static function float(mixed $valor): float
    {
        return (float) filter_var($valor, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitiza booleano
     */
    public static function booleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (in_array($valor, [1, '1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Sanitiza CPF removendo caracteres não numéricos
     */
    public static function cpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    /**
     * Sanitiza CNPJ removendo caracteres não numéricos
     */
    public static function cnpj(string $cnpj): string
    {
        return preg_replace('/[^0-9]/', '', $cnpj);
    }

    /**
     * Sanitiza telefone removendo caracteres não numéricos
     */
    public static function telefone(string $telefone): string
    {
        return preg_replace('/[^0-9]/', '', $telefone);
    }

    /**
     * Sanitiza CEP removendo caracteres não numéricos
     */
    public static function cep(string $cep): string
    {
        return preg_replace('/[^0-9]/', '', $cep);
    }

    /**
     * Remove XSS de uma string
     */
    public static function antiXss(string $valor): string
    {
        // Converte caracteres especiais em entidades HTML
        $valor = htmlspecialchars($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove tags de script
        $valor = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $valor);

        // Remove atributos de evento
        $valor = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $valor);

        // Remove javascript:
        $valor = preg_replace('/javascript:/i', '', $valor);

        return $valor;
    }

    /**
     * Sanitiza SQL (previne SQL Injection)
     * Nota: Use prepared statements sempre que possível
     */
    public static function sql(string $valor): string
    {
        return addslashes(trim($valor));
    }

    /**
     * Sanitiza nome de arquivo
     */
    public static function nomeArquivo(string $nome): string
    {
        // Remove caracteres especiais, mantém apenas alfanuméricos, pontos, hífens e underscores
        $nome = preg_replace('/[^a-zA-Z0-9._-]/', '', $nome);

        // Remove múltiplos pontos consecutivos
        $nome = preg_replace('/\.+/', '.', $nome);

        // Remove pontos no início e fim
        $nome = trim($nome, '.');

        return $nome;
    }

    /**
     * Sanitiza caminho de arquivo
     */
    public static function caminhoArquivo(string $caminho): string
    {
        // Remove tentativas de directory traversal
        $caminho = str_replace(['../', '..\\'], '', $caminho);

        // Normaliza separadores
        $caminho = str_replace('\\', '/', $caminho);

        // Remove múltiplas barras
        $caminho = preg_replace('#/+#', '/', $caminho);

        return $caminho;
    }

    /**
     * Sanitiza slug (para URLs amigáveis)
     */
    public static function slug(string $texto): string
    {
        // Converte para minúsculas
        $texto = mb_strtolower($texto, 'UTF-8');

        // Remove acentuação
        $texto = self::removerAcentos($texto);

        // Substitui espaços e caracteres especiais por hífen
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

        // Remove hífens duplicados
        $texto = preg_replace('/-+/', '-', $texto);

        // Remove hífens do início e fim
        $texto = trim($texto, '-');

        return $texto;
    }

    /**
     * Remove acentuação de uma string
     */
    public static function removerAcentos(string $texto): string
    {
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];

        return strtr($texto, $acentos);
    }

    /**
     * Sanitiza array recursivamente
     */
    public static function array(array $dados, callable $funcao = null): array
    {
        $funcao = $funcao ?? [self::class, 'string'];

        $resultado = [];
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $resultado[$chave] = self::array($valor, $funcao);
            } else {
                $resultado[$chave] = call_user_func($funcao, $valor);
            }
        }

        return $resultado;
    }

    /**
     * Sanitiza HTML permitindo apenas tags seguras
     */
    public static function html(string $html, array $tagsPermitidas = []): string
    {
        if (empty($tagsPermitidas)) {
            $tagsPermitidas = ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        }

        $permitidas = '<' . implode('><', $tagsPermitidas) . '>';
        return strip_tags($html, $permitidas);
    }

    /**
     * Sanitiza JSON
     */
    public static function json(string $json): ?array
    {
        $dados = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return self::array($dados);
    }

    /**
     * Remove espaços extras
     */
    public static function espacos(string $texto): string
    {
        // Remove espaços no início e fim
        $texto = trim($texto);

        // Substitui múltiplos espaços por um único
        $texto = preg_replace('/\s+/', ' ', $texto);

        return $texto;
    }

    /**
     * Trunca texto
     */
    public static function truncar(string $texto, int $tamanho = 100, string $sufixo = '...'): string
    {
        if (mb_strlen($texto) <= $tamanho) {
            return $texto;
        }

        return mb_substr($texto, 0, $tamanho) . $sufixo;
    }

    /**
     * Sanitiza input do usuário removendo caracteres perigosos
     */
    public static function input(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return self::array($valor);
        }

        if (is_string($valor)) {
            return self::antiXss(trim($valor));
        }

        return $valor;
    }
}
