<?php

namespace App\Services\FrotaAbastecimento;

use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
// ModelFrotaAbastecimentoNotificacao não é mais utilizado - usando apenas whatsapp_queue
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoAlerta;
use App\Models\Colaborador\ModelColaborador;
use App\Models\S3\ModelS3Arquivo;
use App\Services\Whatsapp\ServiceWhatsapp;
use App\Core\BancoDados;

/**
 * Service para enviar notificações via WhatsApp
 * Usa apenas whatsapp_queue - tabela frotas_abastecimentos_notificacoes não é mais utilizada
 */
class ServiceFrotaAbastecimentoNotificacao
{
    private ModelFrotaAbastecimento $model;
    // Removido: ModelFrotaAbastecimentoNotificacao - não usado mais
    private ModelFrotaAbastecimentoMetrica $modelMetrica;
    private ModelFrotaAbastecimentoAlerta $modelAlerta;
    private ModelColaborador $modelColaborador;
    private ModelS3Arquivo $modelS3Arquivo;
    private ServiceWhatsapp $serviceWhatsapp;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimento();
        // Removido: ModelFrotaAbastecimentoNotificacao
        $this->modelMetrica = new ModelFrotaAbastecimentoMetrica();
        $this->modelAlerta = new ModelFrotaAbastecimentoAlerta();
        $this->modelColaborador = new ModelColaborador();
        $this->modelS3Arquivo = new ModelS3Arquivo();
        $this->serviceWhatsapp = new ServiceWhatsapp();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Envia notificação ao motorista quando ordem é criada
     */
    public function enviarNotificacaoOrdemCriada(int $abastecimento_id): void
    {
        $abastecimento = $this->model->buscarComDetalhes($abastecimento_id);
        if (!$abastecimento) {
            return;
        }

        $motorista = $this->modelColaborador->buscarPorId($abastecimento['colaborador_id']);
        if (!$motorista || !$motorista['celular']) {
            return;
        }

        // Montar mensagem
        $mensagem = $this->montarMensagemOrdemCriada($abastecimento);

        // Enviar WhatsApp via fila (whatsapp_queue)
        try {
            $resultado = $this->serviceWhatsapp->enviarMensagem([
                'destinatario' => [
                    'tipo' => 'colaborador',
                    'id' => $abastecimento['colaborador_id']
                ],
                'tipo' => 'text',
                'mensagem' => $mensagem,
                'prioridade' => 'alta',
                'metadata' => [
                    'modulo' => 'frota_abastecimento',
                    'tipo_notificacao' => 'ordem_criada',
                    'abastecimento_id' => $abastecimento_id
                ]
            ]);

            if ($resultado['sucesso']) {
                $this->model->marcarNotificacaoMotoristaEnviada($abastecimento_id);
            }
        } catch (\Exception $e) {
            // Log do erro (opcional)
            error_log("Erro ao enviar notificação ordem criada: " . $e->getMessage());
        }
    }

