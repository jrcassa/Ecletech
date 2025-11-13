<?php

namespace App\Controllers\Email;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Email\ModelEmailHistorico;
use App\Services\Email\ServiceEmailEntidade;

/**
 * Controller para rastreamento de abertura e cliques
 */
class ControllerEmailTracking extends Controller
{
    private ModelEmailHistorico $historico;
    private ServiceEmailEntidade $entidadeService;

    public function __construct()
    {
        parent::__construct();
        $this->historico = new ModelEmailHistorico();
        $this->entidadeService = new ServiceEmailEntidade();
    }

    /**
     * GET /email/track/open/{tracking_code}
     * Rastreia abertura de email (pixel transparente)
     */
    public function rastrearAbertura(Request $request, array $params): Response
    {
        $trackingCode = $params['tracking_code'] ?? null;

        if ($trackingCode) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Registra abertura
            $registrado = $this->historico->registrarAbertura($trackingCode, $ip, $userAgent);

            // Se encontrou o email, atualiza também na entidade
            if ($registrado) {
                $email = $this->historico->buscarUnicoPorTrackingCode($trackingCode);
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
    public function rastrearClique(Request $request, array $params): Response
    {
        $trackingCode = $params['tracking_code'] ?? null;
        $url = $request->get('url');

        if ($trackingCode) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Registra clique
            $registrado = $this->historico->registrarClique($trackingCode, $ip, $userAgent);

            // Se encontrou o email, atualiza também na entidade
            if ($registrado) {
                $email = $this->historico->buscarUnicoPorTrackingCode($trackingCode);
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

        return $this->erro('URL de destino não encontrada', 404);
    }

    /**
     * GET /email/track/stats/{tracking_code}
     * Obtém estatísticas de um email específico
     */
    public function estatisticas(Request $request, array $params): Response
    {
        // Valida permissão
        if (!$this->acl->temPermissao('email.acessar')) {
            return $this->erro('Sem permissão para acessar estatísticas', 403);
        }

        $trackingCode = $params['tracking_code'];

        $email = $this->historico->buscarUnicoPorTrackingCode($trackingCode);

        if (!$email) {
            return $this->erro('Email não encontrado', 404);
        }

        return $this->sucesso([
            'tracking_code' => $trackingCode,
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
    }
}
