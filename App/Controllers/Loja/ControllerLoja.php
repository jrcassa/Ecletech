<?php

namespace App\Controllers\Loja;

use App\Controllers\BaseController;

use App\Models\Loja\ModelLoja;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar informações da loja
 * IMPORTANTE: Esta tabela mantém apenas 1 registro (Singleton)
 */
class ControllerLoja extends BaseController
{
    private ModelLoja $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelLoja();
        $this->auth = new Autenticacao();
    }

    /**
     * Obtém as informações da loja (único registro)
     */
    public function obter(): void
    {
        try {
            $loja = $this->model->obter();

            if (!$loja) {
                $this->naoEncontrado('Informações da loja não encontradas');
                return;
            }

            $this->sucesso($loja, 'Informações da loja obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza as informações da loja
     */
    public function atualizar(): void
    {
        try {
            // Obtém o ID único da loja
            $idLoja = $this->model->obterIdUnico();

            if (!$idLoja) {
                $this->naoEncontrado('Registro da loja não encontrado');
                return;
            }

            $dados = $this->obterDados();

            // Sanitiza os dados
            $dados = $this->sanitizarDadosLoja($dados);

            // Validação dos dados usando o Model
            $validacao = $this->model->validar($dados, $idLoja);

            if (!$validacao['valido']) {
                $this->validacao($validacao['erros']);
                return;
            }

            // Validações adicionais usando AuxiliarValidacao
            $erros = [];

            // Validação de nome_fantasia
            if (isset($dados['nome_fantasia'])) {
                $errosTemp = AuxiliarValidacao::validar($dados, [
                    'nome_fantasia' => 'obrigatorio|min:3|max:150'
                ]);
                $erros = array_merge($erros, $errosTemp);
            }

            // Validação de razao_social
            if (isset($dados['razao_social'])) {
                $errosTemp = AuxiliarValidacao::validar($dados, [
                    'razao_social' => 'obrigatorio|min:3|max:200'
                ]);
                $erros = array_merge($erros, $errosTemp);
            }

            // Validação de CNPJ
            if (isset($dados['cnpj'])) {
                $errosTemp = AuxiliarValidacao::validar($dados, [
                    'cnpj' => 'obrigatorio|cnpj'
                ]);
                $erros = array_merge($erros, $errosTemp);
            }

            // Validação de email
            if (isset($dados['email']) && !empty($dados['email'])) {
                $errosTemp = AuxiliarValidacao::validar($dados, [
                    'email' => 'email|max:150'
                ]);
                $erros = array_merge($erros, $errosTemp);
            }

            // Validação de CPF do responsável
            if (isset($dados['cpf_responsavel']) && !empty($dados['cpf_responsavel'])) {
                $errosTemp = AuxiliarValidacao::validar($dados, [
                    'cpf_responsavel' => 'cpf'
                ]);
                $erros = array_merge($erros, $errosTemp);
            }

            // Validação de CEP
            if (isset($dados['endereco_cep']) && !empty($dados['endereco_cep'])) {
                $dados['endereco_cep'] = preg_replace('/[^0-9]/', '', $dados['endereco_cep']);
                if (strlen($dados['endereco_cep']) !== 8) {
                    $erros[] = 'CEP deve conter 8 dígitos';
                }
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Normaliza dados
            if (isset($dados['cnpj'])) {
                $dados['cnpj'] = preg_replace('/[^0-9]/', '', $dados['cnpj']);
            }

            if (isset($dados['cpf_responsavel'])) {
                $dados['cpf_responsavel'] = preg_replace('/[^0-9]/', '', $dados['cpf_responsavel']);
            }

            if (isset($dados['endereco_uf'])) {
                $dados['endereco_uf'] = strtoupper($dados['endereco_uf']);
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza as informações da loja
            $resultado = $this->model->atualizar($idLoja, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar informações da loja', 400);
                return;
            }

            $loja = $this->model->buscarPorId($idLoja);

            $this->sucesso($loja, 'Informações da loja atualizadas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados antes de processar
     *
     * @param array $dados Dados a serem sanitizados
     * @return array Dados sanitizados
     */
    private function sanitizarDadosLoja(array $dados): array
    {
        $camposTexto = [
            'nome_fantasia',
            'razao_social',
            'inscricao_estadual',
            'inscricao_municipal',
            'responsavel',
            'endereco_logradouro',
            'endereco_numero',
            'endereco_complemento',
            'endereco_bairro',
            'endereco_cidade'
        ];

        foreach ($camposTexto as $campo) {
            if (isset($dados[$campo]) && is_string($dados[$campo])) {
                $dados[$campo] = AuxiliarSanitizacao::string($dados[$campo]);
            }
        }

        // Sanitiza email
        if (isset($dados['email']) && is_string($dados['email'])) {
            $dados['email'] = AuxiliarSanitizacao::email($dados['email']);
        }

        // Sanitiza telefone e celular
        if (isset($dados['telefone']) && is_string($dados['telefone'])) {
            $dados['telefone'] = AuxiliarSanitizacao::telefone($dados['telefone']);
        }

        if (isset($dados['celular']) && is_string($dados['celular'])) {
            $dados['celular'] = AuxiliarSanitizacao::telefone($dados['celular']);
        }

        // Sanitiza site
        if (isset($dados['site']) && is_string($dados['site'])) {
            $dados['site'] = AuxiliarSanitizacao::url($dados['site']);
        }

        // Sanitiza CNPJ
        if (isset($dados['cnpj']) && is_string($dados['cnpj'])) {
            $dados['cnpj'] = AuxiliarSanitizacao::cnpj($dados['cnpj']);
        }

        // Sanitiza CPF do responsável
        if (isset($dados['cpf_responsavel']) && is_string($dados['cpf_responsavel'])) {
            $dados['cpf_responsavel'] = AuxiliarSanitizacao::cpf($dados['cpf_responsavel']);
        }

        // Sanitiza CEP
        if (isset($dados['endereco_cep']) && is_string($dados['endereco_cep'])) {
            $dados['endereco_cep'] = AuxiliarSanitizacao::cep($dados['endereco_cep']);
        }

        return $dados;
    }

    /**
     * Valida os dados da loja
     * Esta é uma validação adicional que pode ser usada antes de enviar para o model
     *
     * @param array $dados Dados a serem validados
     * @return array Array de erros (vazio se não houver erros)
     */
    private function validarDados(array $dados): array
    {
        $erros = [];

        // Validações básicas de formato
        if (isset($dados['telefone']) && !empty($dados['telefone'])) {
            $telefoneLimpo = preg_replace('/[^0-9]/', '', $dados['telefone']);
            if (strlen($telefoneLimpo) < 10 || strlen($telefoneLimpo) > 11) {
                $erros[] = 'Telefone deve ter 10 ou 11 dígitos';
            }
        }

        if (isset($dados['celular']) && !empty($dados['celular'])) {
            $celularLimpo = preg_replace('/[^0-9]/', '', $dados['celular']);
            if (strlen($celularLimpo) !== 11) {
                $erros[] = 'Celular deve ter 11 dígitos';
            }
        }

        if (isset($dados['site']) && !empty($dados['site'])) {
            if (!filter_var($dados['site'], FILTER_VALIDATE_URL)) {
                $erros[] = 'Site deve ser uma URL válida';
            }
        }

        if (isset($dados['endereco_uf']) && !empty($dados['endereco_uf'])) {
            if (strlen($dados['endereco_uf']) !== 2) {
                $erros[] = 'UF deve ter exatamente 2 caracteres';
            }
        }

        return $erros;
    }
}
