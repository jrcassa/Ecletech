<?php

namespace App\Models\Loja;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar informações da loja
 * IMPORTANTE: Esta tabela mantém apenas 1 registro (Singleton)
 */
class ModelLoja
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Obtém as informações da loja (único registro)
     *
     * @return array|null Retorna os dados da loja ou null se não encontrado
     */
    public function obter(): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM loja_informacoes WHERE ativo = 1 AND deletado_em IS NULL LIMIT 1"
        );
    }

    /**
     * Obtém informações da loja por ID específico
     *
     * @param int $id ID da loja
     * @return array|null Retorna os dados da loja ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM loja_informacoes WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Atualiza as informações da loja
     *
     * @param int $id ID do registro a ser atualizado
     * @param array $dados Dados a serem atualizados
     * @param int|null $usuarioId ID do usuário que está realizando a alteração
     * @return bool Retorna true se a atualização foi bem-sucedida
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'uuid',
            'external_id',
            'nome_fantasia',
            'razao_social',
            'cnpj',
            'inscricao_estadual',
            'inscricao_municipal',
            'email',
            'telefone',
            'celular',
            'site',
            'responsavel',
            'cpf_responsavel',
            'endereco_logradouro',
            'endereco_numero',
            'endereco_complemento',
            'endereco_bairro',
            'endereco_cidade',
            'endereco_uf',
            'endereco_cep',
            'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('loja_informacoes', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'loja_informacoes',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um CNPJ já existe (exceto para o ID informado)
     *
     * @param string $cnpj CNPJ a ser verificado
     * @param int|null $excluirId ID a ser excluído da verificação
     * @return bool Retorna true se o CNPJ já existe
     */
    public function cnpjExiste(string $cnpj, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM loja_informacoes WHERE cnpj = ? AND deletado_em IS NULL";
        $parametros = [$cnpj];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um CPF de responsável já existe (exceto para o ID informado)
     *
     * @param string $cpf CPF a ser verificado
     * @param int|null $excluirId ID a ser excluído da verificação
     * @return bool Retorna true se o CPF já existe
     */
    public function cpfResponsavelExiste(string $cpf, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM loja_informacoes WHERE cpf_responsavel = ? AND deletado_em IS NULL";
        $parametros = [$cpf];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém o ID do único registro ativo da loja
     *
     * @return int|null Retorna o ID ou null se não encontrado
     */
    public function obterIdUnico(): ?int
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM loja_informacoes WHERE ativo = 1 AND deletado_em IS NULL LIMIT 1"
        );

        return $resultado ? (int) $resultado['id'] : null;
    }

    /**
     * Obtém o external_id da loja (para integração CRM)
     *
     * @return string|null Retorna o external_id ou null se não configurado
     */
    public function obterExternalId(): ?string
    {
        $resultado = $this->db->buscarUm(
            "SELECT external_id FROM loja_informacoes WHERE ativo = 1 AND deletado_em IS NULL LIMIT 1"
        );

        return $resultado['external_id'] ?? null;
    }

    /**
     * Valida os dados da loja antes de salvar
     *
     * @param array $dados Dados a serem validados
     * @param int|null $idAtual ID do registro atual (para update)
     * @return array Retorna array com 'valido' (bool) e 'erros' (array)
     */
    public function validar(array $dados, ?int $idAtual = null): array
    {
        $erros = [];

        // Validação de nome_fantasia
        if (empty($dados['nome_fantasia'])) {
            $erros[] = 'Nome fantasia é obrigatório';
        } elseif (strlen($dados['nome_fantasia']) > 150) {
            $erros[] = 'Nome fantasia deve ter no máximo 150 caracteres';
        }

        // Validação de razão_social
        if (empty($dados['razao_social'])) {
            $erros[] = 'Razão social é obrigatória';
        } elseif (strlen($dados['razao_social']) > 200) {
            $erros[] = 'Razão social deve ter no máximo 200 caracteres';
        }

        // Validação de CNPJ
        if (empty($dados['cnpj'])) {
            $erros[] = 'CNPJ é obrigatório';
        } else {
            // Remove caracteres não numéricos
            $cnpjLimpo = preg_replace('/[^0-9]/', '', $dados['cnpj']);

            if (strlen($cnpjLimpo) !== 14) {
                $erros[] = 'CNPJ deve conter 14 dígitos';
            } elseif ($this->cnpjExiste($cnpjLimpo, $idAtual)) {
                $erros[] = 'CNPJ já cadastrado';
            }
        }

        // Validação de email
        if (!empty($dados['email'])) {
            if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'Email inválido';
            } elseif (strlen($dados['email']) > 150) {
                $erros[] = 'Email deve ter no máximo 150 caracteres';
            }
        }

        // Validação de CPF do responsável
        if (!empty($dados['cpf_responsavel'])) {
            $cpfLimpo = preg_replace('/[^0-9]/', '', $dados['cpf_responsavel']);

            if (strlen($cpfLimpo) !== 11) {
                $erros[] = 'CPF do responsável deve conter 11 dígitos';
            }
        }

        // Validação de UF
        if (!empty($dados['endereco_uf'])) {
            if (strlen($dados['endereco_uf']) !== 2) {
                $erros[] = 'UF deve conter 2 caracteres';
            }
        }

        // Validação de CEP
        if (!empty($dados['endereco_cep'])) {
            $cepLimpo = preg_replace('/[^0-9]/', '', $dados['endereco_cep']);

            if (strlen($cepLimpo) !== 8) {
                $erros[] = 'CEP deve conter 8 dígitos';
            }
        }

        // Validação de site
        if (!empty($dados['site'])) {
            if (strlen($dados['site']) > 150) {
                $erros[] = 'Site deve ter no máximo 150 caracteres';
            }
        }

        return [
            'valido' => count($erros) === 0,
            'erros' => $erros
        ];
    }
}
