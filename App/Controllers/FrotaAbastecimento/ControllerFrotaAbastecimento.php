<?php

namespace App\Controllers\FrotaAbastecimento;

use App\Controllers\BaseController;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\Frota\ModelFrota;
use App\Models\Colaborador\ModelColaborador;
use App\Services\FrotaAbastecimento\ServiceFrotaAbastecimento;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarResposta;

/**
 * Controller para gerenciar abastecimentos da frota
 */
class ControllerFrotaAbastecimento extends BaseController
{
    private ModelFrotaAbastecimento $model;
    private ModelFrota $modelFrota;
    private ModelColaborador $modelColaborador;
    private ServiceFrotaAbastecimento $service;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimento();
        $this->modelFrota = new ModelFrota();
        $this->modelColaborador = new ModelColaborador();
        $this->service = new ServiceFrotaAbastecimento();
    }

    /**
     * Lista abastecimentos (Admin vê todos, Motorista vê apenas seus)
     */
    public function listar(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $permissoes = $usuarioLogado['permissoes'] ?? [];

            // Filtros base
            $filtros = [
                'frota_id' => $_GET['frota_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null,
                'combustivel' => $_GET['combustivel'] ?? null,
                'ordenacao' => $_GET['ordenacao'] ?? 'criado_em',
                'direcao' => $_GET['direcao'] ?? 'DESC'
            ];

            // Se não tem permissão de visualizar, só vê seus próprios
            if (!in_array('frota_abastecimento.visualizar', $permissoes)) {
                $filtros['colaborador_id'] = $usuarioLogado['id'];
            }

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 20);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            // Busca dados
            $abastecimentos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $abastecimentos,
                $total,
                $paginaAtual,
                $porPagina,
                'Abastecimentos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca abastecimento por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();
            $permissoes = $usuarioLogado['permissoes'] ?? [];

            $abastecimento = $this->model->buscarComDetalhes((int) $id);

            if (!$abastecimento) {
                $this->naoEncontrado('Abastecimento não encontrado');
                return;
            }

            // Se não tem permissão de visualizar, verifica se é o próprio
            if (!in_array('frota_abastecimento.visualizar', $permissoes)) {
                if ($abastecimento['colaborador_id'] != $usuarioLogado['id']) {
                    $this->proibido('Você não tem permissão para visualizar este abastecimento');
                    return;
                }
            }

            $this->sucesso($abastecimento, 'Abastecimento encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria ordem de abastecimento (Admin/Gestor)
     */
    public function criar(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'frota_id' => 'obrigatorio|inteiro',
                'colaborador_id' => 'obrigatorio|inteiro',
                'data_limite' => 'opcional|data',
                'observacao_admin' => 'opcional|max:500'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se frota existe e está ativa
            $frota = $this->modelFrota->buscarPorId($dados['frota_id']);
            if (!$frota || $frota['deletado_em'] !== null) {
                $this->erro('Frota não encontrada ou inativa');
                return;
            }

            // Verifica se colaborador existe e está ativo
            $colaborador = $this->modelColaborador->buscarPorId($dados['colaborador_id']);
            if (!$colaborador || $colaborador['deletado_em'] !== null) {
                $this->erro('Colaborador não encontrado ou inativo');
                return;
            }

            // Verifica se já existe ordem aguardando
            $ordemExistente = $this->model->buscarOrdemAguardando($dados['frota_id'], $dados['colaborador_id']);
            if ($ordemExistente) {
                $this->erro('Já existe uma ordem de abastecimento aguardando para este motorista e veículo');
                return;
            }

            // Adiciona criador
            $dados['criado_por'] = $usuarioLogado['id'];

            // Cria ordem via service (envia WhatsApp)
            $id = $this->service->registrarOrdem($dados);

            $this->criado(['id' => $id], 'Ordem de abastecimento criada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista ordens pendentes do motorista logado
     */
    public function meusPendentes(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();

            $ordens = $this->model->buscarOrdensPendentes($usuarioLogado['id']);

            $this->sucesso($ordens, 'Ordens pendentes listadas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista histórico do motorista logado
     */
    public function meuHistorico(): void
    {
        try {
            $usuarioLogado = $this->obterUsuarioAutenticado();
            $limite = (int) ($_GET['limite'] ?? 20);

            $historico = $this->model->buscarHistoricoMotorista($usuarioLogado['id'], $limite);

            $this->sucesso($historico, 'Histórico listado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Finaliza abastecimento (Motorista)
     */
    public function finalizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();
            $dados = $this->obterDados();

            // Busca ordem
            $ordem = $this->model->buscarPorId((int) $id);
            if (!$ordem) {
                $this->naoEncontrado('Ordem de abastecimento não encontrada');
                return;
            }

            // Valida propriedade
            if ($ordem['colaborador_id'] != $usuarioLogado['id']) {
                $this->proibido('Esta ordem não pertence a você');
                return;
            }

            // Valida status
            if ($ordem['status'] !== 'aguardando') {
                $this->erro('Esta ordem não está aguardando finalização (status: ' . $ordem['status'] . ')');
                return;
            }

            // Validação dos dados
            $erros = AuxiliarValidacao::validar($dados, [
                'km' => 'obrigatorio|decimal',
                'litros' => 'obrigatorio|decimal',
                'combustivel' => 'obrigatorio|em:gasolina,etanol,diesel,gnv,flex',
                'valor' => 'obrigatorio|decimal',
                'forma_pagamento_id' => 'opcional|inteiro',
                'data_abastecimento' => 'obrigatorio',
                'latitude' => 'opcional|max:30',
                'longitude' => 'opcional|max:30',
                'observacao_motorista' => 'opcional|max:500',
                'comprovante_base64' => 'opcional' // Aceita comprovante na finalização
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Validações de negócio
            if ($dados['km'] <= 0 || $dados['litros'] <= 0 || $dados['valor'] <= 0) {
                $this->erro('KM, litros e valor devem ser maiores que zero');
                return;
            }

            // Valida KM sequencial
            $ultimoAbastecimento = $this->model->buscarUltimoAbastecimentoFrota($ordem['frota_id']);
            if ($ultimoAbastecimento && $dados['km'] <= $ultimoAbastecimento['km']) {
                $this->erro("KM informado ({$dados['km']}) deve ser maior que o último registro ({$ultimoAbastecimento['km']})");
                return;
            }

            // Valida data não futura
            $agora = new \DateTime();
            $dataAbastecimento = new \DateTime($dados['data_abastecimento']);
            if ($dataAbastecimento > $agora) {
                $this->erro('Data do abastecimento não pode ser futura');
                return;
            }

            $dados['finalizado_por'] = $usuarioLogado['id'];

            // Se tiver comprovante, anexa ANTES de finalizar (para que a notificação já contenha a foto)
            if (!empty($dados['comprovante_base64'])) {
                try {
                    $this->service->anexarComprovante((int) $id, $dados['comprovante_base64']);
                } catch (\Exception $e) {
                    // Log do erro mas não bloqueia finalização
                    error_log("Erro ao anexar comprovante durante finalização: " . $e->getMessage());
                }
            }

            // Finaliza via service (calcula métricas, detecta alertas, envia WhatsApp)
            $this->service->finalizarAbastecimento((int) $id, $dados);

            $this->sucesso(['id' => (int) $id], 'Abastecimento finalizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza ordem (Admin - apenas aguardando)
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            $ordem = $this->model->buscarPorId((int) $id);
            if (!$ordem) {
                $this->naoEncontrado('Ordem não encontrada');
                return;
            }

            if ($ordem['status'] !== 'aguardando') {
                $this->erro('Não é possível editar ordem que não está aguardando');
                return;
            }

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'data_limite' => 'opcional|data',
                'observacao_admin' => 'opcional|max:500'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $this->model->atualizar((int) $id, $dados);

            $this->sucesso(['id' => (int) $id], 'Ordem atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cancela ordem (Admin)
     */
    public function cancelar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();
            $observacao = $dados['observacao'] ?? null;

            $ordem = $this->model->buscarPorId((int) $id);
            if (!$ordem) {
                $this->naoEncontrado('Ordem não encontrada');
                return;
            }

            if ($ordem['status'] !== 'aguardando') {
                $this->erro('Não é possível cancelar ordem que não está aguardando');
                return;
            }

            $this->service->cancelarOrdem((int) $id, $observacao);

            $this->sucesso(['id' => (int) $id], 'Ordem cancelada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta abastecimento (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $abastecimento = $this->model->buscarPorId((int) $id);
            if (!$abastecimento) {
                $this->naoEncontrado('Abastecimento não encontrado');
                return;
            }

            $this->model->deletar((int) $id);

            $this->sucesso([], 'Abastecimento deletado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas gerais
     */
    public function obterEstatisticas(): void
    {
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            $estatisticas = $this->model->obterEstatisticas($filtros);

            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Anexa comprovante (Upload para S3)
     */
    public function anexarComprovante(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            if (!isset($dados['comprovante_base64'])) {
                $this->erro('Arquivo não fornecido');
                return;
            }

            $abastecimento = $this->model->buscarPorId((int) $id);
            if (!$abastecimento) {
                $this->naoEncontrado('Abastecimento não encontrado');
                return;
            }

            $resultado = $this->service->anexarComprovante((int) $id, $dados['comprovante_base64']);

            $this->sucesso($resultado, 'Comprovante anexado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém comprovantes
     */
    public function obterComprovantes(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $comprovantes = $this->service->obterComprovantes((int) $id);

            $this->sucesso($comprovantes, 'Comprovantes listados com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
