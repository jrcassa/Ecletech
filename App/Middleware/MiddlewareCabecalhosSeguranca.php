<?php

namespace App\Middleware;

use App\Core\Configuracao;

/**
 * Middleware para adicionar cabeçalhos de segurança
 * Aplica headers de segurança recomendados pelo OWASP
 */
class MiddlewareCabecalhosSeguranca
{
    private Configuracao $config;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
    }

    /**
     * Processa a requisição aplicando headers de segurança
     */
    public function handle(): bool
    {
        // Previne sniffing de MIME type
        // Força o navegador a respeitar o Content-Type declarado
        header('X-Content-Type-Options: nosniff');

        // Previne clickjacking
        // Impede que a página seja carregada em iframe/frame
        header('X-Frame-Options: DENY');

        // Proteção XSS do navegador (legacy, mantido para compatibilidade)
        header('X-XSS-Protection: 1; mode=block');

        // Controle de referrer - protege dados sensíveis em URLs
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Política de permissões - desabilita APIs sensíveis
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

        // Content Security Policy - previne XSS e injeção de código
        $csp = $this->obterCsp();
        header("Content-Security-Policy: {$csp}");

        // HSTS - força HTTPS
        if ($this->ehHttps()) {
            $hstsMaxAge = $this->config->obter('seguranca.hsts_max_age', 31536000);
            header("Strict-Transport-Security: max-age={$hstsMaxAge}; includeSubDomains; preload");
        }

        // Remove informações do servidor que podem expor vulnerabilidades
        header_remove('X-Powered-By');
        header_remove('Server');

        // Previne caching de dados sensíveis
        if ($this->rotaRequerCache()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        return true;
    }

    /**
     * Obtém a política CSP configurada
     */
    private function obterCsp(): string
    {
        $cspConfig = $this->config->obter('seguranca.csp', []);

        if (!empty($cspConfig) && is_array($cspConfig)) {
            $cspParts = [];
            foreach ($cspConfig as $diretiva => $valor) {
                $cspParts[] = "{$diretiva} {$valor}";
            }
            return implode('; ', $cspParts);
        }

        // CSP padrão restritivo
        return "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' data:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";
    }

    /**
     * Verifica se a requisição é HTTPS
     */
    private function ehHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return ($https && strtolower($https) !== 'off') ||
               ($forwardedProto && strtolower($forwardedProto) === 'https');
    }

    /**
     * Verifica se a rota atual requer cache desabilitado
     */
    private function rotaRequerCache(): bool
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Desabilita cache para métodos que modificam dados
        if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        // Desabilita cache para rotas sensíveis
        $rotasSensiveis = [
            '/auth/',
            '/logout',
            '/admin/',
            '/api/user',
            '/api/colaborador'
        ];

        foreach ($rotasSensiveis as $rotaSensivel) {
            if (str_contains($path, $rotaSensivel)) {
                return true;
            }
        }

        return false;
    }
}
