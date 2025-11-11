<?php

namespace App\Controllers\Login;

use App\Models\Login\ModelLoginAttempt;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar tentativas de login e proteção contra brute force
 */
class ControllerLoginAttempt
{
    private ModelLoginAttempt $model;

    public function __construct()
    {
        $this->model = new ModelLoginAttempt();
    }

    /**
     * Lista todas as tentativas de login com filtros
     * GET /api/login-attempts
     */
    public function listarTentativas(): void
    {
        try {
            // Obtém parâmetros de filtro
            $filtros = [
                'email' => $_GET['email'] ?? null,
                'ip_address' => $_GET['ip_address'] ?? null,
                'sucesso' => isset($_GET['sucesso']) ? (bool) $_GET['sucesso'] : null,
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 50);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            // Busca dados
            $tentativas = $this->model->listarTentativas($filtros);
            $total = $this->model->contarTentativas(array_diff_key($filtros, array_flip(['limite', 'offset'])));

            AuxiliarResposta::paginado(
                $tentativas,
                $total,
                $paginaAtual,
                $porPagina,
                'Tentativas de login listadas com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista todos os bloqueios ativos
     * GET /api/login-bloqueios
     */
    public function listarBloqueios(): void
    {
        try {
            $filtros = [
                'tipo' => $_GET['tipo'] ?? null,
                'email' => $_GET['email'] ?? null,
                'ip_address' => $_GET['ip_address'] ?? null
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 50);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            // Busca dados
            $bloqueios = $this->model->listarBloqueios($filtros);
            $total = $this->model->contarBloqueios(array_diff_key($filtros, array_flip(['limite', 'offset'])));

            AuxiliarResposta::paginado(
                $bloqueios,
                $total,
                $paginaAtual,
                $porPagina,
                'Bloqueios ativos listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas gerais de tentativas e bloqueios
     * GET /api/login-attempts/estatisticas
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();
            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Desbloqueia um email específico
     * DELETE /api/login-bloqueios/email
     */
    public function desbloquearEmail(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            if (empty($dados['email'])) {
                AuxiliarResposta::erro('Email é obrigatório', 400);
                return;
            }

            if (!AuxiliarValidacao::email($dados['email'])) {
                AuxiliarResposta::erro('Email inválido', 400);
                return;
            }

            $sucesso = $this->model->desbloquearEmail($dados['email']);

            if ($sucesso) {
                AuxiliarResposta::sucesso(
                    ['email' => $dados['email']],
                    'Email desbloqueado com sucesso'
                );
            } else {
                AuxiliarResposta::erro('Falha ao desbloquear email', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Desbloqueia um IP específico
     * DELETE /api/login-bloqueios/ip
     */
    public function desbloquearIp(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            if (empty($dados['ip_address'])) {
                AuxiliarResposta::erro('IP é obrigatório', 400);
                return;
            }

            // Validação básica de IP
            if (!filter_var($dados['ip_address'], FILTER_VALIDATE_IP)) {
                AuxiliarResposta::erro('IP inválido', 400);
                return;
            }

            $sucesso = $this->model->desbloquearIp($dados['ip_address']);

            if ($sucesso) {
                AuxiliarResposta::sucesso(
                    ['ip_address' => $dados['ip_address']],
                    'IP desbloqueado com sucesso'
                );
            } else {
                AuxiliarResposta::erro('Falha ao desbloquear IP', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Desbloqueia um bloqueio específico por ID
     * DELETE /api/login-bloqueios/{id}
     */
    public function desbloquearPorId(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $sucesso = $this->model->desbloquearPorId((int) $id);

            if ($sucesso) {
                AuxiliarResposta::sucesso(
                    ['id' => (int) $id],
                    'Bloqueio removido com sucesso'
                );
            } else {
                AuxiliarResposta::erro('Falha ao remover bloqueio', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um bloqueio manual
     * POST /api/login-bloqueios
     */
    public function criarBloqueio(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            // Validações
            if (empty($dados['tipo']) || !in_array($dados['tipo'], ['ip', 'email', 'ambos'])) {
                AuxiliarResposta::erro('Tipo de bloqueio inválido (ip, email ou ambos)', 400);
                return;
            }

            if (($dados['tipo'] === 'email' || $dados['tipo'] === 'ambos') && empty($dados['email'])) {
                AuxiliarResposta::erro('Email é obrigatório para este tipo de bloqueio', 400);
                return;
            }

            if (($dados['tipo'] === 'ip' || $dados['tipo'] === 'ambos') && empty($dados['ip_address'])) {
                AuxiliarResposta::erro('IP é obrigatório para este tipo de bloqueio', 400);
                return;
            }

            if (!empty($dados['email']) && !AuxiliarValidacao::email($dados['email'])) {
                AuxiliarResposta::erro('Email inválido', 400);
                return;
            }

            if (!empty($dados['ip_address']) && !filter_var($dados['ip_address'], FILTER_VALIDATE_IP)) {
                AuxiliarResposta::erro('IP inválido', 400);
                return;
            }

            $permanente = $dados['permanente'] ?? false;
            $motivo = $dados['motivo'] ?? 'Bloqueio manual por administrador';

            $sucesso = $this->model->criarBloqueio(
                $dados['tipo'],
                $dados['email'] ?? null,
                $dados['ip_address'] ?? null,
                0,
                $permanente,
                $motivo
            );

            if ($sucesso) {
                AuxiliarResposta::sucesso(
                    [
                        'tipo' => $dados['tipo'],
                        'email' => $dados['email'] ?? null,
                        'ip_address' => $dados['ip_address'] ?? null,
                        'permanente' => $permanente
                    ],
                    'Bloqueio criado com sucesso'
                );
            } else {
                AuxiliarResposta::erro('Falha ao criar bloqueio', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Verifica status de bloqueio de um email ou IP
     * GET /api/login-bloqueios/verificar
     */
    public function verificarBloqueio(): void
    {
        try {
            $email = $_GET['email'] ?? null;
            $ipAddress = $_GET['ip_address'] ?? null;

            $resultado = [
                'email_bloqueado' => false,
                'ip_bloqueado' => false,
                'info_email' => null,
                'info_ip' => null
            ];

            if ($email) {
                $resultado['email_bloqueado'] = $this->model->emailEstaBloqueado($email);
                if ($resultado['email_bloqueado']) {
                    $resultado['info_email'] = $this->model->obterBloqueioEmail($email);
                }
            }

            if ($ipAddress) {
                $resultado['ip_bloqueado'] = $this->model->ipEstaBloqueado($ipAddress);
                if ($resultado['ip_bloqueado']) {
                    $resultado['info_ip'] = $this->model->obterBloqueioIp($ipAddress);
                }
            }

            AuxiliarResposta::sucesso($resultado, 'Status verificado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
