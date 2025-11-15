<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Cliente
 * Ecletech <-> GestãoClick
 *
 * Baseado na estrutura real da API GestãoClick
 */
class ClienteHandler
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Transforma dados do Ecletech para formato GestaoClick
     */
    public function transformarParaExterno(array $cliente): array
    {
        // Estrutura real da API GestãoClick (conforme Postman)
        $dados = [
            'tipo_pessoa' => $cliente['tipo_pessoa'] ?? 'PF', // PF, PJ, ES
            'nome' => $cliente['nome'] ?? '',
            'razao_social' => $cliente['razao_social'] ?? '',
            'cnpj' => $this->formatarCnpj($cliente['cnpj'] ?? ''),
            'cpf' => $this->formatarCpf($cliente['cpf'] ?? ''),
            'rg' => $cliente['rg'] ?? '',
            'inscricao_estadual' => $cliente['inscricao_estadual'] ?? '',
            'inscricao_municipal' => $cliente['inscricao_municipal'] ?? '',
            'data_nascimento' => $cliente['data_nascimento'] ?? '',
            'telefone' => $this->formatarTelefone($cliente['telefone'] ?? ''),
            'celular' => $this->formatarTelefone($cliente['celular'] ?? ''),
            'fax' => $cliente['fax'] ?? '',
            'email' => $cliente['email'] ?? '',
            'ativo' => !empty($cliente['ativo']) ? '1' : '0',
            'usuario_id' => $cliente['usuario_id'] ?? '',
            'loja_id' => $cliente['loja_id'] ?? '',
        ];

        // Endereços (array de objetos com estrutura específica)
        if (!empty($cliente['enderecos']) || isset($cliente['cep'])) {
            $enderecos = [];

            // Se vier como array de endereços
            if (!empty($cliente['enderecos']) && is_array($cliente['enderecos'])) {
                foreach ($cliente['enderecos'] as $end) {
                    $enderecos[] = [
                        'endereco' => [
                            'cep' => $this->formatarCep($end['cep'] ?? ''),
                            'logradouro' => $end['logradouro'] ?? $end['endereco'] ?? '',
                            'numero' => $end['numero'] ?? '',
                            'complemento' => $end['complemento'] ?? '',
                            'bairro' => $end['bairro'] ?? '',
                            'cidade_id' => $end['cidade_id'] ?? '',
                            'nome_cidade' => $end['nome_cidade'] ?? $end['cidade'] ?? '',
                            'estado' => $end['estado'] ?? ''
                        ]
                    ];
                }
            }
            // Se vier como campos diretos (legado Ecletech)
            elseif (!empty($cliente['cep'])) {
                $enderecos[] = [
                    'endereco' => [
                        'cep' => $this->formatarCep($cliente['cep']),
                        'logradouro' => $cliente['logradouro'] ?? $cliente['endereco'] ?? '',
                        'numero' => $cliente['numero'] ?? '',
                        'complemento' => $cliente['complemento'] ?? '',
                        'bairro' => $cliente['bairro'] ?? '',
                        'cidade_id' => $cliente['cidade_id'] ?? '',
                        'nome_cidade' => $cliente['cidade'] ?? '',
                        'estado' => $cliente['estado'] ?? ''
                    ]
                ];
            }

            if (!empty($enderecos)) {
                $dados['enderecos'] = $enderecos;
            }
        }

        // Contatos (array de objetos com estrutura específica)
        if (!empty($cliente['contatos']) && is_array($cliente['contatos'])) {
            $contatos = [];
            foreach ($cliente['contatos'] as $cont) {
                $contatos[] = [
                    'contato' => [
                        'nome' => $cont['nome'] ?? '',
                        'contato' => $cont['contato'] ?? $cont['email'] ?? $cont['telefone'] ?? '',
                        'cargo' => $cont['cargo'] ?? '',
                        'observacao' => $cont['observacao'] ?? ''
                    ]
                ];
            }
            $dados['contatos'] = $contatos;
        }

        return $dados;
    }

    /**
     * Transforma dados do GestaoClick para formato Ecletech
     */
    public function transformarParaInterno(array $clienteCrm): array
    {
        $dados = [
            'external_id' => (string) $clienteCrm['id'],
            'tipo_pessoa' => $clienteCrm['tipo_pessoa'] ?? 'PF',
            'nome' => $clienteCrm['nome'] ?? '',
            'razao_social' => $clienteCrm['razao_social'] ?? '',
            'cpf' => $this->limparDocumento($clienteCrm['cpf'] ?? ''),
            'cnpj' => $this->limparDocumento($clienteCrm['cnpj'] ?? ''),
            'rg' => $clienteCrm['rg'] ?? '',
            'inscricao_estadual' => $clienteCrm['inscricao_estadual'] ?? '',
            'inscricao_municipal' => $clienteCrm['inscricao_municipal'] ?? '',
            'data_nascimento' => $clienteCrm['data_nascimento'] ?? null,
            'telefone' => $this->limparTelefone($clienteCrm['telefone'] ?? ''),
            'celular' => $this->limparTelefone($clienteCrm['celular'] ?? ''),
            'fax' => $clienteCrm['fax'] ?? '',
            'email' => $clienteCrm['email'] ?? '',
            'ativo' => $clienteCrm['ativo'] === '1' ? 1 : 0,
        ];

        // Endereços
        if (!empty($clienteCrm['enderecos']) && is_array($clienteCrm['enderecos'])) {
            $enderecos = [];
            foreach ($clienteCrm['enderecos'] as $endObj) {
                $end = $endObj['endereco'] ?? $endObj;
                $enderecos[] = [
                    'cep' => $this->limparCep($end['cep'] ?? ''),
                    'logradouro' => $end['logradouro'] ?? '',
                    'numero' => $end['numero'] ?? '',
                    'complemento' => $end['complemento'] ?? '',
                    'bairro' => $end['bairro'] ?? '',
                    'cidade_id' => $end['cidade_id'] ?? '',
                    'cidade' => $end['nome_cidade'] ?? '',
                    'estado' => $end['estado'] ?? ''
                ];
            }
            $dados['enderecos'] = $enderecos;

            // Também adiciona o primeiro endereço como campos diretos (legado)
            if (!empty($enderecos[0])) {
                $dados = array_merge($dados, $enderecos[0]);
            }
        }

        // Contatos
        if (!empty($clienteCrm['contatos']) && is_array($clienteCrm['contatos'])) {
            $contatos = [];
            foreach ($clienteCrm['contatos'] as $contObj) {
                $cont = $contObj['contato'] ?? $contObj;
                $contatos[] = [
                    'nome' => $cont['nome'] ?? '',
                    'contato' => $cont['contato'] ?? '',
                    'cargo' => $cont['cargo'] ?? '',
                    'observacao' => $cont['observacao'] ?? ''
                ];
            }
            $dados['contatos'] = $contatos;
        }

        return $dados;
    }

    /**
     * Formata CPF para envio (XXX.XXX.XXX-XX)
     */
    private function formatarCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' .
                   substr($cpf, 3, 3) . '.' .
                   substr($cpf, 6, 3) . '-' .
                   substr($cpf, 9, 2);
        }

        return $cpf;
    }

    /**
     * Formata CNPJ para envio (XX.XXX.XXX/XXXX-XX)
     */
    private function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' .
                   substr($cnpj, 2, 3) . '.' .
                   substr($cnpj, 5, 3) . '/' .
                   substr($cnpj, 8, 4) . '-' .
                   substr($cnpj, 12, 2);
        }

        return $cnpj;
    }

    /**
     * Formata telefone para envio ((XX) XXXXX-XXXX)
     */
    private function formatarTelefone(string $telefone): string
    {
        $telefone = preg_replace('/\D/', '', $telefone);

        if (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' .
                   substr($telefone, 2, 5) . '-' .
                   substr($telefone, 7, 4);
        } elseif (strlen($telefone) === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' .
                   substr($telefone, 2, 4) . '-' .
                   substr($telefone, 6, 4);
        }

        return $telefone;
    }

    /**
     * Formata CEP para envio (XXXXX-XXX)
     */
    private function formatarCep(string $cep): string
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) === 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }

        return $cep;
    }

    /**
     * Remove formatação de documento
     */
    private function limparDocumento(string $documento): string
    {
        return preg_replace('/\D/', '', $documento);
    }

    /**
     * Remove formatação de telefone
     */
    private function limparTelefone(string $telefone): string
    {
        return preg_replace('/\D/', '', $telefone);
    }

    /**
     * Remove formatação de CEP
     */
    private function limparCep(string $cep): string
    {
        return preg_replace('/\D/', '', $cep);
    }
}
