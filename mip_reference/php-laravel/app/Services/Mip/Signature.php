<?php

namespace App\Services\Mip;

use Carbon\Carbon;

class Signature
{
    /**
     * Sign a MIP request
     *
     * @param string $path The request path (e.g., /mip/node/{id}/mip_connections)
     * @param array|null $body The JSON body (will be encoded)
     * @param string $privateKey The private key to sign with
     * @return array Headers to include in the request
     */
    public static function sign(string $path, ?array $body, string $privateKey, string $mipIdentifier): array
    {
        $timestamp = Carbon::now('UTC')->toIso8601String();
        $jsonBody = $body ? json_encode($body) : '';

        $dataToSign = $timestamp . $path . $jsonBody;
        $signature = Crypto::sign($dataToSign, $privateKey);

        return [
            'X-MIP-MIP-IDENTIFIER' => $mipIdentifier,
            'X-MIP-TIMESTAMP' => $timestamp,
            'X-MIP-SIGNATURE' => $signature,
        ];
    }

    /**
     * Verify a MIP request signature
     *
     * @param string $path The request path
     * @param string|null $body The raw JSON body
     * @param string $timestamp The timestamp from headers
     * @param string $signature The signature from headers
     * @param string $publicKey The public key to verify with
     * @return bool
     */
    public static function verify(
        string $path,
        ?string $body,
        string $timestamp,
        string $signature,
        string $publicKey
    ): bool {
        // Verify timestamp is within 5 minute window
        $requestTime = Carbon::parse($timestamp);
        $now = Carbon::now('UTC');

        if (abs($now->diffInSeconds($requestTime)) > 300) {
            return false;
        }

        $dataToVerify = $timestamp . $path . ($body ?? '');

        return Crypto::verify($dataToVerify, $signature, $publicKey);
    }
}