    /**
     * Envia notificação aos admins quando motorista finaliza (via ACL)
     */
    public function enviarNotificacaoAbastecimentoFinalizado(int $abastecimento_id): void
    {
        $abastecimento = $this->model->buscarComDetalhes($abastecimento_id);
        if (!$abastecimento) {
            return;
        }

        // Buscar métricas e alertas
        $metricas = $this->modelMetrica->buscarPorAbastecimentoId($abastecimento_id);
        $alertas = $this->modelAlerta->buscarPorAbastecimento($abastecimento_id);

        // Buscar destinatários via ACL
        $destinatarios = $this->obterDestinatariosNotificacao();

        foreach ($destinatarios as $destinatario) {
            // Montar mensagem
            $mensagem = $this->montarMensagemAbastecimentoFinalizado($abastecimento, $metricas, $alertas);

            // Enviar WhatsApp via fila (whatsapp_queue)
            try {
                $this->serviceWhatsapp->enviarMensagem([
                    'destinatario' => [
                        'tipo' => 'colaborador',
                        'id' => $destinatario['id']
                    ],
                    'tipo' => 'text',
                    'mensagem' => $mensagem,
                    'prioridade' => 'normal',
                    'metadata' => [
                        'modulo' => 'frota_abastecimento',
                        'tipo_notificacao' => 'abastecimento_finalizado',
                        'abastecimento_id' => $abastecimento_id
                    ]
                ]);

                // Buscar e enviar foto do comprovante se existir
                $comprovantes = $this->modelS3Arquivo->buscarPorEntidade('frota_abastecimento', $abastecimento_id);

                foreach ($comprovantes as $comprovante) {
                    // Filtra apenas imagens (categoria comprovante ou tipo MIME de imagem)
                    if ($comprovante['categoria'] === 'comprovante' ||
                        (isset($comprovante['tipo_mime']) && strpos($comprovante['tipo_mime'], 'image/') === 0)) {

                        $urlArquivo = $comprovante['url_publica'] ?? $comprovante['caminho_s3'];

                        $this->serviceWhatsapp->enviarMensagem([
                            'destinatario' => [
                                'tipo' => 'colaborador',
                                'id' => $destinatario['id']
                            ],
                            'tipo' => 'image',
                            'arquivo_url' => $urlArquivo,
                            'mensagem' => '📷 Comprovante de Abastecimento',
                            'prioridade' => 'normal',
                            'metadata' => [
                                'modulo' => 'frota_abastecimento',
                                'tipo_notificacao' => 'abastecimento_finalizado_foto',
                                'abastecimento_id' => $abastecimento_id,
                                's3_arquivo_id' => $comprovante['id']
                            ]
                        ]);

                        // Envia apenas o primeiro comprovante
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Log do erro (opcional)
                error_log("Erro ao enviar notificação abastecimento finalizado: " . $e->getMessage());
            }
        }

        $this->model->marcarNotificacaoAdminEnviada($abastecimento_id);
    }

    /**
     * Envia notificação ao motorista quando ordem é cancelada
     */
    public function enviarNotificacaoOrdemCancelada(int $abastecimento_id): void
    {
        $abastecimento = $this->model->buscarComDetalhes($abastecimento_id);
        if (!$abastecimento) {
            return;
        }

        $motorista = $this->modelColaborador->buscarPorId($abastecimento['colaborador_id']);
        if (!$motorista || !$motorista['celular']) {
            return;
        }

        // Montar mensagem
        $mensagem = $this->montarMensagemOrdemCancelada($abastecimento);

        // Enviar WhatsApp via fila (whatsapp_queue)
        try {
            $this->serviceWhatsapp->enviarMensagem([
                'destinatario' => [
                    'tipo' => 'colaborador',
                    'id' => $abastecimento['colaborador_id']
                ],
                'tipo' => 'text',
                'mensagem' => $mensagem,
                'prioridade' => 'alta',
                'metadata' => [
                    'modulo' => 'frota_abastecimento',
                    'tipo_notificacao' => 'ordem_cancelada',
                    'abastecimento_id' => $abastecimento_id
                ]
            ]);
        } catch (\Exception $e) {
            // Log do erro (opcional)
            error_log("Erro ao enviar notificação ordem cancelada: " . $e->getMessage());
        }
    }

    /**
     * Busca colaboradores com permissão ACL para receber notificações
     */
    private function obterDestinatariosNotificacao(): array
    {
        $sql = "
            SELECT DISTINCT c.id, c.nome, c.celular
            FROM colaboradores c
            INNER JOIN colaborador_roles r ON c.nivel_id = r.id
            INNER JOIN colaborador_role_permissions crp ON r.id = crp.role_id
            INNER JOIN colaborador_permissions p ON crp.permission_id = p.id
            WHERE p.codigo = 'frota_abastecimento.receber_notificacao'
            AND c.ativo = 1
            AND c.deletado_em IS NULL
            AND c.celular IS NOT NULL
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Monta mensagem de ordem criada
     */
    private function montarMensagemOrdemCriada(array $abastecimento): string
    {
        $mensagem = "🚗 *Nova Ordem de Abastecimento*\n\n";
        $mensagem .= "Olá *{$abastecimento['motorista_nome']}*,\n\n";
        $mensagem .= "Você tem uma nova ordem de abastecimento:\n\n";

        // Usar nome da frota, ou modelo/marca como fallback
        $veiculoNome = !empty($abastecimento['frota_nome'])
            ? $abastecimento['frota_nome']
            : trim(($abastecimento['frota_marca'] ?? '') . ' ' . ($abastecimento['frota_modelo'] ?? ''));

        $mensagem .= "📌 *Veículo:* {$abastecimento['frota_placa']}";
        if (!empty($veiculoNome)) {
            $mensagem .= " - {$veiculoNome}";
        }
        $mensagem .= "\n";

        if ($abastecimento['data_limite']) {
            $dataLimite = date('d/m/Y', strtotime($abastecimento['data_limite']));
            $mensagem .= "📅 *Data Limite:* {$dataLimite}\n";
        }

        if ($abastecimento['observacao_admin']) {
            $mensagem .= "💬 *Observação:* {$abastecimento['observacao_admin']}\n";
        }

        $mensagem .= "\nAcesse o sistema para realizar o abastecimento.\n\n";
        $mensagem .= "---\n";
        $mensagem .= "_Sistema de Frota Ecletech_";

        return $mensagem;
    }

    /**
     * Monta mensagem de abastecimento finalizado
     */
    private function montarMensagemAbastecimentoFinalizado(array $abastecimento, ?array $metricas, array $alertas): string
    {
        $mensagem = "✅ *Abastecimento Realizado*\n\n";
        $mensagem .= "👤 *Motorista:* {$abastecimento['motorista_nome']}\n";

        // Usar nome da frota, ou modelo/marca como fallback
        $veiculoNome = !empty($abastecimento['frota_nome'])
            ? $abastecimento['frota_nome']
            : trim(($abastecimento['frota_marca'] ?? '') . ' ' . ($abastecimento['frota_modelo'] ?? ''));

        $mensagem .= "🚗 *Veículo:* {$abastecimento['frota_placa']}";
        if (!empty($veiculoNome)) {
            $mensagem .= " - {$veiculoNome}";
        }
        $mensagem .= "\n\n";

        $mensagem .= "📍 *Dados do Abastecimento:*\n";
        $mensagem .= "• KM: " . number_format($abastecimento['km'], 2, ',', '.') . "\n";
        $mensagem .= "• Litros: " . number_format($abastecimento['litros'], 3, ',', '.') . " L\n";
        $mensagem .= "• Combustível: " . ucfirst($abastecimento['combustivel']) . "\n";
        $mensagem .= "• Valor: R$ " . number_format($abastecimento['valor'], 2, ',', '.') . "\n";

        $dataAbastecimento = date('d/m/Y H:i', strtotime($abastecimento['data_abastecimento']));
        $mensagem .= "• Data/Hora: {$dataAbastecimento}\n";

        if ($metricas) {
            $mensagem .= "\n💰 *Métricas:*\n";

            if ($metricas['consumo_km_por_litro']) {
                $mensagem .= "• Consumo: " . number_format($metricas['consumo_km_por_litro'], 2, ',', '.') . " km/l\n";
            }

            if ($metricas['custo_por_km']) {
                $mensagem .= "• Custo/km: R$ " . number_format($metricas['custo_por_km'], 2, ',', '.') . "\n";
            }

            if ($metricas['custo_por_litro']) {
                $mensagem .= "• Custo/litro: R$ " . number_format($metricas['custo_por_litro'], 2, ',', '.') . "\n";
            }
        }

        if (!empty($alertas)) {
            $mensagem .= "\n⚠️ *Alertas:* " . count($alertas) . " detectado(s)\n";
            foreach ($alertas as $alerta) {
                $emoji = $alerta['severidade'] === 'critica' ? '🔴' : ($alerta['severidade'] === 'alta' ? '🟠' : '🟡');
                $mensagem .= "{$emoji} {$alerta['titulo']}\n";
            }
        }

        $mensagem .= "\n---\n";
        $mensagem .= "_Sistema de Frota Ecletech_";

        return $mensagem;
    }

    /**
     * Monta mensagem de ordem cancelada
     */
    private function montarMensagemOrdemCancelada(array $abastecimento): string
    {
        $mensagem = "❌ *Ordem de Abastecimento Cancelada*\n\n";
        $mensagem .= "Olá *{$abastecimento['motorista_nome']}*,\n\n";
        $mensagem .= "A ordem de abastecimento do veículo *{$abastecimento['frota_placa']}* foi cancelada.\n\n";

        if ($abastecimento['observacao_admin']) {
            $mensagem .= "💬 *Motivo:* {$abastecimento['observacao_admin']}\n\n";
        }

        $mensagem .= "---\n";
        $mensagem .= "_Sistema de Frota Ecletech_";

        return $mensagem;
    }
}
