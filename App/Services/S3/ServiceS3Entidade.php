<?php

namespace App\Services\S3;

use App\Models\S3\ModelS3Arquivo;

/**
 * Service para gerenciar relacionamento de arquivos com entidades
 * Facilita anexar arquivos a clientes, produtos, NFe, etc
 */
class ServiceS3Entidade
{
    private ModelS3Arquivo $modelArquivo;
    private ServiceS3 $serviceS3;

    public function __construct()
    {
        $this->modelArquivo = new ModelS3Arquivo();
        $this->serviceS3 = new ServiceS3();
    }

    /**
     * Busca arquivos de uma entidade
     */
    public function buscarArquivos(string $tipo, int $id): array
    {
        $arquivos = $this->modelArquivo->buscarPorEntidade($tipo, $id);

        return [
            'sucesso' => true,
            'total' => count($arquivos),
            'arquivos' => $arquivos
        ];
    }

    /**
     * Anexa arquivo existente a uma entidade
     */
    public function anexarArquivo(int $arquivoId, string $tipo, int $id): array
    {
        $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

        if (!$arquivo) {
            throw new \Exception("Arquivo não encontrado.");
        }

        $sucesso = $this->modelArquivo->atualizar($arquivoId, [
            'entidade_tipo' => $tipo,
            'entidade_id' => $id
        ]);

        if ($sucesso) {
            return [
                'sucesso' => true,
                'arquivo' => $this->modelArquivo->buscarPorId($arquivoId)
            ];
        }

        return ['sucesso' => false];
    }

    /**
     * Desanexa arquivo de uma entidade
     */
    public function desanexarArquivo(int $arquivoId): array
    {
        $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

        if (!$arquivo) {
            throw new \Exception("Arquivo não encontrado.");
        }

        $sucesso = $this->modelArquivo->atualizar($arquivoId, [
            'entidade_tipo' => null,
            'entidade_id' => null
        ]);

        if ($sucesso) {
            return [
                'sucesso' => true,
                'arquivo' => $this->modelArquivo->buscarPorId($arquivoId)
            ];
        }

        return ['sucesso' => false];
    }

    /**
     * Upload e anexa arquivo a uma entidade em uma única operação
     */
    public function uploadEAnexar(array $params, string $tipo, int $id): array
    {
        // Adiciona informações da entidade aos parâmetros
        $params['entidade_tipo'] = $tipo;
        $params['entidade_id'] = $id;

        // Faz upload
        return $this->serviceS3->upload($params);
    }

    /**
     * Deleta todos os arquivos de uma entidade
     */
    public function deletarArquivosEntidade(
        string $tipo,
        int $id,
        bool $deletarS3 = true
    ): array {
        $arquivos = $this->modelArquivo->buscarPorEntidade($tipo, $id);

        $deletados = 0;
        $erros = [];

        foreach ($arquivos as $arquivo) {
            try {
                $this->serviceS3->deletar($arquivo['id'], $deletarS3);
                $deletados++;
            } catch (\Exception $e) {
                $erros[] = [
                    'arquivo_id' => $arquivo['id'],
                    'erro' => $e->getMessage()
                ];
            }
        }

        return [
            'sucesso' => true,
            'total' => count($arquivos),
            'deletados' => $deletados,
            'erros' => $erros
        ];
    }

    /**
     * Conta arquivos de uma entidade
     */
    public function contarArquivos(string $tipo, int $id): int
    {
        $arquivos = $this->modelArquivo->buscarPorEntidade($tipo, $id);
        return count($arquivos);
    }

    /**
     * Busca arquivos por categoria dentro de uma entidade
     */
    public function buscarPorCategoria(
        string $tipo,
        int $id,
        string $categoria
    ): array {
        $arquivos = $this->modelArquivo->listar([
            'entidade_tipo' => $tipo,
            'entidade_id' => $id,
            'categoria' => $categoria
        ]);

        return [
            'sucesso' => true,
            'total' => count($arquivos),
            'arquivos' => $arquivos
        ];
    }

    /**
     * Atualiza categoria de um arquivo
     */
    public function atualizarCategoria(int $arquivoId, string $categoria): array
    {
        $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

        if (!$arquivo) {
            throw new \Exception("Arquivo não encontrado.");
        }

        $sucesso = $this->modelArquivo->atualizar($arquivoId, [
            'categoria' => $categoria
        ]);

        if ($sucesso) {
            return [
                'sucesso' => true,
                'arquivo' => $this->modelArquivo->buscarPorId($arquivoId)
            ];
        }

        return ['sucesso' => false];
    }

    /**
     * Move arquivo de uma entidade para outra
     */
    public function moverArquivo(
        int $arquivoId,
        string $novoTipo,
        int $novoId
    ): array {
        $arquivo = $this->modelArquivo->buscarPorId($arquivoId);

        if (!$arquivo) {
            throw new \Exception("Arquivo não encontrado.");
        }

        $sucesso = $this->modelArquivo->atualizar($arquivoId, [
            'entidade_tipo' => $novoTipo,
            'entidade_id' => $novoId
        ]);

        if ($sucesso) {
            return [
                'sucesso' => true,
                'arquivo' => $this->modelArquivo->buscarPorId($arquivoId),
                'de' => [
                    'tipo' => $arquivo['entidade_tipo'],
                    'id' => $arquivo['entidade_id']
                ],
                'para' => [
                    'tipo' => $novoTipo,
                    'id' => $novoId
                ]
            ];
        }

        return ['sucesso' => false];
    }

    /**
     * Gera URLs assinadas para todos os arquivos de uma entidade
     */
    public function gerarUrlsEntidade(
        string $tipo,
        int $id,
        ?int $expiracaoSegundos = null
    ): array {
        $arquivos = $this->modelArquivo->buscarPorEntidade($tipo, $id);

        $resultado = [];

        foreach ($arquivos as $arquivo) {
            try {
                $url = $this->serviceS3->gerarUrlAssinada(
                    $arquivo['id'],
                    $expiracaoSegundos
                );

                $resultado[] = [
                    'arquivo_id' => $arquivo['id'],
                    'nome_original' => $arquivo['nome_original'],
                    'url' => $url['url'],
                    'expira_em' => $url['expira_em'] ?? null,
                    'publico' => $url['publico'] ?? false
                ];
            } catch (\Exception $e) {
                $resultado[] = [
                    'arquivo_id' => $arquivo['id'],
                    'erro' => $e->getMessage()
                ];
            }
        }

        return [
            'sucesso' => true,
            'total' => count($arquivos),
            'urls' => $resultado
        ];
    }
}
