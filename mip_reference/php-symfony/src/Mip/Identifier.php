<?php

namespace App\Mip;

/**
 * MIP Identifier generation utilities
 */
class Identifier
{
    /**
     * Generate a MIP identifier per spec:
     * MD5 hash of UUID concatenated with organization name as salt
     */
    public static function generate(string $organizationName): string
    {
        $uuid = self::generateUuid();
        return md5($uuid . $organizationName);
    }

    /**
     * Generate a UUID v4
     */
    private static function generateUuid(): string
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
