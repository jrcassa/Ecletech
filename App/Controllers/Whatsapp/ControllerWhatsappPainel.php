<?php

namespace App\Controllers\Whatsapp;

use App\Controllers\BaseController;

use App\Services\Whatsapp\ServiceWhatsapp;
use App\Models\Whatsapp\ModelWhatsappHistorico;
use App\Helpers\AuxiliarResposta;

/**
 * Controller para painel de gerenciamento WhatsApp
 */
class ControllerWhatsappPainel extends BaseController
{
    private ServiceWhatsapp $service;
    private ModelWhatsappHistorico $historicoModel;

    public function __construct()
    {
        $this->service = new ServiceWhatsapp();
        $this->historicoModel = new ModelWhatsappHistorico();
    }

    /**
     * Dashboard com estatísticas gerais
     */
    public function dashboard(): void
    {
        try {
            $stats = $this->service->obterEstatisticas();
            $this->sucesso($stats, 'Dashboard carregado');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Carrega histórico
     */
    public function historico(): void
    {
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null,
                'tipo_evento' => $_GET['tipo_evento'] ?? null
            ];

            $filtros = array_filter($filtros, fn($v) => $v !== null);

            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            $historico = $this->historicoModel->buscar($filtros, $limit, $offset);
            $total = $this->historicoModel->contar($filtros);

            $this->paginado($historico, $total, 1, $limit, 'Histórico carregado');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Processa fila manualmente
     */
    public function processar(): void
    {
        try {
            $limit = (int) ($_POST['limit'] ?? 10);
            $resultado = $this->service->processarFila($limit);

            $this->sucesso(
                $resultado,
                "Processadas: {$resultado['processadas']}, Sucesso: {$resultado['sucesso']}, Erro: {$resultado['erro']}"
            );

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }
}
