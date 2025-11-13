<?php

namespace App\Controllers\Whatsapp;

use App\Controllers\BaseController;

use App\Models\Whatsapp\ModelWhatsappConfiguracao;
use App\Services\Whatsapp\ServiceWhatsappEntidade;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar configurações do WhatsApp
 */
class ControllerWhatsappConfiguracao extends BaseController
{
    private ModelWhatsappConfiguracao $model;
    private ServiceWhatsappEntidade $entidadeService;

    public function __construct()
    {
        $this->model = new ModelWhatsappConfiguracao();
        $this->entidadeService = new ServiceWhatsappEntidade();
    }

    /**
     * Lista todas as configurações
     */
    public function listar(): void
    {
        try {
            $configs = $this->model->buscarTodas();

            // Organiza por prefixo da chave (simula categorias)
            $organizadas = ['geral' => []];
            foreach ($configs as $config) {
                // Agrupa por prefixo da chave (ex: api_*, instancia_*, etc)
                $partes = explode('_', $config['chave']);
                $categoria = count($partes) > 1 ? $partes[0] : 'geral';

                if (!isset($organizadas[$categoria])) {
                    $organizadas[$categoria] = [];
                }
                $organizadas[$categoria][] = $config;
            }

            $this->sucesso($organizadas, 'Configurações carregadas');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Obtém configuração específica
     */
    public function obter(string $chave): void
    {
        try {
            $config = $this->model->buscarPorChave($chave);

            if (!$config) {
                $this->naoEncontrado('Configuração não encontrada');
                return;
            }

            $this->sucesso($config, 'Configuração encontrada');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Salva configuração
     */
    public function salvar(): void
    {
        try {
            $dados = $this->obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'chave' => 'obrigatorio',
                'valor' => 'obrigatorio'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $sucesso = $this->model->salvar($dados['chave'], $dados['valor']);

            if ($sucesso) {
                $this->sucesso(null, 'Configuração salva com sucesso');
            } else {
                $this->erro('Erro ao salvar configuração', 400);
            }

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Reseta configuração para padrão
     */
    public function resetar(string $chave): void
    {
        try {
            $sucesso = $this->model->resetar($chave);

            if ($sucesso) {
                $this->sucesso(null, 'Configuração resetada');
            } else {
                $this->erro('Configuração não encontrada', 404);
            }

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Sincroniza entidade
     */
    public function sincronizarEntidade(): void
    {
        try {
            $dados = $this->obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'tipo' => 'obrigatorio',
                'id' => 'obrigatorio|inteiro'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $resultado = $this->entidadeService->sincronizarEntidade($dados['tipo'], (int) $dados['id']);

            $this->sucesso($resultado, 'Entidade sincronizada');

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sincroniza lote de entidades
     */
    public function sincronizarLote(): void
    {
        try {
            $dados = $this->obterDados();

            $erros = AuxiliarValidacao::validar($dados, [
                'tipo' => 'obrigatorio'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            $limit = (int) ($dados['limit'] ?? 100);
            $offset = (int) ($dados['offset'] ?? 0);

            $resultado = $this->entidadeService->sincronizarLote($dados['tipo'], $limit, $offset);

            $this->sucesso(
                $resultado,
                "Sincronizados: {$resultado['sincronizados']}, Erros: {$resultado['erros']}"
            );

        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }
}
