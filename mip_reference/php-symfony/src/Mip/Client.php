<?php

namespace App\Mip;

use App\Mip\Model\CogsRequest;
use App\Mip\Model\Endorsement;
use App\Mip\Model\NodeIdentity;
use App\Mip\Model\SearchRequest;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for making outbound MIP requests
 */
class Client
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private NodeIdentity $identity
    ) {}

    /**
     * Request a connection with another node
     */
    public function requestConnection(string $targetUrl, array $endorsements = []): array
    {
        $payload = [
            'mip_identifier' => $this->identity->mipIdentifier,
            'mip_url' => $this->identity->mipUrl,
            'public_key' => $this->identity->publicKey,
            'organization_legal_name' => $this->identity->organizationName,
            'contact_person' => $this->identity->contactPerson,
            'contact_phone' => $this->identity->contactPhone,
            'share_my_organization' => $this->identity->shareMyOrganization,
            'endorsements' => array_map(fn(Endorsement $e) => $e->toPayload(), $endorsements),
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_connections'), $payload, true);
    }

    /**
     * Notify a node their connection request was approved
     */
    public function approveConnection(string $targetUrl, array $nodeProfile, int $dailyRateLimit = 100): array
    {
        $payload = [
            'node_profile' => $nodeProfile,
            'share_my_organization' => $this->identity->shareMyOrganization,
            'daily_rate_limit' => $dailyRateLimit,
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_connections/approved'), $payload);
    }

    /**
     * Notify a node their connection request was declined
     */
    public function declineConnection(string $targetUrl, ?string $reason = null): array
    {
        $payload = [
            'mip_identifier' => $this->identity->mipIdentifier,
            'reason' => $reason,
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_connections/declined'), $payload);
    }

    /**
     * Notify a node their connection has been revoked
     */
    public function revokeConnection(string $targetUrl, ?string $reason = null): array
    {
        $payload = [
            'mip_identifier' => $this->identity->mipIdentifier,
            'reason' => $reason,
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_connections/revoke'), $payload);
    }

    /**
     * Notify a node their connection has been restored
     */
    public function restoreConnection(string $targetUrl): array
    {
        $payload = [
            'mip_identifier' => $this->identity->mipIdentifier,
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_connections/restore'), $payload);
    }

    /**
     * Send an endorsement to another node
     */
    public function sendEndorsement(string $targetUrl, Endorsement $endorsement): array
    {
        return $this->postRequest($this->buildUrl($targetUrl, '/endorsements'), $endorsement->toPayload());
    }

    /**
     * Send a member search request
     */
    public function memberSearch(string $targetUrl, SearchRequest $searchRequest): array
    {
        return $this->postRequest(
            $this->buildUrl($targetUrl, '/mip_member_searches'),
            $searchRequest->toRequestPayload()
        );
    }

    /**
     * Send member search results back to requester
     */
    public function memberSearchReply(string $targetUrl, SearchRequest $searchRequest): array
    {
        $payload = [
            'meta' => ['succeeded' => true],
            'data' => $searchRequest->toReplyPayload(),
        ];

        return $this->postRequest($this->buildUrl($targetUrl, '/mip_member_searches/reply'), $payload);
    }

    /**
     * Request a Certificate of Good Standing
     */
    public function requestCogs(string $targetUrl, CogsRequest $cogsRequest): array
    {
        return $this->postRequest(
            $this->buildUrl($targetUrl, '/certificates_of_good_standing'),
            $cogsRequest->toRequestPayload()
        );
    }

    /**
     * Send COGS reply back to requester
     */
    public function cogsReply(string $targetUrl, CogsRequest $cogsRequest): array
    {
        return $this->postRequest(
            $this->buildUrl($targetUrl, '/certificates_of_good_standing/reply'),
            $cogsRequest->toReplyPayload()
        );
    }

    /**
     * Query connected organizations
     */
    public function connectedOrganizationsQuery(string $targetUrl): array
    {
        return $this->getRequest($this->buildUrl($targetUrl, '/connected_organizations_query'));
    }

    private function postRequest(string $url, array $payload, bool $includePublicKey = false): array
    {
        $timestamp = (new \DateTime())->format(\DateTime::ATOM);
        $path = parse_url($url, PHP_URL_PATH);
        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = Signature::signRequest($this->identity->privateKey, $timestamp, $path, $jsonBody);

        $headers = [
            'Content-Type' => 'application/json',
            'X-MIP-MIP-IDENTIFIER' => $this->identity->mipIdentifier,
            'X-MIP-TIMESTAMP' => $timestamp,
            'X-MIP-SIGNATURE' => $signature,
        ];

        if ($includePublicKey) {
            $headers['X-MIP-PUBLIC-KEY'] = base64_encode($this->identity->publicKey);
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $jsonBody,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'body' => $content !== '' ? json_decode($content, true) : [],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function getRequest(string $url): array
    {
        $timestamp = (new \DateTime())->format(\DateTime::ATOM);
        $path = parse_url($url, PHP_URL_PATH);
        $signature = Signature::signRequest($this->identity->privateKey, $timestamp, $path);

        $headers = [
            'X-MIP-MIP-IDENTIFIER' => $this->identity->mipIdentifier,
            'X-MIP-TIMESTAMP' => $timestamp,
            'X-MIP-SIGNATURE' => $signature,
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'body' => $content !== '' ? json_decode($content, true) : [],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/') . $endpoint;
    }
}
