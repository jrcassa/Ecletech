<?php

namespace Database\Seeds;

use App\Core\BancoDados;
use Faker\Factory;
use Faker\Generator;

/**
 * Classe base para todos os seeders
 * Fornece métodos utilitários para geração de dados fake
 */
abstract class BaseSeeder
{
    protected BancoDados $db;
    protected Generator $faker;
    protected bool $verbose = true;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->faker = Factory::create('pt_BR');
    }

    /**
     * Método principal que deve ser implementado por cada seeder
     */
    abstract public function run(): void;

    /**
     * Exibe mensagem no console
     */
    protected function info(string $message): void
    {
        if ($this->verbose) {
            echo "[INFO] " . date('H:i:s') . " - $message\n";
        }
    }

    /**
     * Exibe mensagem de sucesso
     */
    protected function success(string $message): void
    {
        if ($this->verbose) {
            echo "[✓] " . date('H:i:s') . " - $message\n";
        }
    }

    /**
     * Exibe mensagem de erro
     */
    protected function error(string $message): void
    {
        echo "[✗] " . date('H:i:s') . " - $message\n";
    }

    /**
     * Exibe mensagem de aviso
     */
    protected function warning(string $message): void
    {
        if ($this->verbose) {
            echo "[!] " . date('H:i:s') . " - $message\n";
        }
    }

    /**
     * Limpa uma tabela
     */
    protected function truncate(string $tabela): void
    {
        try {
            $this->info("Limpando tabela '$tabela'...");
            $this->db->executar("SET FOREIGN_KEY_CHECKS = 0");
            $this->db->executar("TRUNCATE TABLE `$tabela`");
            $this->db->executar("SET FOREIGN_KEY_CHECKS = 1");
            $this->success("Tabela '$tabela' limpa com sucesso");
        } catch (\Exception $e) {
            $this->error("Erro ao limpar tabela '$tabela': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deleta registros soft-deleted de uma tabela
     */
    protected function cleanSoftDeletes(string $tabela): void
    {
        try {
            $this->info("Limpando registros soft-deleted da tabela '$tabela'...");
            $this->db->executar("DELETE FROM `$tabela` WHERE deletado_em IS NOT NULL");
            $this->success("Registros soft-deleted removidos de '$tabela'");
        } catch (\Exception $e) {
            $this->warning("Não foi possível limpar soft deletes de '$tabela': " . $e->getMessage());
        }
    }

    /**
     * Conta quantos registros existem em uma tabela
     */
    protected function count(string $tabela): int
    {
        $resultado = $this->db->buscarUm("SELECT COUNT(*) as total FROM `$tabela`", []);
        return (int) $resultado['total'];
    }

    /**
     * Verifica se uma tabela existe
     */
    protected function tableExists(string $tabela): bool
    {
        try {
            $resultado = $this->db->buscarUm(
                "SELECT COUNT(*) as existe
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = ?",
                [$tabela]
            );
            return (int) $resultado['existe'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gera um CPF válido
     */
    protected function generateCPF(): string
    {
        $n = array_map(function () {
            return rand(0, 9);
        }, range(1, 9));

        // Primeiro dígito verificador
        $d1 = 0;
        for ($i = 0; $i < 9; $i++) {
            $d1 += $n[$i] * (10 - $i);
        }
        $d1 = 11 - ($d1 % 11);
        $d1 = ($d1 >= 10) ? 0 : $d1;

        // Segundo dígito verificador
        $d2 = 0;
        for ($i = 0; $i < 9; $i++) {
            $d2 += $n[$i] * (11 - $i);
        }
        $d2 += $d1 * 2;
        $d2 = 11 - ($d2 % 11);
        $d2 = ($d2 >= 10) ? 0 : $d2;

        return sprintf(
            '%d%d%d.%d%d%d.%d%d%d-%d%d',
            $n[0], $n[1], $n[2],
            $n[3], $n[4], $n[5],
            $n[6], $n[7], $n[8],
            $d1, $d2
        );
    }

    /**
     * Gera um CNPJ válido
     */
    protected function generateCNPJ(): string
    {
        $n = array_map(function () {
            return rand(0, 9);
        }, range(1, 12));

        // Primeiro dígito verificador
        $peso = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += $n[$i] * $peso[$i];
        }
        $d1 = $soma % 11;
        $d1 = ($d1 < 2) ? 0 : 11 - $d1;

        // Segundo dígito verificador
        $peso = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += $n[$i] * $peso[$i];
        }
        $soma += $d1 * $peso[12];
        $d2 = $soma % 11;
        $d2 = ($d2 < 2) ? 0 : 11 - $d2;

        return sprintf(
            '%d%d.%d%d%d.%d%d%d/%d%d%d%d-%d%d',
            $n[0], $n[1],
            $n[2], $n[3], $n[4],
            $n[5], $n[6], $n[7],
            $n[8], $n[9], $n[10], $n[11],
            $d1, $d2
        );
    }

    /**
     * Gera um CEP válido
     */
    protected function generateCEP(): string
    {
        return sprintf('%05d-%03d', rand(1000, 99999), rand(0, 999));
    }

    /**
     * Gera uma data aleatória entre dois intervalos
     */
    protected function randomDate(string $startDate = '-2 years', string $endDate = 'now'): string
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $timestamp = rand($start, $end);
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Gera um UUID v4
     */
    protected function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Executa em lote para melhor performance
     */
    protected function batchInsert(string $tabela, array $registros, int $batchSize = 100): void
    {
        $total = count($registros);
        $batches = array_chunk($registros, $batchSize);

        foreach ($batches as $index => $batch) {
            $this->db->transacao(function () use ($tabela, $batch) {
                foreach ($batch as $registro) {
                    $this->db->inserir($tabela, $registro);
                }
            });

            $progresso = min(($index + 1) * $batchSize, $total);
            $this->info("Inseridos $progresso de $total registros em '$tabela'");
        }
    }

    /**
     * Define se deve exibir mensagens verbosas
     */
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }
}
