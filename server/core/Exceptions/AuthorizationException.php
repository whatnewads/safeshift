<?php
/**
 * Authorization Exception
 * 
 * Thrown when a user lacks the required permissions to access a resource
 * Used for HIPAA-compliant access control enforcement
 */

namespace Core\Exceptions;

class AuthorizationException extends \Exception
{
    /**
     * @var string The resource that was being accessed
     */
    private string $resource;
    
    /**
     * @var string|null The required permission that was missing
     */
    private ?string $requiredPermission;
    
    /**
     * Create a new Authorization Exception
     *
     * @param string $message The exception message
     * @param string $resource The resource being accessed (optional)
     * @param string|null $requiredPermission The permission that was required (optional)
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable for exception chaining
     */
    public function __construct(
        string $message = "Access denied",
        string $resource = '',
        ?string $requiredPermission = null,
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->resource = $resource;
        $this->requiredPermission = $requiredPermission;
    }
    
    /**
     * Get the resource that was being accessed
     *
     * @return string
     */
    public function getResource(): string
    {
        return $this->resource;
    }
    
    /**
     * Get the required permission
     *
     * @return string|null
     */
    public function getRequiredPermission(): ?string
    {
        return $this->requiredPermission;
    }
    
    /**
     * Get a detailed error message including resource and permission info
     *
     * @return string
     */
    public function getDetailedMessage(): string
    {
        $details = $this->getMessage();
        
        if ($this->resource) {
            $details .= " (Resource: {$this->resource})";
        }
        
        if ($this->requiredPermission) {
            $details .= " (Required permission: {$this->requiredPermission})";
        }
        
        return $details;
    }
}