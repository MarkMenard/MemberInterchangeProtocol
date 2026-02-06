<?php

namespace App\Services\Mip;

use Ramsey\Uuid\Uuid;

class Identifier
{
    /**
     * Generate a 128-bit MIP identifier using MD5(UUID + organization_name)
     */
    public static function generate(string $organizationName): string
    {
        $uuid = Uuid::uuid4()->toString();
        return md5($uuid . $organizationName);
    }
}
