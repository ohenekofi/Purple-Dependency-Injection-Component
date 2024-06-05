<?php
namespace Purple\Core\Exceptions;

use InvalidArgumentException;

class ServiceNotFoundException extends InvalidArgumentException
{
    public function __construct($message = "Service not found", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return "<h2>Error: " . $this->getMessage() . "</h2><p>Service not found.</p>";
    }
}