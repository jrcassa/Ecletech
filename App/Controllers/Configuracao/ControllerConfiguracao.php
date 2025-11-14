<?php

namespace App\Controllers\Configuracao;

use App\Controllers\BaseController;
use App\Core\Configuracao;

/**
 * Controller para gerenciar configurações do sistema
 */
class ControllerConfiguracao extends BaseController
{
    private Configuracao $config;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
    }

    /**
     * GET /configuracoes/brute-force
     * Obtém configurações de brute force
     */
    public function obterBruteForce(): void
    {
        try {
            $configuracoes = [
                'max_tentativas' => $this->config->obter('BRUTE_FORCE_MAX_TENTATIVAS', 5),
                'tempo_bloqueio' => $this->config->obter('BRUTE_FORCE_TEMPO_BLOQUEIO', 30),
                'janela_tempo' => $this->config->obter('BRUTE_FORCE_JANELA_TEMPO', 15)
            ];

            $this->sucesso($configuracoes);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter configurações de brute force');
        }
    }

    /**
     * PUT /configuracoes/brute-force
     * Atualiza configurações de brute force
     */
    public function atualizarBruteForce(): void
    {
        try {
            $dados = $this->obterCorpoRequisicao();

            // Validações
            $erros = [];

            if (!isset($dados['max_tentativas'])) {
                $erros['max_tentativas'][] = 'Campo obrigatório';
            } elseif (!is_numeric($dados['max_tentativas']) || $dados['max_tentativas'] < 1 || $dados['max_tentativas'] > 100) {
                $erros['max_tentativas'][] = 'Deve ser um número entre 1 e 100';
            }

            if (!isset($dados['tempo_bloqueio'])) {
                $erros['tempo_bloqueio'][] = 'Campo obrigatório';
            } elseif (!is_numeric($dados['tempo_bloqueio']) || $dados['tempo_bloqueio'] < 1 || $dados['tempo_bloqueio'] > 1440) {
                $erros['tempo_bloqueio'][] = 'Deve ser um número entre 1 e 1440';
            }

            if (!isset($dados['janela_tempo'])) {
                $erros['janela_tempo'][] = 'Campo obrigatório';
            } elseif (!is_numeric($dados['janela_tempo']) || $dados['janela_tempo'] < 1 || $dados['janela_tempo'] > 60) {
                $erros['janela_tempo'][] = 'Deve ser um número entre 1 e 60';
            }

            if (!empty($erros)) {
                $this->erroValidacao($erros);
                return;
            }

            // Atualiza configurações
            $this->config->definir('BRUTE_FORCE_MAX_TENTATIVAS', (int)$dados['max_tentativas']);
            $this->config->definir('BRUTE_FORCE_TEMPO_BLOQUEIO', (int)$dados['tempo_bloqueio']);
            $this->config->definir('BRUTE_FORCE_JANELA_TEMPO', (int)$dados['janela_tempo']);

            $configuracoes = [
                'max_tentativas' => (int)$dados['max_tentativas'],
                'tempo_bloqueio' => (int)$dados['tempo_bloqueio'],
                'janela_tempo' => (int)$dados['janela_tempo']
            ];

            $this->sucesso($configuracoes, 'Configurações atualizadas com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao atualizar configurações de brute force');
        }
    }
}
