<?php

namespace App\Controllers\S3;

use App\Controllers\BaseController;
use App\Services\S3\ServiceS3;
use App\Models\S3\ModelS3Arquivo;

/**
 * Controller para gerenciar downloads do S3
 */
class ControllerS3Download extends BaseController
{
    private ServiceS3 $service;
    private ModelS3Arquivo $modelArquivo;

    public function __construct()
    {
        $this->service = new ServiceS3();
        $this->modelArquivo = new ModelS3Arquivo();
    }

    /**
     * GET /s3/download/{id}
     * Gera URL assinada para download de arquivo
     */
    public function download(int $id): void
    {
        try {
            $expiracao = $this->obterParametro('expiracao');
            $expiracaoSegundos = $expiracao ? (int)$expiracao : null;

            $resultado = $this->service->gerarUrlAssinada($id, $expiracaoSegundos);

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao gerar URL de download');
        }
    }

    /**
     * GET /s3/download/uuid/{uuid}
     * Gera URL assinada usando UUID do arquivo
     */
    public function downloadPorUuid(string $uuid): void
    {
        try {
            $arquivo = $this->modelArquivo->buscarPorUuid($uuid);

            if (!$arquivo) {
                $this->naoEncontrado('Arquivo não encontrado');
                return;
            }

            $expiracao = $this->obterParametro('expiracao');
            $expiracaoSegundos = $expiracao ? (int)$expiracao : null;

            $resultado = $this->service->gerarUrlAssinada($arquivo['id'], $expiracaoSegundos);

            $this->sucesso($resultado);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao gerar URL de download');
        }
    }

    /**
     * GET /s3/arquivos/{id}
     * Obtém informações de um arquivo
     */
    public function obter(int $id): void
    {
        try {
            $arquivo = $this->modelArquivo->buscarPorId($id);

            if (!$arquivo) {
                $this->naoEncontrado('Arquivo não encontrado');
                return;
            }

            // Se solicitado, inclui URL assinada
            $incluirUrl = $this->obterParametro('incluir_url');

            if ($incluirUrl === 'true' || $incluirUrl === '1') {
                $url = $this->service->gerarUrlAssinada($id);
                $arquivo['url_download'] = $url['url'];
                $arquivo['url_expira_em'] = $url['expira_em'] ?? null;
            }

            $this->sucesso($arquivo);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter arquivo');
        }
    }

    /**
     * GET /s3/arquivos/uuid/{uuid}
     * Obtém informações de arquivo por UUID
     */
    public function obterPorUuid(string $uuid): void
    {
        try {
            $arquivo = $this->modelArquivo->buscarPorUuid($uuid);

            if (!$arquivo) {
                $this->naoEncontrado('Arquivo não encontrado');
                return;
            }

            // Se solicitado, inclui URL assinada
            $incluirUrl = $this->obterParametro('incluir_url');

            if ($incluirUrl === 'true' || $incluirUrl === '1') {
                $url = $this->service->gerarUrlAssinada($arquivo['id']);
                $arquivo['url_download'] = $url['url'];
                $arquivo['url_expira_em'] = $url['expira_em'] ?? null;
            }

            $this->sucesso($arquivo);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao obter arquivo');
        }
    }

    /**
     * POST /s3/download/lote
     * Gera URLs assinadas para múltiplos arquivos
     */
    public function downloadLote(): void
    {
        try {
            $dados = $this->obterDados();

            if (empty($dados['ids']) || !is_array($dados['ids'])) {
                $this->badRequest('IDs de arquivos são obrigatórios');
                return;
            }

            $expiracaoSegundos = $dados['expiracao'] ?? null;
            $urls = [];

            foreach ($dados['ids'] as $id) {
                try {
                    $url = $this->service->gerarUrlAssinada($id, $expiracaoSegundos);
                    $arquivo = $this->modelArquivo->buscarPorId($id);

                    $urls[] = [
                        'arquivo_id' => $id,
                        'nome_original' => $arquivo['nome_original'] ?? null,
                        'url' => $url['url'],
                        'expira_em' => $url['expira_em'] ?? null
                    ];
                } catch (\Exception $e) {
                    $urls[] = [
                        'arquivo_id' => $id,
                        'erro' => $e->getMessage()
                    ];
                }
            }

            $this->sucesso([
                'total' => count($urls),
                'urls' => $urls
            ]);
        } catch (\Exception $e) {
            $this->tratarErro($e, 500, 'Erro ao gerar URLs em lote');
        }
    }
}
