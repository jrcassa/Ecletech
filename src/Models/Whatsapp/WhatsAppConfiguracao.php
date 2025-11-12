<?php

namespace Models\Whatsapp;

use PDO;

class WhatsAppConfiguracao
{
    private $conn;
    private $table = 'whatsapp_configuracoes';
    private $cache = [];

    public function __construct($db)
    {
        $this->conn = $db;
        $this->carregarCache();
    }

    /**
     * Carrega todas as configurações em cache
     */
    private function carregarCache()
    {
        $query = "SELECT chave, valor, tipo FROM {$this->table}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->cache[$row['chave']] = $this->converterTipo($row['valor'], $row['tipo']);
        }
    }

    /**
     * Obter valor de configuração
     */
    public function obter($chave, $padrao = null)
    {
        if (isset($this->cache[$chave])) {
            return $this->cache[$chave];
        }

        // Se não está no cache, busca no banco
        $query = "SELECT valor, tipo FROM {$this->table} WHERE chave = :chave";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chave', $chave);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $valor = $this->converterTipo($row['valor'], $row['tipo']);
            $this->cache[$chave] = $valor;
            return $valor;
        }

        return $padrao;
    }

    /**
     * Salvar configuração
     */
    public function salvar($chave, $valor)
    {
        $query = "UPDATE {$this->table} SET valor = :valor WHERE chave = :chave";
        $stmt = $this->conn->prepare($query);

        // Se for array/objeto, converte para JSON
        if (is_array($valor) || is_object($valor)) {
            $valor = json_encode($valor);
        } elseif (is_bool($valor)) {
            $valor = $valor ? 'true' : 'false';
        }

        $stmt->bindParam(':valor', $valor);
        $stmt->bindParam(':chave', $chave);

        if ($stmt->execute()) {
            // Atualiza cache
            $this->cache[$chave] = $this->converterTipo($valor, $this->obterTipo($chave));
            return true;
        }
        return false;
    }

    /**
     * Salvar múltiplas configurações
     */
    public function salvarMultiplas($configs)
    {
        $this->conn->beginTransaction();

        try {
            foreach ($configs as $chave => $valor) {
                $this->salvar($chave, $valor);
            }
            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Obter todas as configurações
     */
    public function obterTodas()
    {
        return $this->cache;
    }

    /**
     * Obter configurações por categoria (prefixo)
     */
    public function obterPorCategoria($prefixo)
    {
        $resultado = [];
        foreach ($this->cache as $chave => $valor) {
            if (strpos($chave, $prefixo) === 0) {
                $resultado[$chave] = $valor;
            }
        }
        return $resultado;
    }

    /**
     * Obter tipo de uma configuração
     */
    private function obterTipo($chave)
    {
        $query = "SELECT tipo FROM {$this->table} WHERE chave = :chave";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chave', $chave);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['tipo'] ?? 'string';
    }

    /**
     * Converter valor conforme tipo
     */
    private function converterTipo($valor, $tipo)
    {
        switch ($tipo) {
            case 'int':
                return (int) $valor;
            case 'bool':
                return $valor === 'true' || $valor === '1' || $valor === 1;
            case 'json':
                return json_decode($valor, true) ?? [];
            default:
                return (string) $valor;
        }
    }

    /**
     * Validar se está dentro do horário permitido
     */
    public function dentroHorarioPermitido()
    {
        $pausaNoturna = $this->obter('antiban_pausa_noturna', false);

        if (!$pausaNoturna) {
            return true;
        }

        $horaAtual = date('H:i');
        $horaInicio = $this->obter('antiban_horario_inicio', '08:00');
        $horaFim = $this->obter('antiban_horario_fim', '22:00');

        return ($horaAtual >= $horaInicio && $horaAtual <= $horaFim);
    }

    /**
     * Obter intervalo randomizado (anti-ban)
     */
    public function obterIntervaloAleatorio()
    {
        $aleatorizar = $this->obter('antiban_aleatorizar', true);
        $minimo = $this->obter('antiban_intervalo_minimo', 1);
        $maximo = $this->obter('antiban_intervalo_maximo', 5);

        if ($aleatorizar && $maximo > $minimo) {
            return rand($minimo, $maximo);
        }

        return $this->obter('fila_intervalo_entre_mensagens', 2);
    }

    /**
     * Verificar se atingiu limite de envios
     */
    public function verificarLimites()
    {
        $limiteHora = $this->obter('limite_mensagens_por_hora', 0);
        $limiteDia = $this->obter('limite_mensagens_por_dia', 0);

        if ($limiteHora == 0 && $limiteDia == 0) {
            return ['permitido' => true];
        }

        // Conta mensagens enviadas
        $queryHora = "SELECT COUNT(*) as total FROM whatsapp_queue
                      WHERE status = 'enviado'
                      AND processado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";

        $queryDia = "SELECT COUNT(*) as total FROM whatsapp_queue
                     WHERE status = 'enviado'
                     AND DATE(processado_em) = CURDATE()";

        $stmtHora = $this->conn->prepare($queryHora);
        $stmtHora->execute();
        $totalHora = $stmtHora->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtDia = $this->conn->prepare($queryDia);
        $stmtDia->execute();
        $totalDia = $stmtDia->fetch(PDO::FETCH_ASSOC)['total'];

        $permitido = true;
        $mensagem = '';

        if ($limiteHora > 0 && $totalHora >= $limiteHora) {
            $permitido = false;
            $mensagem = "Limite de {$limiteHora} mensagens por hora atingido";
        }

        if ($limiteDia > 0 && $totalDia >= $limiteDia) {
            $permitido = false;
            $mensagem = "Limite de {$limiteDia} mensagens por dia atingido";
        }

        return [
            'permitido' => $permitido,
            'mensagem' => $mensagem,
            'total_hora' => $totalHora,
            'total_dia' => $totalDia
        ];
    }

    /**
     * Recarregar cache
     */
    public function recarregarCache()
    {
        $this->cache = [];
        $this->carregarCache();
    }
}
