<?php

namespace Helpers;

class WhatsAppStatus
{
    // Status codes do WhatsApp
    const STATUS_ERRO = 0;
    const STATUS_PENDENTE = 1;      // Mensagem criada, aguardando envio
    const STATUS_ENVIADO = 2;       // Enviado para o servidor WhatsApp
    const STATUS_ENTREGUE = 3;      // Entregue no dispositivo do destinatário
    const STATUS_LIDO = 4;          // Lido pelo destinatário

    // Mapa de status da API para nosso código
    private static $statusMap = [
        'ERROR' => self::STATUS_ERRO,
        'PENDING' => self::STATUS_PENDENTE,
        'SENT' => self::STATUS_ENVIADO,
        'SERVER_ACK' => self::STATUS_ENVIADO,
        'DELIVERY_ACK' => self::STATUS_ENTREGUE,
        'READ' => self::STATUS_LIDO,
        'PLAYED' => self::STATUS_LIDO
    ];

    // Mapa de status code numérico para nome
    private static $statusNames = [
        0 => 'ERRO',
        1 => 'PENDENTE',
        2 => 'ENVIADO',
        3 => 'ENTREGUE',
        4 => 'LIDO'
    ];

    // Mapa de status code numérico (do webhook) para nosso código
    private static $webhookStatusMap = [
        0 => self::STATUS_ERRO,
        1 => self::STATUS_PENDENTE,
        2 => self::STATUS_ENVIADO,
        3 => self::STATUS_ENTREGUE,    // status:3 do webhook = entregue
        4 => self::STATUS_LIDO          // status:4 do webhook = lido
    ];

    /**
     * Converte status da API para código numérico
     */
    public static function paraStatusCode($statusApi)
    {
        $statusUpper = strtoupper($statusApi);
        return self::$statusMap[$statusUpper] ?? self::STATUS_ERRO;
    }

    /**
     * Converte código numérico para nome
     */
    public static function paraNome($statusCode)
    {
        return self::$statusNames[$statusCode] ?? 'DESCONHECIDO';
    }

    /**
     * Converte status do webhook para nosso código
     */
    public static function webhookParaStatusCode($webhookStatus)
    {
        return self::$webhookStatusMap[$webhookStatus] ?? self::STATUS_ERRO;
    }

    /**
     * Retorna classe CSS para badge
     */
    public static function getClasseBadge($statusCode)
    {
        $classes = [
            self::STATUS_ERRO => 'badge bg-danger',
            self::STATUS_PENDENTE => 'badge bg-warning text-dark',
            self::STATUS_ENVIADO => 'badge bg-info text-white',
            self::STATUS_ENTREGUE => 'badge bg-primary',
            self::STATUS_LIDO => 'badge bg-success'
        ];
        return $classes[$statusCode] ?? 'badge bg-secondary';
    }

    /**
     * Retorna ícone para o status
     */
    public static function getIcone($statusCode)
    {
        $icones = [
            self::STATUS_ERRO => 'fas fa-exclamation-circle',
            self::STATUS_PENDENTE => 'fas fa-clock',
            self::STATUS_ENVIADO => 'fas fa-check',
            self::STATUS_ENTREGUE => 'fas fa-check-double',
            self::STATUS_LIDO => 'fas fa-check-double'
        ];
        return $icones[$statusCode] ?? 'fas fa-question-circle';
    }

    /**
     * Retorna HTML completo do badge
     */
    public static function getBadgeHtml($statusCode)
    {
        $classe = self::getClasseBadge($statusCode);
        $icone = self::getIcone($statusCode);
        $nome = self::paraNome($statusCode);

        $iconColor = $statusCode == self::STATUS_LIDO ? 'text-white' : '';

        return "<span class=\"{$classe}\"><i class=\"{$icone} {$iconColor}\"></i> {$nome}</span>";
    }
}
