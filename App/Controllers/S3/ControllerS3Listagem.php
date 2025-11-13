<?php

namespace App\Controllers\S3;

use App\Controllers\BaseController;
use App\Models\S3\ModelS3Arquivo;
use App\Models\S3\ModelS3Historico;
use App\Services\S3\ServiceS3Entidade;

/**
 * Controller para listar arquivos e histórico do S3
 */
class ControllerS3Listagem extends BaseController
{
    private ModelS3Arquivo $modelArquivo;
    private ModelS3Historico $modelHistorico;
    private ServiceS3Entidade $serviceEntidade;

    public function __construct()
    {
        $this->modelArquivo = new ModelS3Arquivo();
        $this->modelHistorico = new ModelS3Historico();
        $this->serviceEntidade = new ServiceS3Entidade();
    }

    /**
     * GET /s3/arquivos
     * Lista arquivos com filtros
     */
    public function listar(): void
    {
        try {
            $limite = (int)($this->obterParametro('limite') ?? 50);
            $pagina = (int)($this->obterParametro('pagina') ?? 1);
            $offset = ($pagina - 1) * $limite;

            // Filtros opcionais
            $filtros = [];

            if ($bucket = $this->obterParametro('bucket')) {
                $filtros['bucket'] = $bucket;
            }

            if ($entidadeTipo = $this->obterParametro('entidade_tipo')) {
                $filtros['entidade_tipo'] = $entidadeTipo;
            }

            if ($entidadeId = $this->obterParametro('entidade_id')) {
                $filtros['entidade_id'] = (int)$entidadeId;
            }

            if ($categoria = $this->obterParametro('categoria')) {
                $filtros['categoria'] = $categoria;
            }

            if ($tipoMime = $this->obterParametro('tipo_mime')) {
                $filtros['tipo_mime'] = $tipoMime;
            }

            if ($criadoPor = $this->obterParametro('criado_por')) {
                $filtros['criado_por'] = (int)$criadoPor;
            }

            $arquivos = $this->modelArquivo->listar($filtros, $limite, $offset);
            $total = $this->modelArquivo->contar($filtros);

            $this->sucesso([
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite),
                'arquivos' => $arquivos
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar arquivos');
        }
    }

    /**
     * GET /s3/arquivos/entidade/{tipo}/{id}
     * Lista arquivos de uma entidade específica
     */
    public function listarPorEntidade(string $tipo, int $id): void
    {
        try {
            $resultado = $this->serviceEntidade->buscarArquivos($tipo, $id);

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar arquivos da entidade');
        }
    }

    /**
     * GET /s3/historico
     * Lista histórico de operações
     */
    public function listarHistorico(): void
    {
        try {
            $limite = (int)($this->obterParametro('limite') ?? 50);
            $pagina = (int)($this->obterParametro('pagina') ?? 1);
            $offset = ($pagina - 1) * $limite;

            // Filtros opcionais
            $filtros = [];

            if ($arquivoId = $this->obterParametro('arquivo_id')) {
                $filtros['arquivo_id'] = (int)$arquivoId;
            }

            if ($operacao = $this->obterParametro('operacao')) {
                $filtros['operacao'] = $operacao;
            }

            if ($status = $this->obterParametro('status')) {
                $filtros['status'] = $status;
            }

            if ($colaboradorId = $this->obterParametro('colaborador_id')) {
                $filtros['colaborador_id'] = (int)$colaboradorId;
            }

            if ($bucket = $this->obterParametro('bucket')) {
                $filtros['bucket'] = $bucket;
            }

            if ($dataInicio = $this->obterParametro('data_inicio')) {
                $filtros['data_inicio'] = $dataInicio;
            }

            if ($dataFim = $this->obterParametro('data_fim')) {
                $filtros['data_fim'] = $dataFim;
            }

            $historico = $this->modelHistorico->listar($filtros, $limite, $offset);
            $total = $this->modelHistorico->contar($filtros);

            $this->sucesso([
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite),
                'historico' => $historico
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar histórico');
        }
    }

    /**
     * GET /s3/estatisticas
     * Obtém estatísticas de armazenamento
     */
    public function estatisticas(): void
    {
        try {
            $stats = $this->modelArquivo->obterEstatisticas();

            $this->sucesso($stats);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter estatísticas');
        }
    }

    /**
     * GET /s3/historico/estatisticas
     * Obtém estatísticas de operações
     */
    public function estatisticasHistorico(): void
    {
        try {
            $filtros = [];

            if ($dataInicio = $this->obterParametro('data_inicio')) {
                $filtros['data_inicio'] = $dataInicio;
            }

            if ($dataFim = $this->obterParametro('data_fim')) {
                $filtros['data_fim'] = $dataFim;
            }

            $stats = $this->modelHistorico->obterEstatisticas($filtros);

            $this->sucesso($stats);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter estatísticas de histórico');
        }
    }

    /**
     * GET /s3/historico/uploads-recentes
     * Obtém uploads recentes
     */
    public function uploadsRecentes(): void
    {
        try {
            $limite = (int)($this->obterParametro('limite') ?? 10);

            $uploads = $this->modelHistorico->obterUploadsRecentes($limite);

            $this->sucesso([
                'total' => count($uploads),
                'uploads' => $uploads
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter uploads recentes');
        }
    }

    /**
     * GET /s3/historico/falhas-recentes
     * Obtém falhas recentes
     */
    public function falhasRecentes(): void
    {
        try {
            $limite = (int)($this->obterParametro('limite') ?? 10);

            $falhas = $this->modelHistorico->obterFalhasRecentes($limite);

            $this->sucesso([
                'total' => count($falhas),
                'falhas' => $falhas
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter falhas recentes');
        }
    }

    /**
     * GET /s3/historico/atividade
     * Obtém atividade por período
     */
    public function atividade(): void
    {
        try {
            $periodo = $this->obterParametro('periodo') ?? 'day';
            $limite = (int)($this->obterParametro('limite') ?? 30);

            $atividade = $this->modelHistorico->obterAtividadePorPeriodo($periodo, $limite);

            $this->sucesso([
                'periodo' => $periodo,
                'total' => count($atividade),
                'atividade' => $atividade
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter atividade');
        }
    }

    /**
     * GET /s3/arquivos/deletados
     * Lista arquivos deletados (soft delete)
     */
    public function arquivosDeletados(): void
    {
        try {
            $diasAtras = (int)($this->obterParametro('dias') ?? 30);

            $deletados = $this->modelArquivo->buscarDeletados($diasAtras);

            $this->sucesso([
                'total' => count($deletados),
                'arquivos' => $deletados
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao listar arquivos deletados');
        }
    }
}
