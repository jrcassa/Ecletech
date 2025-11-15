<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Cliente
 * Ecletech <-> GestãoClick
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
        $dados = [
            'name' => $cliente['nome'] ?? $cliente['razao_social'] ?? '',
            'email' => $cliente['email'] ?? null,
            'phone' => $this->formatarTelefone($cliente['telefone'] ?? ''),
        ];

        // Tipo de pessoa e documento
        if (!empty($cliente['tipo_pessoa'])) {
            $dados['person_type'] = $cliente['tipo_pessoa'] === 'PF' ? 'individual' : 'company';

            if ($cliente['tipo_pessoa'] === 'PF' && !empty($cliente['cpf'])) {
                $dados['document'] = $this->formatarCpf($cliente['cpf']);
            } elseif ($cliente['tipo_pessoa'] === 'PJ' && !empty($cliente['cnpj'])) {
                $dados['document'] = $this->formatarCnpj($cliente['cnpj']);
            }
        }

        // Endereço
        if (!empty($cliente['endereco'])) {
            $dados['address'] = [
                'street' => $cliente['endereco'] ?? '',
                'number' => $cliente['numero'] ?? '',
                'complement' => $cliente['complemento'] ?? '',
                'district' => $cliente['bairro'] ?? '',
                'city' => $cliente['cidade'] ?? '',
                'state' => $cliente['estado'] ?? '',
                'zip_code' => $this->formatarCep($cliente['cep'] ?? '')
            ];
        }

        // Informações adicionais
        if (!empty($cliente['observacoes'])) {
            $dados['notes'] = $cliente['observacoes'];
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
            'nome' => $clienteCrm['name'] ?? '',
            'email' => $clienteCrm['email'] ?? null,
            'telefone' => $this->limparTelefone($clienteCrm['phone'] ?? ''),
        ];

        // Tipo de pessoa e documento
        if (!empty($clienteCrm['person_type'])) {
            $dados['tipo_pessoa'] = $clienteCrm['person_type'] === 'individual' ? 'PF' : 'PJ';

            if ($clienteCrm['person_type'] === 'individual' && !empty($clienteCrm['document'])) {
                $dados['cpf'] = $this->limparDocumento($clienteCrm['document']);
            } elseif ($clienteCrm['person_type'] === 'company' && !empty($clienteCrm['document'])) {
                $dados['cnpj'] = $this->limparDocumento($clienteCrm['document']);
                $dados['razao_social'] = $clienteCrm['name'] ?? '';
            }
        }

        // Endereço
        if (!empty($clienteCrm['address'])) {
            $address = $clienteCrm['address'];
            $dados['endereco'] = $address['street'] ?? '';
            $dados['numero'] = $address['number'] ?? '';
            $dados['complemento'] = $address['complement'] ?? '';
            $dados['bairro'] = $address['district'] ?? '';
            $dados['cidade'] = $address['city'] ?? '';
            $dados['estado'] = $address['state'] ?? '';
            $dados['cep'] = $this->limparCep($address['zip_code'] ?? '');
        }

        // Informações adicionais
        if (!empty($clienteCrm['notes'])) {
            $dados['observacoes'] = $clienteCrm['notes'];
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
