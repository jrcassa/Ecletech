<?php

namespace App\Core;

/**
 * Classe para gerenciar usuários
 */
class GerenciadorUsuario
{
    private BancoDados $db;
    private Configuracao $config;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->config = Configuracao::obterInstancia();
    }

    /**
     * Cria um novo usuário
     */
    public function criar(array $dados): int
    {
        // Valida os dados
        $this->validarDados($dados);

        // Verifica se o email já existe
        if ($this->emailExiste($dados['email'])) {
            throw new \RuntimeException("Email já cadastrado");
        }

        // Hash da senha
        $senhaHash = $this->hashSenha($dados['senha']);

        // Prepara os dados para inserção
        $dadosInsercao = [
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha' => $senhaHash,
            'nivel_id' => $dados['nivel_id'] ?? 1,
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Insere o usuário
        return $this->db->inserir('administradores', $dadosInsercao);
    }

    /**
     * Atualiza um usuário
     */
    public function atualizar(int $id, array $dados): bool
    {
        // Verifica se o usuário existe
        $usuario = $this->buscarPorId($id);
        if (!$usuario) {
            throw new \RuntimeException("Usuário não encontrado");
        }

        // Prepara os dados para atualização
        $dadosAtualizacao = [];

        if (isset($dados['nome'])) {
            $dadosAtualizacao['nome'] = $dados['nome'];
        }

        if (isset($dados['email'])) {
            // Verifica se o novo email já existe (exceto para o próprio usuário)
            if ($dados['email'] !== $usuario['email'] && $this->emailExiste($dados['email'])) {
                throw new \RuntimeException("Email já cadastrado");
            }
            $dadosAtualizacao['email'] = $dados['email'];
        }

        if (isset($dados['senha'])) {
            $this->validarSenha($dados['senha']);
            $dadosAtualizacao['senha'] = $this->hashSenha($dados['senha']);
        }

        if (isset($dados['nivel_id'])) {
            $dadosAtualizacao['nivel_id'] = $dados['nivel_id'];
        }

        if (isset($dados['ativo'])) {
            $dadosAtualizacao['ativo'] = $dados['ativo'] ? 1 : 0;
        }

        if (!empty($dadosAtualizacao)) {
            $dadosAtualizacao['atualizado_em'] = date('Y-m-d H:i:s');
            $this->db->atualizar('administradores', $dadosAtualizacao, 'id = ?', [$id]);
        }

        return true;
    }

    /**
     * Deleta um usuário (soft delete)
     */
    public function deletar(int $id): bool
    {
        return $this->db->atualizar(
            'administradores',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Busca um usuário por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT id, nome, email, nivel_id, ativo, criado_em, ultimo_login FROM administradores WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um usuário por email
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->buscarUm(
            "SELECT id, nome, email, nivel_id, ativo, criado_em, ultimo_login FROM administradores WHERE email = ?",
            [$email]
        );
    }

    /**
     * Lista todos os usuários
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT id, nome, email, nivel_id, ativo, criado_em, ultimo_login FROM administradores WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['nivel_id'])) {
            $sql .= " AND nivel_id = ?";
            $parametros[] = $filtros['nivel_id'];
        }

        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR email LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $sql .= " ORDER BY nome ASC";

        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Altera a senha do usuário
     */
    public function alterarSenha(int $id, string $senhaAtual, string $novaSenha): bool
    {
        // Busca o usuário com a senha
        $usuario = $this->db->buscarUm(
            "SELECT senha FROM administradores WHERE id = ?",
            [$id]
        );

        if (!$usuario) {
            throw new \RuntimeException("Usuário não encontrado");
        }

        // Verifica a senha atual
        if (!password_verify($senhaAtual, $usuario['senha'])) {
            throw new \RuntimeException("Senha atual incorreta");
        }

        // Valida a nova senha
        $this->validarSenha($novaSenha);

        // Atualiza a senha
        $senhaHash = $this->hashSenha($novaSenha);
        return $this->db->atualizar(
            'administradores',
            ['senha' => $senhaHash, 'atualizado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Valida os dados do usuário
     */
    private function validarDados(array $dados): void
    {
        if (empty($dados['nome'])) {
            throw new \InvalidArgumentException("Nome é obrigatório");
        }

        if (empty($dados['email'])) {
            throw new \InvalidArgumentException("Email é obrigatório");
        }

        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email inválido");
        }

        if (empty($dados['senha'])) {
            throw new \InvalidArgumentException("Senha é obrigatória");
        }

        $this->validarSenha($dados['senha']);
    }

    /**
     * Valida a senha
     */
    private function validarSenha(string $senha): void
    {
        $minTamanho = $this->config->obter('seguranca.senha_min_tamanho', 8);

        if (strlen($senha) < $minTamanho) {
            throw new \InvalidArgumentException("Senha deve ter no mínimo {$minTamanho} caracteres");
        }

        if ($this->config->obter('seguranca.senha_requer_maiuscula', true) && !preg_match('/[A-Z]/', $senha)) {
            throw new \InvalidArgumentException("Senha deve conter pelo menos uma letra maiúscula");
        }

        if ($this->config->obter('seguranca.senha_requer_minuscula', true) && !preg_match('/[a-z]/', $senha)) {
            throw new \InvalidArgumentException("Senha deve conter pelo menos uma letra minúscula");
        }

        if ($this->config->obter('seguranca.senha_requer_numero', true) && !preg_match('/[0-9]/', $senha)) {
            throw new \InvalidArgumentException("Senha deve conter pelo menos um número");
        }

        if ($this->config->obter('seguranca.senha_requer_especial', true) && !preg_match('/[^A-Za-z0-9]/', $senha)) {
            throw new \InvalidArgumentException("Senha deve conter pelo menos um caractere especial");
        }
    }

    /**
     * Gera hash da senha
     */
    private function hashSenha(string $senha): string
    {
        return password_hash($senha, PASSWORD_ARGON2ID);
    }

    /**
     * Verifica se o email já existe
     */
    private function emailExiste(string $email): bool
    {
        $usuario = $this->db->buscarUm(
            "SELECT id FROM administradores WHERE email = ?",
            [$email]
        );

        return $usuario !== null;
    }

    /**
     * Conta o total de usuários
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM administradores WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['nivel_id'])) {
            $sql .= " AND nivel_id = ?";
            $parametros[] = $filtros['nivel_id'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }
}
