<?php
/**
 * UUID Helper Class
 * 
 * Provides UUID v4 generation
 */

namespace App\Helpers;

class UuidHelper
{
    /**
     * Generate UUID v4
     * 
     * @return string
     */
    public static function generate(): string
    {
        $data = random_bytes(16);
        
        // Set version (4) and variant bits
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Format as UUID v4
        return sprintf('%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
    
    /**
     * Validate UUID format
     * 
     * @param string $uuid
     * @return bool
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid) === 1;
    }
    
    /**
     * Generate a short UUID (first 8 characters)
     * 
     * @return string
     */
    public static function generateShort(): string
    {
        return substr(str_replace('-', '', self::generate()), 0, 8);
    }
}