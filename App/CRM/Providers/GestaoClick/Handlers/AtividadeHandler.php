<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Atividade
 * Ecletech <-> GestãoClick
 */
class AtividadeHandler
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Transforma dados do Ecletech para formato GestaoClick
     */
    public function transformarParaExterno(array $atividade): array
    {
        $dados = [
            'subject' => $atividade['assunto'] ?? $atividade['titulo'] ?? '',
            'type' => $this->mapearTipo($atividade['tipo'] ?? 'tarefa'),
            'description' => $atividade['descricao'] ?? null,
        ];

        // Data de vencimento
        if (!empty($atividade['data_vencimento'])) {
            $dados['due_date'] = $atividade['data_vencimento'];
        }

        // Data de conclusão
        if (!empty($atividade['data_conclusao'])) {
            $dados['done_date'] = $atividade['data_conclusao'];
        }

        // Status (concluída ou não)
        if (isset($atividade['concluida'])) {
            $dados['done'] = (bool) $atividade['concluida'];
        } elseif (isset($atividade['status'])) {
            $dados['done'] = ($atividade['status'] === 'concluida');
        }

        // Relacionamentos
        if (!empty($atividade['external_id_cliente'])) {
            $dados['customer_id'] = $atividade['external_id_cliente'];
        }

        if (!empty($atividade['external_id_venda'])) {
            $dados['deal_id'] = $atividade['external_id_venda'];
        }

        // Responsável
        if (!empty($atividade['responsavel'])) {
            $dados['assigned_to'] = $atividade['responsavel'];
        }

        // Prioridade
        if (!empty($atividade['prioridade'])) {
            $dados['priority'] = $this->mapearPrioridade($atividade['prioridade']);
        }

        // Duração (em minutos)
        if (!empty($atividade['duracao'])) {
            $dados['duration'] = (int) $atividade['duracao'];
        }

        return $dados;
    }

    /**
     * Transforma dados do GestaoClick para formato Ecletech
     */
    public function transformarParaInterno(array $atividadeCrm): array
    {
        $dados = [
            'external_id' => (string) $atividadeCrm['id'],
            'assunto' => $atividadeCrm['subject'] ?? '',
            'titulo' => $atividadeCrm['subject'] ?? '',
            'tipo' => $this->mapearTipoInterno($atividadeCrm['type'] ?? 'task'),
            'descricao' => $atividadeCrm['description'] ?? null,
        ];

        // Data de vencimento
        if (!empty($atividadeCrm['due_date'])) {
            $dados['data_vencimento'] = $atividadeCrm['due_date'];
        }

        // Data de conclusão
        if (!empty($atividadeCrm['done_date'])) {
            $dados['data_conclusao'] = $atividadeCrm['done_date'];
        }

        // Status
        if (isset($atividadeCrm['done'])) {
            $dados['concluida'] = (int) $atividadeCrm['done'];
            $dados['status'] = $atividadeCrm['done'] ? 'concluida' : 'pendente';
        }

        // Responsável
        if (!empty($atividadeCrm['assigned_to'])) {
            $dados['responsavel'] = $atividadeCrm['assigned_to'];
        }

        // Prioridade
        if (!empty($atividadeCrm['priority'])) {
            $dados['prioridade'] = $this->mapearPrioridadeInterna($atividadeCrm['priority']);
        }

        // Duração
        if (!empty($atividadeCrm['duration'])) {
            $dados['duracao'] = (int) $atividadeCrm['duration'];
        }

        // Relacionamentos
        if (!empty($atividadeCrm['customer_id'])) {
            $dados['external_id_cliente'] = (string) $atividadeCrm['customer_id'];
        }

        if (!empty($atividadeCrm['deal_id'])) {
            $dados['external_id_venda'] = (string) $atividadeCrm['deal_id'];
        }

        return $dados;
    }

    /**
     * Mapeia tipo de atividade Ecletech -> GestaoClick
     */
    private function mapearTipo(string $tipo): string
    {
        $mapa = [
            'tarefa' => 'task',
            'ligacao' => 'call',
            'email' => 'email',
            'reuniao' => 'meeting',
            'visita' => 'visit'
        ];

        return $mapa[$tipo] ?? 'task';
    }

    /**
     * Mapeia tipo de atividade GestaoClick -> Ecletech
     */
    private function mapearTipoInterno(string $tipo): string
    {
        $mapa = [
            'task' => 'tarefa',
            'call' => 'ligacao',
            'email' => 'email',
            'meeting' => 'reuniao',
            'visit' => 'visita'
        ];

        return $mapa[$tipo] ?? 'tarefa';
    }

    /**
     * Mapeia prioridade Ecletech -> GestaoClick
     */
    private function mapearPrioridade(string $prioridade): string
    {
        $mapa = [
            'baixa' => 'low',
            'media' => 'medium',
            'alta' => 'high',
            'urgente' => 'urgent'
        ];

        return $mapa[$prioridade] ?? 'medium';
    }

    /**
     * Mapeia prioridade GestaoClick -> Ecletech
     */
    private function mapearPrioridadeInterna(string $prioridade): string
    {
        $mapa = [
            'low' => 'baixa',
            'medium' => 'media',
            'high' => 'alta',
            'urgent' => 'urgente'
        ];

        return $mapa[$prioridade] ?? 'media';
    }
}
