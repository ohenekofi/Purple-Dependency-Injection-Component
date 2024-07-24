<?php
namespace Purple\Core\Services\Exception;
use Exception;

class ServiceAccessException extends Exception
{
    private $serviceId;

    public function __construct(string $serviceId, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->serviceId = $serviceId;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function __toString()
    {
        $message = parent::__toString();
        return "ServiceAccessException: Service with ID '{$this->serviceId}' is inaccessible. " . $message;
    }
}
