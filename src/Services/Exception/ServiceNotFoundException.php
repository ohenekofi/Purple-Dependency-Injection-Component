<?php

namespace Purple\Core\Services\Exception;

use Exception;
use Throwable;

/**
 * ServiceNotFoundException
 *
 * This exception is thrown when a requested service is not found in the service container.
 */
class ServiceNotFoundException extends Exception
{
    /**
     * The service ID that was not found.
     *
     * @var string
     */
    protected $serviceId;

    /**
     * Constructor
     *
     * @param string $serviceId The ID of the service that could not be found.
     * @param string $message   The exception message.
     * @param int    $code      The exception code.
     * @param Throwable|null $previous The previous exception, if any.
     */
    public function __construct(string $serviceId, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->serviceId = $serviceId;

        // If no custom message is provided, use a default one.
        if (empty($message)) {
            $message = sprintf("<error>Error:</error> The service <service>%s</service> could not be found in the service container.", $serviceId);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the service ID that was not found.
     *
     * @return string
     */
    public function getServiceId(): string
    {
        return $this->serviceId;
    }
}