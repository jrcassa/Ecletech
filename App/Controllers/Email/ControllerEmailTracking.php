<?php

namespace App\Controllers\Email;

use App\Controllers\BaseController;
use App\Models\Email\ModelEmailHistorico;
use App\Services\Email\ServiceEmailEntidade;

/**
 * Controller para rastreamento de abertura e cliques
 */
class ControllerEmailTracking extends BaseController
{
    private ModelEmailHistorico $historico;
    private ServiceEmailEntidade $entidadeService;

    public function __construct()
    {
        $this->historico = new ModelEmailHistorico();
        $this->entidadeService = new ServiceEmailEntidade();
    }

    /**
     * GET /email/track/open/{tracking_code}
     * Rastreia abertura de email (pixel transparente)
     */
    public function rastrearAbertura(string $tracking_code): void
    {
        if ($tracking_code) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Registra abertura
            $registrado = $this->historico->registrarAbertura($tracking_code, $ip, $userAgent);

            // Se encontrou o email, atualiza também na entidade
            if ($registrado) {
                $email = $this->historico->buscarUnicoPorTrackingCode($tracking_code);
                if ($email && $email['tipo_entidade'] && $email['entidade_id']) {
                    $this->entidadeService->registrarAbertura($email['tipo_entidade'], $email['entidade_id']);
                }
            }
        }

        // Retorna pixel transparente 1x1
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        // Pixel PNG transparente 1x1 em base64
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        echo $pixel;
        exit;
    }

    /**
     * GET /email/track/click/{tracking_code}
     * Rastreia clique em link
     */
    public function rastrearClique(string $tracking_code): void
    {
        $url = $this->obterParametro('url');

        if ($tracking_code) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Registra clique
            $registrado = $this->historico->registrarClique($tracking_code, $ip, $userAgent);

            // Se encontrou o email, atualiza também na entidade
            if ($registrado) {
                $email = $this->historico->buscarUnicoPorTrackingCode($tracking_code);
                if ($email && $email['tipo_entidade'] && $email['entidade_id']) {
                    $this->entidadeService->registrarClique($email['tipo_entidade'], $email['entidade_id']);
                }
            }
        }

        // Redireciona para URL original
        if ($url) {
            header('Location: ' . urldecode($url));
            exit;
        }

        $this->erro('URL de destino não encontrada', 404);
    }

    /**
     * GET /email/track/stats/{tracking_code}
     * Obtém estatísticas de um email específico
     */
    public function estatisticas(string $tracking_code): void
    {
        try {
            $email = $this->historico->buscarUnicoPorTrackingCode($tracking_code);

            if (!$email) {
                $this->naoEncontrado('Email não encontrado');
                return;
            }

            $this->sucesso([
                'tracking_code' => $tracking_code,
                'destinatario' => $email['destinatario_email'],
                'assunto' => $email['assunto'],
                'status' => $email['status'],
                'status_code' => $email['status_code'],
                'data_enviado' => $email['data_enviado'],
                'data_aberto' => $email['data_aberto'],
                'data_clicado' => $email['data_clicado'],
                'ip_abertura' => $email['ip_abertura'],
                'user_agent' => $email['user_agent']
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter estatísticas');
        }
    }
}
