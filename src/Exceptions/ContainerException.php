<?php
namespace Purple\Core\Exceptions;

use RuntimeException;

class ContainerException extends RuntimeException
{
    public function __construct($message = "Container error", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return "<h2>Error: " . $this->getMessage() . "</h2><p>Container error occurred.</p>";
    }
}