<?php

namespace App\Helpers;

/**
 * Classe auxiliar para validação de dados
 */
class AuxiliarValidacao
{
    /**
     * Valida email
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida URL
     */
    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valida IP
     */
    public static function ip(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Valida CPF
     */
    public static function cpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Calcula o primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if (intval($cpf[9]) !== $digito1) {
            return false;
        }

        // Calcula o segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return intval($cpf[10]) === $digito2;
    }

    /**
     * Valida CNPJ
     */
    public static function cnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $soma = 0;
        $multiplicador = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += intval($cnpj[$i]) * $multiplicador;
            $multiplicador = $multiplicador === 2 ? 9 : $multiplicador - 1;
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if (intval($cnpj[12]) !== $digito1) {
            return false;
        }

        // Valida segundo dígito verificador
        $soma = 0;
        $multiplicador = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += intval($cnpj[$i]) * $multiplicador;
            $multiplicador = $multiplicador === 2 ? 9 : $multiplicador - 1;
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return intval($cnpj[13]) === $digito2;
    }

    /**
     * Valida telefone brasileiro
     */
    public static function telefone(string $telefone): bool
    {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        // Aceita formato com ou sem DDD (10 ou 11 dígitos)
        return preg_match('/^[1-9]{2}9?[0-9]{8}$/', $telefone) === 1;
    }

    /**
     * Valida CEP
     */
    public static function cep(string $cep): bool
    {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return preg_match('/^[0-9]{8}$/', $cep) === 1;
    }

    /**
     * Valida data (formato YYYY-MM-DD)
     */
    public static function data(string $data): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $matches)) {
            return false;
        }

        return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
    }

    /**
     * Valida data/hora (formato YYYY-MM-DD HH:MM:SS)
     */
    public static function dataHora(string $dataHora): bool
    {
        $formato = 'Y-m-d H:i:s';
        $d = \DateTime::createFromFormat($formato, $dataHora);
        return $d && $d->format($formato) === $dataHora;
    }

    /**
     * Valida número
     */
    public static function numero(mixed $valor): bool
    {
        return is_numeric($valor);
    }

    /**
     * Valida inteiro
     */
    public static function inteiro(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Valida float
     */
    public static function float(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * Valida booleano
     */
    public static function booleano(mixed $valor): bool
    {
        return is_bool($valor) || in_array($valor, [0, 1, '0', '1', 'true', 'false'], true);
    }

    /**
     * Valida JSON
     */
    public static function json(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Valida tamanho mínimo
     */
    public static function min(string|array $valor, int $min): bool
    {
        $tamanho = is_array($valor) ? count($valor) : mb_strlen($valor);
        return $tamanho >= $min;
    }

    /**
     * Valida tamanho máximo
     */
    public static function max(string|array $valor, int $max): bool
    {
        $tamanho = is_array($valor) ? count($valor) : mb_strlen($valor);
        return $tamanho <= $max;
    }

    /**
     * Valida se o valor está entre min e max
     */
    public static function entre(int|float $valor, int|float $min, int|float $max): bool
    {
        return $valor >= $min && $valor <= $max;
    }

    /**
     * Valida se o valor está em uma lista
     */
    public static function em(mixed $valor, array $lista): bool
    {
        return in_array($valor, $lista, true);
    }

    /**
     * Valida regex
     */
    public static function regex(string $valor, string $padrao): bool
    {
        return preg_match($padrao, $valor) === 1;
    }

    /**
     * Valida se é alfanumérico
     */
    public static function alfanumerico(string $valor): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $valor) === 1;
    }

    /**
     * Valida se contém apenas letras
     */
    public static function alfabetico(string $valor): bool
    {
        return preg_match('/^[a-zA-Z]+$/', $valor) === 1;
    }

    /**
     * Valida campo obrigatório
     */
    public static function obrigatorio(mixed $valor): bool
    {
        if (is_null($valor)) {
            return false;
        }

        if (is_string($valor) && trim($valor) === '') {
            return false;
        }

        if (is_array($valor) && empty($valor)) {
            return false;
        }

        return true;
    }

    /**
     * Valida múltiplas regras
     */
    public static function validar(array $dados, array $regras): array
    {
        $erros = [];

        foreach ($regras as $campo => $regrasString) {
            $regrasList = explode('|', $regrasString);
            $valor = $dados[$campo] ?? null;

            foreach ($regrasList as $regra) {
                // Separa a regra do parâmetro (ex: "min:3")
                $parametro = null;
                if (strpos($regra, ':') !== false) {
                    [$regra, $parametro] = explode(':', $regra, 2);
                }

                // Executa a validação
                $valido = match ($regra) {
                    'obrigatorio' => self::obrigatorio($valor),
                    'email' => self::email($valor ?? ''),
                    'url' => self::url($valor ?? ''),
                    'cpf' => self::cpf($valor ?? ''),
                    'cnpj' => self::cnpj($valor ?? ''),
                    'telefone' => self::telefone($valor ?? ''),
                    'cep' => self::cep($valor ?? ''),
                    'data' => self::data($valor ?? ''),
                    'numero' => self::numero($valor),
                    'inteiro' => self::inteiro($valor),
                    'alfanumerico' => self::alfanumerico($valor ?? ''),
                    'alfabetico' => self::alfabetico($valor ?? ''),
                    'min' => $parametro ? self::min($valor ?? '', (int)$parametro) : true,
                    'max' => $parametro ? self::max($valor ?? '', (int)$parametro) : true,
                    'em' => $parametro ? self::em($valor, explode(',', $parametro)) : true,
                    default => true
                };

                if (!$valido) {
                    $erros[$campo][] = self::obterMensagemErro($campo, $regra, $parametro);
                }
            }
        }

        return $erros;
    }

    /**
     * Obtém mensagem de erro para a regra
     */
    private static function obterMensagemErro(string $campo, string $regra, ?string $parametro = null): string
    {
        return match ($regra) {
            'obrigatorio' => "O campo {$campo} é obrigatório",
            'email' => "O campo {$campo} deve ser um email válido",
            'url' => "O campo {$campo} deve ser uma URL válida",
            'cpf' => "O campo {$campo} deve ser um CPF válido",
            'cnpj' => "O campo {$campo} deve ser um CNPJ válido",
            'telefone' => "O campo {$campo} deve ser um telefone válido",
            'cep' => "O campo {$campo} deve ser um CEP válido",
            'data' => "O campo {$campo} deve ser uma data válida",
            'numero' => "O campo {$campo} deve ser um número",
            'inteiro' => "O campo {$campo} deve ser um número inteiro",
            'alfanumerico' => "O campo {$campo} deve conter apenas letras e números",
            'alfabetico' => "O campo {$campo} deve conter apenas letras",
            'min' => "O campo {$campo} deve ter no mínimo {$parametro} caracteres",
            'max' => "O campo {$campo} deve ter no máximo {$parametro} caracteres",
            'em' => "O campo {$campo} contém um valor inválido",
            default => "O campo {$campo} é inválido"
        };
    }
}
