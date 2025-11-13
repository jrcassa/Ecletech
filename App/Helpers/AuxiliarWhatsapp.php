<?php

namespace App\Helpers;

/**
 * Helper para funções auxiliares do WhatsApp
 */
class AuxiliarWhatsapp
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
            3 => 'Entregue',
            4 => 'Lido',
            default => 'Desconhecido'
        };
    }

    /**
     * Mapeia nome do status do webhook para code
     */
    public static function webhookParaStatusCode(string $status): int
    {
        return match(strtolower($status)) {
            'error', 'failed' => 0,
            'pending', 'server' => 1,
            'sent', 'sent_device' => 2,
            'delivered' => 3,
            'read' => 4,
            default => 1
        };
    }

    /**
     * Gera HTML de badge de status
     */
    public static function getBadgeHtml(int $code): string
    {
        $classes = [
            0 => 'badge bg-danger',
            1 => 'badge bg-warning',
            2 => 'badge bg-info',
            3 => 'badge bg-primary',
            4 => 'badge bg-success'
        ];

        $class = $classes[$code] ?? 'badge bg-secondary';
        $nome = self::statusParaNome($code);

        return "<span class=\"{$class}\">{$nome}</span>";
    }

    /**
     * Limpa número de WhatsApp
     */
    public static function limparNumero(string $numero): string
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
    public static function formatarNumero(string $numero): string
    {
        // Remove caracteres não numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);

        // Formato: +55 (15) 99999-9999
        if (strlen($numero) == 13) { // Com código país
            return '+' . substr($numero, 0, 2) . ' (' . substr($numero, 2, 2) . ') ' .
                   substr($numero, 4, 5) . '-' . substr($numero, 9);
        }

        return $numero;
    }

    /**
     * Valida número de WhatsApp
     */
    public static function validarNumero(string $numero, int $minLength = 10, int $maxLength = 15): bool
    {
        $numero = preg_replace('/[^0-9]/', '', $numero);
        return strlen($numero) >= $minLength && strlen($numero) <= $maxLength;
    }

    /**
     * Verifica se string é base64
     */
    public static function isBase64(string $string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        // Verifica se tem prefixo data:
        if (strpos($string, 'data:') === 0) {
            return true;
        }

        // Verifica se é base64 válido
        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string) && base64_decode($string, true) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Converte prioridade em descrição
     */
    public static function prioridadeParaNome(int $prioridade): string
    {
        return match(true) {
            $prioridade >= 9 => 'Muito Alta',
            $prioridade >= 7 => 'Alta',
            $prioridade >= 4 => 'Média',
            $prioridade >= 2 => 'Baixa',
            default => 'Muito Baixa'
        };
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
        $tiposValidos = ['cliente', 'colaborador', 'fornecedor', 'transportadora'];
        return in_array(strtolower($tipo), $tiposValidos);
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
}
