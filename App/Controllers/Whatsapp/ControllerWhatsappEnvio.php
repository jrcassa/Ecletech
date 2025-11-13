<?php

namespace App\Controllers\Whatsapp;

use App\Services\Whatsapp\ServiceWhatsapp;
use App\Models\Whatsapp\ModelWhatsappQueue;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar envio de mensagens WhatsApp
 */
class ControllerWhatsappEnvio
{
    private ServiceWhatsapp $service;
    private ModelWhatsappQueue $queueModel;

    public function __construct()
    {
        $this->service = new ServiceWhatsapp();
        $this->queueModel = new ModelWhatsappQueue();
    }

    /**
     * Envia mensagem WhatsApp (via fila ou direto)
     */
    public function enviar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'destinatario' => 'obrigatorio',
                'tipo' => 'obrigatorio|em:text,image,pdf,audio,video,document'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Valida modo_envio se fornecido
            if (isset($dados['modo_envio']) && !in_array($dados['modo_envio'], ['fila', 'direto'])) {
                AuxiliarResposta::erro('Modo de envio inválido. Use "fila" ou "direto"', 400);
                return;
            }

            // Valida conteúdo conforme tipo
            if ($dados['tipo'] === 'text' && empty($dados['mensagem'])) {
                AuxiliarResposta::erro('Mensagem é obrigatória para tipo texto', 400);
                return;
            }

            if ($dados['tipo'] !== 'text' && empty($dados['arquivo_url']) && empty($dados['arquivo_base64'])) {
                AuxiliarResposta::erro('URL ou base64 do arquivo é obrigatório', 400);
                return;
            }

            // Envia (modo_envio é passado automaticamente via $dados)
            $resultado = $this->service->enviarMensagem($dados);

            if ($resultado['sucesso']) {
                // Retorna informações diferentes conforme o modo
                $retorno = [
                    'modo' => $resultado['modo']
                ];

                if ($resultado['modo'] === 'fila') {
                    $retorno['queue_id'] = $resultado['queue_id'];
                } else {
                    $retorno['message_id'] = $resultado['message_id'];
                    $retorno['dados'] = $resultado['dados'];
                }

                AuxiliarResposta::sucesso($retorno, $resultado['mensagem']);
            } else {
                AuxiliarResposta::erro($resultado['erro'], 400);
            }

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Cancela mensagem pendente
     */
    public function cancelar(int $queueId): void
    {
        try {
            $mensagem = $this->queueModel->buscarPorId($queueId);

            if (!$mensagem) {
                AuxiliarResposta::naoEncontrado('Mensagem não encontrada');
                return;
            }

            if ($mensagem['status_code'] != 1) {
                AuxiliarResposta::erro('Só é possível cancelar mensagens pendentes', 400);
                return;
            }

            $this->queueModel->deletar($queueId);

            AuxiliarResposta::sucesso(null, 'Mensagem cancelada com sucesso');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Lista mensagens da fila
     */
    public function listar(): void
    {
        try {
            $status = $_GET['status'] ?? null;
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            if ($status !== null) {
                $mensagens = $this->queueModel->buscarPorStatus((int) $status, $limit, $offset);
                $total = $this->queueModel->contarPorStatus((int) $status);
            } else {
                $mensagens = $this->queueModel->buscarPendentes($limit);
                $total = $this->queueModel->contarPendentes();
            }

            AuxiliarResposta::paginado($mensagens, $total, 1, $limit, 'Fila carregada com sucesso');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca mensagem por ID
     */
    public function buscar(int $queueId): void
    {
        try {
            $mensagem = $this->queueModel->buscarPorId($queueId);

            if (!$mensagem) {
                AuxiliarResposta::naoEncontrado('Mensagem não encontrada');
                return;
            }

            AuxiliarResposta::sucesso($mensagem, 'Mensagem encontrada');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém estatísticas da fila
     */
    public function estatisticas(): void
    {
        try {
            $stats = $this->service->obterEstatisticas();
            AuxiliarResposta::sucesso($stats, 'Estatísticas obtidas com sucesso');

        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }
}
