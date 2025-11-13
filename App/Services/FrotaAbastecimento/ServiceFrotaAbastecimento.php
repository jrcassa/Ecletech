<?php

namespace App\Services\FrotaAbastecimento;

use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoAlerta;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoNotificacao;
use App\Models\S3\ModelS3Arquivo;
use App\Services\S3\ServiceS3;
use App\Services\Whatsapp\ServiceWhatsapp;
use App\Helpers\AuxiliarS3;
use Exception;

/**
 * Service principal para orquestrar abastecimentos
 */
class ServiceFrotaAbastecimento
{
    private ModelFrotaAbastecimento $model;
    private ModelFrotaAbastecimentoMetrica $modelMetrica;
    private ModelFrotaAbastecimentoAlerta $modelAlerta;
    private ModelFrotaAbastecimentoNotificacao $modelNotificacao;
    private ModelS3Arquivo $modelS3;
    private ServiceS3 $serviceS3;
    private ServiceWhatsapp $serviceWhatsapp;
    private ServiceFrotaAbastecimentoMetricas $serviceMetricas;
    private ServiceFrotaAbastecimentoAlertas $serviceAlertas;
    private ServiceFrotaAbastecimentoNotificacao $serviceNotificacao;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimento();
        $this->modelMetrica = new ModelFrotaAbastecimentoMetrica();
        $this->modelAlerta = new ModelFrotaAbastecimentoAlerta();
        $this->modelNotificacao = new ModelFrotaAbastecimentoNotificacao();
        $this->modelS3 = new ModelS3Arquivo();
        $this->serviceS3 = new ServiceS3();
        $this->serviceWhatsapp = new ServiceWhatsapp();
        $this->serviceMetricas = new ServiceFrotaAbastecimentoMetricas();
        $this->serviceAlertas = new ServiceFrotaAbastecimentoAlertas();
        $this->serviceNotificacao = new ServiceFrotaAbastecimentoNotificacao();
    }

    /**
     * Registra ordem e envia WhatsApp para motorista
     */
    public function registrarOrdem(array $dados): int
    {
        // Cria ordem
        $id = $this->model->criar($dados);

        // Envia notificação para motorista
        try {
            $this->serviceNotificacao->enviarNotificacaoOrdemCriada($id);
        } catch (Exception $e) {
            // Log do erro, mas não bloqueia criação
            error_log("Erro ao enviar notificação de ordem criada: " . $e->getMessage());
        }

        return $id;
    }

    /**
     * Finaliza abastecimento: atualiza, calcula métricas, detecta alertas, envia WhatsApp
     */
    public function finalizarAbastecimento(int $id, array $dados): bool
    {
        // 1. Atualiza ordem
        $this->model->marcarComoFinalizado($id, $dados);

        // 2. Calcula métricas
        try {
            $this->serviceMetricas->calcularMetricasAbastecimento($id);
        } catch (Exception $e) {
            error_log("Erro ao calcular métricas: " . $e->getMessage());
        }

        // 3. Detecta alertas
        try {
            $this->serviceAlertas->detectarAlertasAbastecimento($id);
        } catch (Exception $e) {
            error_log("Erro ao detectar alertas: " . $e->getMessage());
        }

        // 4. Envia notificação para admins
        try {
            $this->serviceNotificacao->enviarNotificacaoAbastecimentoFinalizado($id);
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação de finalização: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Cancela ordem e envia WhatsApp para motorista
     */
    public function cancelarOrdem(int $id, ?string $observacao = null): bool
    {
        // Cancela ordem
        $this->model->marcarComoCancelado($id, $observacao);

        // Envia notificação para motorista
        try {
            $this->serviceNotificacao->enviarNotificacaoOrdemCancelada($id);
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação de cancelamento: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Anexa comprovante via S3
     */
    public function anexarComprovante(int $abastecimento_id, string $arquivo_base64): array
    {
        // Validar se é base64 válido
        if (!AuxiliarS3::validarBase64($arquivo_base64)) {
            throw new Exception('Arquivo base64 inválido');
        }

        // Remove prefixo data: se existir
        $arquivo_base64 = preg_replace('#^data:[\w/]+;base64,#i', '', $arquivo_base64);

        // Decodifica
        $conteudo = base64_decode($arquivo_base64);

        // Detecta tipo MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tipoMime = $finfo->buffer($conteudo);

        // Valida tipos permitidos
        $tiposPermitidos = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($tipoMime, $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Permitidos: PDF, JPEG, PNG');
        }

        // Valida tamanho (máx 5MB)
        if (strlen($conteudo) > 5 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo: 5MB');
        }

        // Determina extensão
        $extensao = match($tipoMime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin'
        };

        // Preparar dados para upload
        $nomeOriginal = 'comprovante_' . $abastecimento_id . '.' . $extensao;

        // Upload para S3
        $resultado = $this->serviceS3->upload([
            'nome_original' => $nomeOriginal,
            'conteudo' => $conteudo,
            'tipo_mime' => $tipoMime,
            'caminho_s3' => 'frota/abastecimentos/' . date('Y/m'),
            'acl' => 'private',
            'metadata' => [
                'abastecimento_id' => $abastecimento_id,
                'tipo' => 'comprovante_pagamento'
            ],
            'entidade_tipo' => 'frota_abastecimento',
            'entidade_id' => $abastecimento_id,
            'categoria' => 'comprovante_pagamento',
            'criado_por' => $_SESSION['usuario_id'] ?? null
        ]);

        return $resultado;
    }

    /**
     * Obtém comprovantes de um abastecimento
     */
    public function obterComprovantes(int $abastecimento_id): array
    {
        $arquivos = $this->modelS3->buscarPorEntidade('frota_abastecimento', $abastecimento_id);

        // Filtrar apenas comprovantes
        $comprovantes = array_filter($arquivos, function($arquivo) {
            return ($arquivo['categoria'] ?? '') === 'comprovante_pagamento';
        });

        // Gerar URLs temporárias
        foreach ($comprovantes as &$arquivo) {
            $resultado = $this->serviceS3->gerarUrlAssinada($arquivo['id'], 3600);
            $arquivo['url_temporaria'] = $resultado['url'] ?? null;
        }

        return array_values($comprovantes);
    }
}
