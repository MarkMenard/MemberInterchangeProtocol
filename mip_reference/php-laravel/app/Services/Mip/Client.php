<?php

namespace App\Services\Mip;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Client
{
    private Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Make a signed MIP request
     */
    public function request(string $method, string $url, ?array $body = null, bool $includePublicKey = false, int $timeout = 10): array
    {
        $identity = $this->store->getIdentity();
        $path = parse_url($url, PHP_URL_PATH);

        $headers = Signature::sign($path, $body, $identity['private_key'], $identity['mip_identifier']);
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        if ($includePublicKey) {
            $headers['X-MIP-PUBLIC-KEY'] = base64_encode($identity['public_key']);
        }

        Log::info("MIP Client: {$method} {$url}", ['headers' => array_keys($headers)]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->connectTimeout(5)
                ->{strtolower($method)}($url, $body);

            if (!$response->successful()) {
                Log::error("MIP Client Error: {$response->status()}", [
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::warning("MIP Client Exception: {$e->getMessage()}");
            return [
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Request a connection to another node
     */
    public function requestConnection(string $targetMipUrl): array
    {
        $identity = $this->store->getIdentity();

        // First, fetch the target's node profile
        $profileResponse = Http::get("{$targetMipUrl}/profile");
        if (!$profileResponse->successful()) {
            return ['success' => false, 'error' => 'Failed to fetch node profile'];
        }

        $profile = $profileResponse->json()['data'] ?? $profileResponse->json();
        $targetMipId = $profile['mip_identifier'];

        // Prepare endorsements from our active connections
        $endorsements = $this->gatherEndorsements($targetMipId);

        $body = [
            'node_profile' => [
                'mip_identifier' => $identity['mip_identifier'],
                'mip_url' => $identity['mip_url'],
                'organization_legal_name' => $identity['organization_name'],
                'contact_person' => $identity['contact_person'],
                'contact_phone' => $identity['contact_phone'],
                'share_my_organization' => $identity['share_my_organization'],
                'public_key' => $identity['public_key'],
            ],
            'endorsements' => $endorsements,
        ];

        $url = "{$targetMipUrl}/mip/node/{$targetMipId}/mip_connections";

        return $this->request('POST', $url, $body, true);
    }

    /**
     * Notify connection approval
     */
    public function notifyApproval(array $connection): array
    {
        $identity = $this->store->getIdentity();
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $body = [
            'node_profile' => [
                'mip_identifier' => $identity['mip_identifier'],
                'mip_url' => $identity['mip_url'],
                'organization_legal_name' => $identity['organization_name'],
                'contact_person' => $identity['contact_person'],
                'contact_phone' => $identity['contact_phone'],
                'share_my_organization' => $identity['share_my_organization'],
                'public_key' => $identity['public_key'],
            ],
            'daily_rate_limit' => 100,
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/mip_connections/approved";

        return $this->request('POST', $url, $body);
    }

    /**
     * Notify connection decline
     */
    public function notifyDecline(array $connection): array
    {
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/mip_connections/declined";

        return $this->request('POST', $url, []);
    }

    /**
     * Send endorsement to a connected node
     */
    public function sendEndorsement(array $connection): array
    {
        $identity = $this->store->getIdentity();
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        // Create endorsement: sign their public key + fingerprint
        $fingerprint = Crypto::fingerprint($connection['public_key']);
        $dataToSign = $connection['public_key'] . $fingerprint;
        $signature = Crypto::sign($dataToSign, $identity['private_key']);

        $body = [
            'endorser_mip_identifier' => $identity['mip_identifier'],
            'endorsed_mip_identifier' => $targetMipId,
            'endorsed_public_key_fingerprint' => $fingerprint,
            'signature' => $signature,
            'expires_at' => now()->addYear()->toIso8601String(),
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/endorsements";

        return $this->request('POST', $url, $body);
    }

    /**
     * Send a member search request
     */
    public function memberSearch(array $connection, array $criteria, string $requestId): array
    {
        $identity = $this->store->getIdentity();
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $body = [
            'request_id' => $requestId,
            'search_criteria' => $criteria,
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/mip_member_searches";

        return $this->request('POST', $url, $body);
    }

    /**
     * Send member search reply
     */
    public function memberSearchReply(array $connection, string $requestId, array $results): array
    {
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $body = [
            'request_id' => $requestId,
            'results' => $results,
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/mip_member_searches/reply";

        return $this->request('POST', $url, $body);
    }

    /**
     * Request Certificate of Good Standing
     */
    public function requestCogs(array $connection, string $memberNumber, string $requestId): array
    {
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $body = [
            'request_id' => $requestId,
            'member_number' => $memberNumber,
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/certificates_of_good_standing";

        return $this->request('POST', $url, $body);
    }

    /**
     * Send COGS reply
     */
    public function cogsReply(array $connection, string $requestId, ?array $memberProfile, bool $approved): array
    {
        $targetMipId = $connection['mip_identifier'];
        $targetUrl = $connection['mip_url'];

        $body = [
            'request_id' => $requestId,
            'approved' => $approved,
            'member_profile' => $memberProfile,
            'issued_at' => now()->toIso8601String(),
            'valid_until' => now()->addDays(30)->toIso8601String(),
        ];

        $url = "{$targetUrl}/mip/node/{$targetMipId}/certificates_of_good_standing/reply";

        return $this->request('POST', $url, $body);
    }

    /**
     * Gather endorsements from active connections for the target
     */
    private function gatherEndorsements(string $targetMipId): array
    {
        $endorsements = [];
        $connections = $this->store->getConnections();

        foreach ($connections as $conn) {
            if ($conn['status'] === 'ACTIVE') {
                // Get any endorsement we have from this connection
                $stored = $this->store->getEndorsementsFor($this->store->getIdentity()['mip_identifier']);
                foreach ($stored as $endorsement) {
                    if ($endorsement['endorser_mip_identifier'] === $conn['mip_identifier']) {
                        $endorsements[] = $endorsement;
                    }
                }
            }
        }

        return $endorsements;
    }
}
