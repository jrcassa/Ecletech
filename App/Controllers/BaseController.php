<?php

namespace App\Controllers;

use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarRede;
use App\Core\Autenticacao;

/**
 * Classe base para controllers
 * Contém métodos comuns reutilizáveis em todos os controllers
 */
abstract class BaseController
{
    /**
     * Obtém o usuário autenticado
     *
     * @return array|null Dados do usuário autenticado ou null
     */
    protected function obterUsuarioAutenticado(): ?array
    {
        try {
            $autenticacao = new Autenticacao();
            return $autenticacao->obterUsuarioAutenticado();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtém o ID do usuário autenticado
     *
     * @return int|null ID do usuário ou null se não autenticado
     */
    protected function obterIdUsuarioAutenticado(): ?int
    {
        $usuario = $this->obterUsuarioAutenticado();
        return $usuario['id'] ?? null;
    }

    /**
     * Verifica se o usuário está autenticado
     *
     * @return bool True se autenticado
     */
    protected function estaAutenticado(): bool
    {
        return $this->obterUsuarioAutenticado() !== null;
    }

    /**
     * Requer autenticação - retorna erro se não autenticado
     *
     * @return bool True se autenticado, false se não (e envia resposta de erro)
     */
    protected function requerAutenticacao(): bool
    {
        if (!$this->estaAutenticado()) {
            AuxiliarResposta::naoAutorizado('Autenticação necessária');
            return false;
        }
        return true;
    }

    /**
     * Obtém dados da requisição (POST/PUT/PATCH)
     *
     * @return array Dados da requisição
     */
    protected function obterDados(): array
    {
        return AuxiliarResposta::obterDados();
    }

    /**
     * Obtém um parâmetro da query string
     *
     * @param string $nome Nome do parâmetro
     * @param mixed $padrao Valor padrão se não existir
     * @return mixed Valor do parâmetro
     */
    protected function obterParametro(string $nome, mixed $padrao = null): mixed
    {
        return $_GET[$nome] ?? $padrao;
    }

    /**
     * Obtém parâmetros de paginação
     *
     * @param int $porPaginaPadrao Itens por página padrão
     * @return array ['pagina' => int, 'por_pagina' => int, 'offset' => int]
     */
    protected function obterPaginacao(int $porPaginaPadrao = 20): array
    {
        $paginaAtual = (int) $this->obterParametro('pagina', 1);
        $porPagina = (int) $this->obterParametro('por_pagina', $porPaginaPadrao);

        // Limita o número de itens por página
        $porPagina = min(max($porPagina, 1), 100);
        $paginaAtual = max($paginaAtual, 1);

        $offset = ($paginaAtual - 1) * $porPagina;

        return [
            'pagina' => $paginaAtual,
            'por_pagina' => $porPagina,
            'offset' => $offset
        ];
    }

    /**
     * Obtém parâmetros de filtro da query string
     *
     * @param array $camposPermitidos Campos permitidos para filtro
     * @return array Filtros aplicados
     */
    protected function obterFiltros(array $camposPermitidos = []): array
    {
        $filtros = [];

        foreach ($camposPermitidos as $campo) {
            $valor = $this->obterParametro($campo);
            if ($valor !== null && $valor !== '') {
                $filtros[$campo] = $valor;
            }
        }

        return $filtros;
    }

    /**
     * Obtém parâmetros de ordenação
     *
     * @param string $ordenacaoPadrao Campo padrão para ordenação
     * @param string $direcaoPadrao Direção padrão (ASC ou DESC)
     * @return array ['ordenacao' => string, 'direcao' => string]
     */
    protected function obterOrdenacao(string $ordenacaoPadrao = 'id', string $direcaoPadrao = 'ASC'): array
    {
        $ordenacao = $this->obterParametro('ordenacao', $ordenacaoPadrao);
        $direcao = strtoupper($this->obterParametro('direcao', $direcaoPadrao));

        // Valida direção
        if (!in_array($direcao, ['ASC', 'DESC'])) {
            $direcao = 'ASC';
        }

        return [
            'ordenacao' => $ordenacao,
            'direcao' => $direcao
        ];
    }

    /**
     * Valida um ID
     *
     * @param string|int $id ID a validar
     * @param string $nomeCampo Nome do campo para mensagem de erro
     * @return bool True se válido, false se inválido (e envia resposta de erro)
     */
    protected function validarId(string|int $id, string $nomeCampo = 'ID'): bool
    {
        if (!AuxiliarValidacao::inteiro($id) || (int)$id <= 0) {
            AuxiliarResposta::erro("{$nomeCampo} inválido", 400);
            return false;
        }
        return true;
    }

    /**
     * Valida dados com regras específicas
     *
     * @param array $dados Dados a validar
     * @param array $regras Regras de validação
     * @return bool True se válido, false se inválido (e envia resposta de erro)
     */
    protected function validar(array $dados, array $regras): bool
    {
        $erros = AuxiliarValidacao::validar($dados, $regras);

        if (!empty($erros)) {
            AuxiliarResposta::validacao($erros);
            return false;
        }

        return true;
    }

    /**
     * Envia resposta de erro de validação
     *
     * @param array $erros Array de erros de validação
     */
    protected function validacao(array $erros): void
    {
        AuxiliarResposta::validacao($erros);
    }

    /**
     * Trata exceções de forma padronizada
     *
     * @param \Exception $e Exceção capturada
     * @param int $codigoHttp Código HTTP de erro (padrão: 400)
     * @param string|null $mensagemPersonalizada Mensagem personalizada
     */
    protected function tratarErro(\Exception $e, int $codigoHttp = 400, ?string $mensagemPersonalizada = null): void
    {
        $mensagem = $mensagemPersonalizada ?? $e->getMessage();

        // Log do erro para debug
        error_log("Erro no controller: " . get_class($this) . " - " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        AuxiliarResposta::erro($mensagem, $codigoHttp);
    }

    /**
     * Envia resposta de sucesso
     *
     * @param mixed $dados Dados da resposta
     * @param string $mensagem Mensagem de sucesso
     * @param int $codigo Código HTTP (padrão: 200)
     */
    protected function sucesso(mixed $dados = null, string $mensagem = 'Operação realizada com sucesso', int $codigo = 200): void
    {
        AuxiliarResposta::sucesso($dados, $mensagem, $codigo);
    }

    /**
     * Envia resposta de erro
     *
     * @param string $mensagem Mensagem de erro
     * @param int $codigo Código HTTP (padrão: 400)
     */
    protected function erro(string $mensagem, int $codigo = 400): void
    {
        AuxiliarResposta::erro($mensagem, $codigo);
    }

    /**
     * Envia resposta de não encontrado
     *
     * @param string $mensagem Mensagem de erro
     */
    protected function naoEncontrado(string $mensagem = 'Recurso não encontrado'): void
    {
        AuxiliarResposta::naoEncontrado($mensagem);
    }

    /**
     * Envia resposta de não autorizado
     *
     * @param string $mensagem Mensagem de erro
     */
    protected function naoAutorizado(string $mensagem = 'Não autorizado'): void
    {
        AuxiliarResposta::naoAutorizado($mensagem);
    }

    /**
     * Envia resposta de proibido
     *
     * @param string $mensagem Mensagem de erro
     */
    protected function proibido(string $mensagem = 'Acesso proibido'): void
    {
        AuxiliarResposta::proibido($mensagem);
    }

    /**
     * Envia resposta paginada
     *
     * @param array $dados Dados da página atual
     * @param int $total Total de registros
     * @param int $paginaAtual Página atual
     * @param int $porPagina Itens por página
     * @param string $mensagem Mensagem de sucesso
     */
    protected function paginado(array $dados, int $total, int $paginaAtual, int $porPagina, string $mensagem = 'Dados listados com sucesso'): void
    {
        AuxiliarResposta::paginado($dados, $total, $paginaAtual, $porPagina, $mensagem);
    }

    /**
     * Obtém o IP do cliente
     *
     * @return string IP do cliente
     */
    protected function obterIpCliente(): string
    {
        return AuxiliarRede::obterIp();
    }

    /**
     * Obtém o User-Agent do cliente
     *
     * @return string User-Agent
     */
    protected function obterUserAgent(): string
    {
        return AuxiliarRede::obterUserAgent();
    }

    /**
     * Verifica se a requisição é AJAX
     *
     * @return bool True se for AJAX
     */
    protected function ehRequisicaoAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Obtém o método HTTP da requisição
     *
     * @return string Método HTTP (GET, POST, PUT, etc)
     */
    protected function obterMetodoHttp(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Verifica se o método HTTP é o esperado
     *
     * @param string|array $metodos Método(s) esperado(s)
     * @return bool True se corresponde, false e envia erro se não
     */
    protected function validarMetodoHttp(string|array $metodos): bool
    {
        $metodos = is_array($metodos) ? $metodos : [$metodos];
        $metodoAtual = $this->obterMetodoHttp();

        if (!in_array($metodoAtual, $metodos)) {
            $metodosPermitidos = implode(', ', $metodos);
            AuxiliarResposta::erro("Método {$metodoAtual} não permitido. Use: {$metodosPermitidos}", 405);
            return false;
        }

        return true;
    }

    /**
     * Sanitiza dados de entrada
     *
     * @param array $dados Dados a sanitizar
     * @param array $camposHtml Campos que podem conter HTML
     * @return array Dados sanitizados
     */
    protected function sanitizarDados(array $dados, array $camposHtml = []): array
    {
        $sanitizados = [];

        foreach ($dados as $campo => $valor) {
            if (in_array($campo, $camposHtml)) {
                // Campos HTML mantém tags permitidas
                $sanitizados[$campo] = $valor;
            } elseif (is_string($valor)) {
                // Remove tags e sanitiza strings
                $sanitizados[$campo] = htmlspecialchars(strip_tags($valor), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($valor)) {
                // Recursivo para arrays
                $sanitizados[$campo] = $this->sanitizarDados($valor, $camposHtml);
            } else {
                $sanitizados[$campo] = $valor;
            }
        }

        return $sanitizados;
    }

    /**
     * Remove campos sensíveis de um array ou conjunto de dados
     *
     * @param array|array[] $dados Dados a processar (pode ser array simples ou array de arrays)
     * @param array $campos Campos a remover (padrão: senha)
     * @return array Dados sem os campos sensíveis
     */
    protected function removerCamposSensiveis(array $dados, array $campos = ['senha']): array
    {
        // Verifica se é um array de arrays (lista de registros)
        if (isset($dados[0]) && is_array($dados[0])) {
            foreach ($dados as &$item) {
                foreach ($campos as $campo) {
                    unset($item[$campo]);
                }
            }
        } else {
            // Array simples (registro único)
            foreach ($campos as $campo) {
                unset($dados[$campo]);
            }
        }

        return $dados;
    }

    /**
     * Valida se um recurso existe
     *
     * @param mixed $recurso Recurso a validar
     * @param string $nomeTipo Nome do tipo de recurso para mensagem de erro
     * @return bool True se existe, false e envia erro se não existe
     */
    protected function validarExistencia(mixed $recurso, string $nomeTipo = 'Recurso'): bool
    {
        if (!$recurso || (is_array($recurso) && empty($recurso))) {
            $this->naoEncontrado("{$nomeTipo} não encontrado");
            return false;
        }
        return true;
    }

    /**
     * Envia resposta de criação (201 Created)
     *
     * @param mixed $dados Dados do recurso criado
     * @param string $mensagem Mensagem de sucesso
     */
    protected function criado(mixed $dados = null, string $mensagem = 'Recurso criado com sucesso'): void
    {
        AuxiliarResposta::sucesso($dados, $mensagem, 201);
    }

    /**
     * Merge de múltiplos arrays de forma segura
     *
     * @param array ...$arrays Arrays para fazer merge
     * @return array Array resultante do merge
     */
    protected function mergeArrays(array ...$arrays): array
    {
        $resultado = [];
        foreach ($arrays as $array) {
            $resultado = array_merge($resultado, $array);
        }
        return $resultado;
    }

    /**
     * Verifica se a requisição contém um determinado campo
     *
     * @param string $campo Nome do campo
     * @param string $metodo Método HTTP (GET, POST, etc). Se null, verifica ambos
     * @return bool True se o campo existe
     */
    protected function temCampo(string $campo, ?string $metodo = null): bool
    {
        if ($metodo === 'GET' || $metodo === null) {
            if (isset($_GET[$campo])) {
                return true;
            }
        }

        if ($metodo === 'POST' || $metodo === null) {
            $dados = $this->obterDados();
            if (isset($dados[$campo])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converte string para booleano
     *
     * @param mixed $valor Valor a converter
     * @return bool Valor booleano
     */
    protected function paraBooleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            return in_array(strtolower($valor), ['true', '1', 'yes', 'sim']);
        }

        return (bool) $valor;
    }

    /**
     * Prepara dados para auditoria
     * Remove campos sensíveis e prepara para log
     *
     * @param array $dados Dados a preparar
     * @return array Dados seguros para auditoria
     */
    protected function prepararParaAuditoria(array $dados): array
    {
        $dadosAuditoria = $this->removerCamposSensiveis($dados, [
            'senha',
            'password',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret'
        ]);

        return $dadosAuditoria;
    }
}
