<?php

namespace App\Services\Mip;

use RuntimeException;

class Crypto
{
    /**
     * Generate a new RSA key pair (2048 bit)
     */
    public static function generateKeyPair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        if (!$resource) {
            throw new RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        $publicKey = $details['key'];

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Sign data with private key using SHA256
     */
    public static function sign(string $data, string $privateKey): string
    {
        $signature = '';
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new RuntimeException('Failed to sign data: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Verify signature with public key
     */
    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        $decodedSignature = base64_decode($signature);
        $result = openssl_verify($data, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Calculate MD5 fingerprint of a public key
     */
    public static function fingerprint(string $publicKey): string
    {
        // Normalize the public key by removing headers/whitespace
        $normalized = preg_replace('/-----[^-]+-----/', '', $publicKey);
        $normalized = preg_replace('/\s+/', '', $normalized);

        return md5($normalized);
    }
}
