<?php

namespace App\Mip;

/**
 * MIP request signature handling
 */
class Signature
{
    /**
     * Create a MIP request signature
     * Per spec: signature signs "timestamp + path + json_payload"
     */
    public static function signRequest(string $privateKeyPem, string $timestamp, string $path, ?string $jsonBody = null): string
    {
        $data = self::buildSignatureData($timestamp, $path, $jsonBody);
        return Crypto::sign($privateKeyPem, $data);
    }

    /**
     * Verify a MIP request signature
     */
    public static function verifyRequest(string $publicKeyPem, string $signature, string $timestamp, string $path, ?string $jsonBody = null): bool
    {
        $data = self::buildSignatureData($timestamp, $path, $jsonBody);
        return Crypto::verify($publicKeyPem, $signature, $data);
    }

    /**
     * Check if timestamp is within acceptable window (default ±5 minutes)
     */
    public static function timestampValid(string $timestamp, int $windowSeconds = 300): bool
    {
        try {
            $requestTime = new \DateTime($timestamp);
            $now = new \DateTime();
            $diff = abs($now->getTimestamp() - $requestTime->getTimestamp());
            return $diff <= $windowSeconds;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function buildSignatureData(string $timestamp, string $path, ?string $jsonBody): string
    {
        $data = $timestamp . $path;
        if ($jsonBody !== null && $jsonBody !== '') {
            $data .= $jsonBody;
        }
        return $data;
    }
}
