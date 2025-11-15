<?php

namespace App\CRM\Core;

/**
 * Exceção customizada para operações de CRM
 */
class CrmException extends \Exception
{
    private ?array $context;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, ?array $context = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Retorna o contexto adicional da exceção
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Converte a exceção para array (útil para logs)
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context
        ];
    }
}
