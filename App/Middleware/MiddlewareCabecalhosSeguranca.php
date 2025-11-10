<?php

namespace App\Middleware;

/**
 * Middleware para adicionar cabeçalhos de segurança
 */
class MiddlewareCabecalhosSeguranca
{
    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        // Previne sniffing de MIME type
        header('X-Content-Type-Options: nosniff');

        // Previne clickjacking
        header('X-Frame-Options: DENY');

        // Proteção XSS do navegador
        header('X-XSS-Protection: 1; mode=block');

        // Controle de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Política de permissões
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");

        // HSTS - força HTTPS (descomentar em produção com HTTPS)
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

        // Remove informações do servidor
        header_remove('X-Powered-By');
        header_remove('Server');

        return true;
    }
}
