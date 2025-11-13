<?php

namespace App\Helpers;

use App\Helpers\ErrorLogger;

/**
 * Classe auxiliar para manipulação de datas
 */
class AuxiliarData
{
    /**
     * Formata data para exibição
     */
    public static function formatar(string $data, string $formato = 'd/m/Y'): string
    {
        try {
            $dt = new \DateTime($data);
            return $dt->format($formato);
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'formatar_data',
                'data' => $data,
                'formato' => $formato
            ]);
            return $data;
        }
    }

    /**
     * Formata data e hora para exibição
     */
    public static function formatarDataHora(string $dataHora, string $formato = 'd/m/Y H:i:s'): string
    {
        return self::formatar($dataHora, $formato);
    }

    /**
     * Converte data do formato brasileiro para banco de dados
     */
    public static function paraBanco(string $data): string
    {
        try {
            // Tenta formato brasileiro dd/mm/yyyy
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            }

            // Se já estiver no formato do banco, retorna como está
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                return $data;
            }

            $dt = new \DateTime($data);
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'converter_data_para_banco',
                'data' => $data
            ]);
            return $data;
        }
    }

    /**
     * Converte data do banco para formato brasileiro
     */
    public static function paraBrasileiro(string $data): string
    {
        return self::formatar($data, 'd/m/Y');
    }

    /**
     * Obtém a data atual
     */
    public static function agora(string $formato = 'Y-m-d H:i:s'): string
    {
        return date($formato);
    }

    /**
     * Obtém a data atual sem hora
     */
    public static function hoje(string $formato = 'Y-m-d'): string
    {
        return date($formato);
    }

    /**
     * Obtém a data de ontem
     */
    public static function ontem(string $formato = 'Y-m-d'): string
    {
        return date($formato, strtotime('-1 day'));
    }

    /**
     * Obtém a data de amanhã
     */
    public static function amanha(string $formato = 'Y-m-d'): string
    {
        return date($formato, strtotime('+1 day'));
    }

    /**
     * Adiciona dias a uma data
     */
    public static function adicionarDias(string $data, int $dias): string
    {
        try {
            $dt = new \DateTime($data);
            $dt->modify("+{$dias} days");
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'adicionar_dias',
                'data' => $data,
                'dias' => $dias
            ]);
            return $data;
        }
    }

    /**
     * Subtrai dias de uma data
     */
    public static function subtrairDias(string $data, int $dias): string
    {
        try {
            $dt = new \DateTime($data);
            $dt->modify("-{$dias} days");
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'subtrair_dias',
                'data' => $data,
                'dias' => $dias
            ]);
            return $data;
        }
    }

    /**
     * Adiciona meses a uma data
     */
    public static function adicionarMeses(string $data, int $meses): string
    {
        try {
            $dt = new \DateTime($data);
            $dt->modify("+{$meses} months");
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'adicionar_meses',
                'data' => $data,
                'meses' => $meses
            ]);
            return $data;
        }
    }

    /**
     * Subtrai meses de uma data
     */
    public static function subtrairMeses(string $data, int $meses): string
    {
        try {
            $dt = new \DateTime($data);
            $dt->modify("-{$meses} months");
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'subtrair_meses',
                'data' => $data,
                'meses' => $meses
            ]);
            return $data;
        }
    }

    /**
     * Calcula a diferença entre duas datas em dias
     */
    public static function diferencaDias(string $data1, string $data2): int
    {
        try {
            $dt1 = new \DateTime($data1);
            $dt2 = new \DateTime($data2);
            $diff = $dt1->diff($dt2);
            return (int) $diff->days;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'diferenca_dias',
                'data1' => $data1,
                'data2' => $data2
            ]);
            return 0;
        }
    }

    /**
     * Verifica se uma data é maior que outra
     */
    public static function maior(string $data1, string $data2): bool
    {
        try {
            $dt1 = new \DateTime($data1);
            $dt2 = new \DateTime($data2);
            return $dt1 > $dt2;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'comparar_maior',
                'data1' => $data1,
                'data2' => $data2
            ]);
            return false;
        }
    }

    /**
     * Verifica se uma data é menor que outra
     */
    public static function menor(string $data1, string $data2): bool
    {
        try {
            $dt1 = new \DateTime($data1);
            $dt2 = new \DateTime($data2);
            return $dt1 < $dt2;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'comparar_menor',
                'data1' => $data1,
                'data2' => $data2
            ]);
            return false;
        }
    }

    /**
     * Verifica se uma data está entre duas outras
     */
    public static function entre(string $data, string $inicio, string $fim): bool
    {
        try {
            $dt = new \DateTime($data);
            $dtInicio = new \DateTime($inicio);
            $dtFim = new \DateTime($fim);
            return $dt >= $dtInicio && $dt <= $dtFim;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'data_entre',
                'data' => $data,
                'inicio' => $inicio,
                'fim' => $fim
            ]);
            return false;
        }
    }

    /**
     * Obtém o nome do mês
     */
    public static function nomeMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];

        return $meses[$mes] ?? '';
    }

    /**
     * Obtém o nome do dia da semana
     */
    public static function nomeDiaSemana(int $dia): string
    {
        $dias = [
            0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira',
            3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira',
            6 => 'Sábado'
        ];

        return $dias[$dia] ?? '';
    }

    /**
     * Obtém o timestamp de uma data
     */
    public static function timestamp(string $data): int
    {
        try {
            $dt = new \DateTime($data);
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'obter_timestamp',
                'data' => $data
            ]);
            return 0;
        }
    }

    /**
     * Converte timestamp para data
     */
    public static function deTimestamp(int $timestamp, string $formato = 'Y-m-d H:i:s'): string
    {
        return date($formato, $timestamp);
    }

    /**
     * Verifica se é fim de semana
     */
    public static function fimDeSemana(string $data): bool
    {
        try {
            $dt = new \DateTime($data);
            $diaSemana = (int) $dt->format('w');
            return in_array($diaSemana, [0, 6]); // 0 = Domingo, 6 = Sábado
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'verificar_fim_de_semana',
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Obtém o primeiro dia do mês
     */
    public static function primeiroDiaMes(string $data): string
    {
        try {
            $dt = new \DateTime($data);
            return $dt->format('Y-m-01');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'primeiro_dia_mes',
                'data' => $data
            ]);
            return $data;
        }
    }

    /**
     * Obtém o último dia do mês
     */
    public static function ultimoDiaMes(string $data): string
    {
        try {
            $dt = new \DateTime($data);
            return $dt->format('Y-m-t');
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'ultimo_dia_mes',
                'data' => $data
            ]);
            return $data;
        }
    }

    /**
     * Calcula a idade
     */
    public static function idade(string $dataNascimento): int
    {
        try {
            $nascimento = new \DateTime($dataNascimento);
            $hoje = new \DateTime();
            $idade = $hoje->diff($nascimento);
            return (int) $idade->y;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'calcular_idade',
                'data_nascimento' => $dataNascimento
            ]);
            return 0;
        }
    }

    /**
     * Formata data por extenso
     */
    public static function porExtenso(string $data): string
    {
        try {
            $dt = new \DateTime($data);
            $dia = $dt->format('d');
            $mes = self::nomeMes((int) $dt->format('m'));
            $ano = $dt->format('Y');

            return "{$dia} de {$mes} de {$ano}";
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'data_por_extenso',
                'data' => $data
            ]);
            return $data;
        }
    }

    /**
     * Retorna data relativa (ex: "há 2 dias")
     */
    public static function relativa(string $data): string
    {
        try {
            $dt = new \DateTime($data);
            $agora = new \DateTime();
            $diff = $agora->diff($dt);

            if ($diff->y > 0) {
                return $diff->y === 1 ? 'há 1 ano' : "há {$diff->y} anos";
            }

            if ($diff->m > 0) {
                return $diff->m === 1 ? 'há 1 mês' : "há {$diff->m} meses";
            }

            if ($diff->d > 0) {
                if ($diff->d === 1) return 'ontem';
                return "há {$diff->d} dias";
            }

            if ($diff->h > 0) {
                return $diff->h === 1 ? 'há 1 hora' : "há {$diff->h} horas";
            }

            if ($diff->i > 0) {
                return $diff->i === 1 ? 'há 1 minuto' : "há {$diff->i} minutos";
            }

            return 'agora';
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'data_relativa',
                'data' => $data
            ]);
            return $data;
        }
    }

    /**
     * Valida se uma data é válida
     */
    public static function valida(string $data, string $formato = 'Y-m-d'): bool
    {
        try {
            $dt = \DateTime::createFromFormat($formato, $data);
            return $dt && $dt->format($formato) === $data;
        } catch (\Exception $e) {
            ErrorLogger::log($e, 'validacao', 'baixo', [
                'contexto' => 'validar_data',
                'data' => $data,
                'formato' => $formato
            ]);
            return false;
        }
    }
}
