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
     * Valida placa de veículo brasileira (Mercosul ou antiga)
     * Formato Mercosul: ABC1D23
     * Formato Antigo: ABC-1234 ou ABC1234
     */
    public static function placa(string $placa): bool
    {
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', $placa));

        // Formato Mercosul: ABC1D23 (3 letras + 1 número + 1 letra + 2 números)
        if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
            return true;
        }

        // Formato Antigo: ABC1234 (3 letras + 4 números)
        if (preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
            return true;
        }

        return false;
    }

    /**
     * Valida chassi (VIN - Vehicle Identification Number)
     * Deve ter exatamente 17 caracteres alfanuméricos
     */
    public static function chassi(string $chassi): bool
    {
        $chassi = strtoupper(preg_replace('/[^A-Z0-9]/', '', $chassi));

        // Deve ter 17 caracteres
        if (strlen($chassi) !== 17) {
            return false;
        }

        // Não pode conter as letras I, O ou Q (podem ser confundidas com números)
        if (preg_match('/[IOQ]/', $chassi)) {
            return false;
        }

        // Deve conter apenas letras e números
        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassi) === 1;
    }

    /**
     * Valida RENAVAM (Registro Nacional de Veículos Automotores)
     * Deve ter exatamente 11 dígitos
     */
    public static function renavam(string $renavam): bool
    {
        $renavam = preg_replace('/[^0-9]/', '', $renavam);

        // Deve ter exatamente 11 dígitos
        if (strlen($renavam) !== 11) {
            return false;
        }

        // Validação do dígito verificador
        $sequencia = '3298765432';
        $soma = 0;

        for ($i = 0; $i < 10; $i++) {
            $soma += intval($renavam[$i]) * intval($sequencia[$i]);
        }

        $digito = $soma % 11;
        $digito = $digito === 0 || $digito === 1 ? 0 : 11 - $digito;

        return intval($renavam[10]) === $digito;
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
     * Obtém valor de um array usando notação de ponto
     * Ex: obterValorAninhado($dados, 'credenciais.access_token')
     */
    private static function obterValorAninhado(array $dados, string $chave): mixed
    {
        // Se a chave não contém ponto, acessa diretamente
        if (strpos($chave, '.') === false) {
            return $dados[$chave] ?? null;
        }

        // Divide a chave por pontos
        $chaves = explode('.', $chave);
        $valor = $dados;

        // Navega pelo array aninhado
        foreach ($chaves as $parte) {
            if (!is_array($valor) || !isset($valor[$parte])) {
                return null;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }

    /**
     * Valida múltiplas regras
     */
    public static function validar(array $dados, array $regras): array
    {
        $erros = [];

        foreach ($regras as $campo => $regrasString) {
            $regrasList = explode('|', $regrasString);
            $valor = self::obterValorAninhado($dados, $campo);

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
                    'placa' => self::placa($valor ?? ''),
                    'chassi' => self::chassi($valor ?? ''),
                    'renavam' => self::renavam($valor ?? ''),
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
            'placa' => "O campo {$campo} deve ser uma placa válida (formato Mercosul ou antigo)",
            'chassi' => "O campo {$campo} deve ser um chassi válido (17 caracteres)",
            'renavam' => "O campo {$campo} deve ser um RENAVAM válido (11 dígitos)",
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

    /**
     * Valida e sanitiza parâmetros de ORDER BY contra SQL Injection
     *
     * @param string $campo Campo de ordenação fornecido pelo usuário
     * @param string $direcao Direção de ordenação fornecida pelo usuário
     * @param array $camposPermitidos Lista de campos permitidos para ordenação
     * @param string $campoDefault Campo padrão caso o fornecido seja inválido
     * @return array Array com ['campo' => campo_validado, 'direcao' => direcao_validada]
     */
    public static function validarOrdenacao(
        string $campo,
        string $direcao,
        array $camposPermitidos,
        string $campoDefault = 'id'
    ): array {
        // Valida o campo contra a whitelist
        $campoValidado = in_array($campo, $camposPermitidos, true) ? $campo : $campoDefault;

        // Valida a direção (apenas ASC ou DESC)
        $direcaoUpper = strtoupper(trim($direcao));
        $direcaoValidada = in_array($direcaoUpper, ['ASC', 'DESC'], true) ? $direcaoUpper : 'ASC';

        return [
            'campo' => $campoValidado,
            'direcao' => $direcaoValidada
        ];
    }
}
