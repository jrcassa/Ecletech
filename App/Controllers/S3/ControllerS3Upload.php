<?php

namespace App\Controllers\S3;

use App\Controllers\BaseController;
use App\Services\S3\ServiceS3;
use App\Models\S3\ModelS3Arquivo;

/**
 * Controller para gerenciar uploads para S3
 */
class ControllerS3Upload extends BaseController
{
    private ServiceS3 $service;
    private ModelS3Arquivo $modelArquivo;

    public function __construct()
    {
        $this->service = new ServiceS3();
        $this->modelArquivo = new ModelS3Arquivo();
    }

    /**
     * POST /s3/upload
     * Faz upload de arquivo
     */
    public function upload(): void
    {
        try {
            $dados = $this->obterDados();

            // Validações básicas
            if (empty($dados['nome_original'])) {
                $this->erro('Nome do arquivo é obrigatório', 400);
                return;
            }

            if (empty($dados['conteudo']) && empty($dados['base64'])) {
                $this->erro('Conteúdo do arquivo é obrigatório', 400);
                return;
            }

            // Obtém usuário autenticado
            $usuario = $this->obterUsuarioAutenticado();
            $dados['criado_por'] = $usuario['id'] ?? null;

            // Upload via base64 ou conteúdo normal
            if (!empty($dados['base64'])) {
                $resultado = $this->service->uploadBase64($dados);
            } else {
                $resultado = $this->service->upload($dados);
            }

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao fazer upload');
        }
    }

    /**
     * POST /s3/upload/base64
     * Upload de arquivo em base64
     */
    public function uploadBase64(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['nome_original'])) {
                $this->erro('Nome do arquivo é obrigatório', 400);
                return;
            }

            if (empty($dados['base64'])) {
                $this->erro('Conteúdo base64 é obrigatório', 400);
                return;
            }

            $usuario = $this->obterUsuarioAutenticado();
            $dados['criado_por'] = $usuario['id'] ?? null;

            $resultado = $this->service->uploadBase64($dados);

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao fazer upload base64');
        }
    }

    /**
     * DELETE /s3/arquivos/{id}
     * Deleta arquivo
     */
    public function deletar(int $id): void
    {
        try {
            $dados = $this->obterDados();
            $deletarS3 = $dados['deletar_s3'] ?? true;

            $resultado = $this->service->deletar($id, $deletarS3);

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao deletar arquivo');
        }
    }

    /**
     * POST /s3/arquivos/{id}/restaurar
     * Restaura arquivo deletado (soft delete)
     */
    public function restaurar(int $id): void
    {
        try {
            $sucesso = $this->modelArquivo->restaurar($id);

            if ($sucesso) {
                $arquivo = $this->modelArquivo->buscarPorId($id);

                $this->sucesso([
                    'mensagem' => 'Arquivo restaurado com sucesso',
                    'arquivo' => $arquivo
                ]);
            } else {
                $this->erro('Erro ao restaurar arquivo', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao restaurar arquivo');
        }
    }

    /**
     * PUT /s3/arquivos/{id}
     * Atualiza metadados do arquivo
     */
    public function atualizar(int $id): void
    {
        try {
            $dados = $this->obterDados();

            $arquivo = $this->modelArquivo->buscarPorId($id);

            if (!$arquivo) {
                $this->naoEncontrado('Arquivo não encontrado');
                return;
            }

            $sucesso = $this->modelArquivo->atualizar($id, $dados);

            if ($sucesso) {
                $arquivoAtualizado = $this->modelArquivo->buscarPorId($id);

                $this->sucesso([
                    'mensagem' => 'Arquivo atualizado com sucesso',
                    'arquivo' => $arquivoAtualizado
                ]);
            } else {
                $this->erro('Erro ao atualizar arquivo', 500);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao atualizar arquivo');
        }
    }

    /**
     * POST /s3/upload/pasta
     * Upload de pasta inteira
     */
    public function uploadPasta(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['caminho_local'])) {
                $this->erro('Caminho local da pasta é obrigatório', 400);
                return;
            }

            $bucket = $dados['bucket'] ?? null;
            $caminhoS3 = $dados['caminho_s3'] ?? '';

            $resultado = $this->service->uploadPasta(
                $dados['caminho_local'],
                $bucket,
                $caminhoS3
            );

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao fazer upload de pasta');
        }
    }
}
