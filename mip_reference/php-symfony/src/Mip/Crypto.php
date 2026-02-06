<?php

namespace App\Mip;

/**
 * Cryptographic utilities for MIP protocol
 */
class Crypto
{
    /**
     * Generate a 2048-bit RSA key pair
     */
    public static function generateKeyPair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($resource);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKeyDetails['key'],
        ];
    }

    /**
     * Calculate MD5 fingerprint of a public key (colon-separated hex)
     */
    public static function fingerprint(string $publicKeyPem): string
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $details = openssl_pkey_get_details($publicKey);

        // Get the DER encoding from the key modulus and exponent
        $der = base64_decode(
            preg_replace(
                '/-----[A-Z ]+-----/',
                '',
                str_replace(["\r", "\n"], '', $publicKeyPem)
            )
        );

        $md5 = md5($der);

        // Format as colon-separated pairs
        return implode(':', str_split($md5, 2));
    }

    /**
     * Sign data with a private key using SHA256
     */
    public static function sign(string $privateKeyPem, string $data): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * Verify a signature using a public key
     */
    public static function verify(string $publicKeyPem, string $signatureBase64, string $data): bool
    {
        try {
            $publicKey = openssl_pkey_get_public($publicKeyPem);
            if (!$publicKey) {
                return false;
            }

            $signature = base64_decode($signatureBase64);
            $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            return $result === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
