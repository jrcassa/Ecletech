<?php

namespace App\Helpers;

/**
 * Classe auxiliar para operações de rede
 */
class AuxiliarRede
{
    /**
     * Obtém o endereço IP real do cliente
     *
     * Verifica headers de proxy (X-Forwarded-For, X-Real-IP) e REMOTE_ADDR
     * Em caso de múltiplos IPs no X-Forwarded-For, retorna o primeiro (cliente real)
     *
     * @return string Endereço IP do cliente ou 'unknown' se não puder ser determinado
     */
    public static function obterIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
              $_SERVER['HTTP_X_REAL_IP'] ??
              $_SERVER['REMOTE_ADDR'] ??
              'unknown';

        // Se houver múltiplos IPs (proxy chain), pega o primeiro (cliente real)
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Valida se é um IP válido
        if ($ip !== 'unknown' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }

        return $ip;
    }

    /**
     * Obtém o User-Agent da requisição
     *
     * @return string User-Agent ou 'unknown' se não disponível
     */
    public static function obterUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Verifica se o IP pertence a uma rede/range específico
     *
     * @param string $ip IP a verificar
     * @param string $range Range em formato CIDR (ex: 192.168.1.0/24)
     * @return bool True se o IP pertence ao range
     */
    public static function ipPertenceAoRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        list($subnet, $mask) = explode('/', $range);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Verifica se o IP é privado/interno
     *
     * @param string $ip IP a verificar
     * @return bool True se o IP é privado
     */
    public static function ehIpPrivado(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Obtém o protocolo da requisição (http ou https)
     *
     * @return string 'http' ou 'https'
     */
    public static function obterProtocolo(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        if ($https && strtolower($https) !== 'off') {
            return 'https';
        }

        if ($forwardedProto && strtolower($forwardedProto) === 'https') {
            return 'https';
        }

        return 'http';
    }

    /**
     * Obtém a URL completa da requisição atual
     *
     * @return string URL completa
     */
    public static function obterUrlCompleta(): string
    {
        $protocolo = self::obterProtocolo();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return "{$protocolo}://{$host}{$uri}";
    }

    /**
     * Obtém o hostname do cliente (reverse DNS lookup)
     *
     * @param string|null $ip IP do cliente (se null, usa obterIp())
     * @return string Hostname ou o próprio IP se não puder ser resolvido
     */
    public static function obterHostname(?string $ip = null): string
    {
        $ip = $ip ?? self::obterIp();

        if ($ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        $hostname = gethostbyaddr($ip);

        // gethostbyaddr retorna o IP se não conseguir resolver
        return $hostname;
    }
}
