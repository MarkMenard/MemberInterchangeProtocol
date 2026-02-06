<?php

namespace App\Controller;

use App\Mip\Client;
use App\Mip\Crypto;
use App\Mip\Model\Connection;
use App\Mip\Model\CogsRequest;
use App\Mip\Model\Endorsement;
use App\Mip\Model\SearchRequest;
use App\Mip\Signature;
use App\Mip\Store;
use App\Mip\StoreFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MIP Protocol endpoint controller
 */
#[Route('/mip/node/{mipId}')]
class MipController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    private function getStore(Request $request): Store
    {
        $port = (int)$request->server->get('SERVER_PORT', 4013);
        return StoreFactory::initializeFromConfig($port);
    }

    private function getClient(Store $store): Client
    {
        return new Client($this->httpClient, $store->getNodeIdentity());
    }

    private function mipResponse(bool $succeeded, array $data = []): JsonResponse
    {
        return new JsonResponse([
            'meta' => ['succeeded' => $succeeded],
            'data' => $data,
        ]);
    }

    private function verifyMipRequest(Request $request, Store $store): array|JsonResponse
    {
        $mipId = $request->headers->get('X-MIP-MIP-IDENTIFIER');
        $timestamp = $request->headers->get('X-MIP-TIMESTAMP');
        $signature = $request->headers->get('X-MIP-SIGNATURE');
        $publicKeyHeader = $request->headers->get('X-MIP-PUBLIC-KEY');

        // Validate required headers
        if (!$mipId || !$timestamp || !$signature) {
            return $this->mipResponse(false, ['error' => 'Missing MIP headers']);
        }

        // Validate timestamp
        if (!Signature::timestampValid($timestamp)) {
            return $this->mipResponse(false, ['error' => 'Invalid timestamp']);
        }

        // Get public key - from connection or header
        $connection = $store->findConnection($mipId);
        $publicKey = null;

        if ($connection !== null) {
            $publicKey = $connection->publicKey;
        } elseif ($publicKeyHeader !== null) {
            $publicKey = base64_decode($publicKeyHeader);
        }

        if ($publicKey === null) {
            return $this->mipResponse(false, ['error' => 'Unknown sender']);
        }

        // Verify signature
        $body = $request->getContent();
        $path = $request->getPathInfo();

        if (!Signature::verifyRequest($publicKey, $signature, $timestamp, $path, $body ?: null)) {
            return $this->mipResponse(false, ['error' => 'Invalid signature']);
        }

        return [
            'mip_id' => $mipId,
            'connection' => $connection,
            'public_key' => $publicKey,
        ];
    }

    private function requireActiveConnection(array $sender): ?JsonResponse
    {
        if (!isset($sender['connection']) || !$sender['connection']->isActive()) {
            return $this->mipResponse(false, ['error' => 'No active connection']);
        }
        return null;
    }

    // ============================================================================
    // Connection Protocol
    // ============================================================================

    #[Route('/mip_connections', name: 'mip_connection_request', methods: ['POST'])]
    public function connectionRequest(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $payload = json_decode($request->getContent(), true);

        // Check if connection already exists
        $existing = $store->findConnection($payload['mip_identifier']);
        if ($existing !== null) {
            return $this->mipResponse(true, [
                'mip_connection' => [
                    'status' => $existing->status,
                    'daily_rate_limit' => $existing->dailyRateLimit,
                    'node_profile' => $store->getNodeIdentity()->toNodeProfile(),
                ],
            ]);
        }

        // Create new connection from request
        $connection = Connection::fromRequest($payload, 'inbound');

        // Check for auto-approval via web-of-trust
        $endorsements = $payload['endorsements'] ?? [];
        $trustedCount = $this->countTrustedEndorsements($store, $endorsements, $connection->publicKey);
        $identity = $store->getNodeIdentity();

        if ($trustedCount >= $identity->trustThreshold) {
            $connection->approve(dailyRateLimit: 100);
            $store->addConnection($connection);
            $store->logActivity("Auto-approved connection: {$connection->organizationName} ({$trustedCount} trusted endorsements)");

            // Send endorsement to new connection (async would be better but we'll do sync for simplicity)
            $this->sendEndorsementToConnection($store, $connection);

            return $this->mipResponse(true, [
                'mip_connection' => [
                    'status' => 'ACTIVE',
                    'daily_rate_limit' => 100,
                    'node_profile' => $identity->toNodeProfile(),
                ],
            ]);
        }

        $store->addConnection($connection);
        $store->logActivity("Connection request from: {$connection->organizationName}");

        return $this->mipResponse(true, [
            'mip_connection' => [
                'status' => 'PENDING',
                'daily_rate_limit' => 100,
                'node_profile' => $identity->toNodeProfile(),
            ],
        ]);
    }

    #[Route('/mip_connections/approved', name: 'mip_connection_approved', methods: ['POST'])]
    public function connectionApproved(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $connection = $store->findConnection($sender['mip_id']);
        if ($connection === null) {
            return $this->mipResponse(false, ['error' => 'Connection not found']);
        }

        $payload = json_decode($request->getContent(), true);
        $nodeProfile = $payload['node_profile'] ?? null;
        $dailyRateLimit = $payload['daily_rate_limit'] ?? 100;

        $connection->approve($nodeProfile, $dailyRateLimit);
        $store->saveConnection($connection);
        $store->logActivity("Connection approved by: {$connection->organizationName}");

        // Send endorsement to the approving node
        $this->sendEndorsementToConnection($store, $connection);

        return $this->mipResponse(true, [
            'mip_connection' => ['status' => 'ACTIVE'],
        ]);
    }

    #[Route('/mip_connections/declined', name: 'mip_connection_declined', methods: ['POST'])]
    public function connectionDeclined(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $connection = $store->findConnection($sender['mip_id']);
        if ($connection === null) {
            return $this->mipResponse(false, ['error' => 'Connection not found']);
        }

        $payload = json_decode($request->getContent(), true);
        $connection->decline($payload['reason'] ?? null);
        $store->saveConnection($connection);
        $store->logActivity("Connection declined by: {$connection->organizationName}");

        return $this->mipResponse(true, [
            'mip_connection' => ['status' => 'DECLINED'],
        ]);
    }

    #[Route('/mip_connections/revoke', name: 'mip_connection_revoke', methods: ['POST'])]
    public function connectionRevoke(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $connection = $store->findConnection($sender['mip_id']);
        if ($connection === null) {
            return $this->mipResponse(false, ['error' => 'Connection not found']);
        }

        $payload = json_decode($request->getContent(), true);
        $connection->revoke($payload['reason'] ?? null);
        $store->saveConnection($connection);
        $store->logActivity("Connection revoked by: {$connection->organizationName}");

        return $this->mipResponse(true, [
            'mip_connection' => ['status' => 'REVOKED'],
        ]);
    }

    #[Route('/mip_connections/restore', name: 'mip_connection_restore', methods: ['POST'])]
    public function connectionRestore(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $connection = $store->findConnection($sender['mip_id']);
        if ($connection === null) {
            return $this->mipResponse(false, ['error' => 'Connection not found']);
        }

        $connection->restore();
        $store->saveConnection($connection);
        $store->logActivity("Connection restored by: {$connection->organizationName}");

        return $this->mipResponse(true, [
            'mip_connection' => ['status' => 'ACTIVE'],
        ]);
    }

    // ============================================================================
    // Endorsements
    // ============================================================================

    #[Route('/endorsements', name: 'mip_endorsements', methods: ['POST'])]
    public function receiveEndorsement(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $endorsement = Endorsement::fromPayload($payload);

        // Verify the endorsement signature
        if (!$endorsement->verifySignature($sender['connection']->publicKey)) {
            return $this->mipResponse(false, ['error' => 'Invalid endorsement signature']);
        }

        $store->addEndorsement($endorsement);

        // Check if this endorsement enables any pending connections to be auto-approved
        $this->checkPendingConnectionsForAutoApproval($store);

        return $this->mipResponse(true, ['endorsement_id' => $endorsement->id]);
    }

    #[Route('/connected_organizations_query', name: 'mip_connected_organizations', methods: ['GET'])]
    public function connectedOrganizationsQuery(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $organizations = [];
        foreach ($store->getActiveConnections() as $connection) {
            if ($connection->shareMyOrganization && $connection->mipIdentifier !== $sender['mip_id']) {
                $organizations[] = $connection->toNodeProfile();
            }
        }

        return $this->mipResponse(true, ['organizations' => $organizations]);
    }

    // ============================================================================
    // Member Protocol
    // ============================================================================

    #[Route('/mip_member_searches', name: 'mip_member_search', methods: ['POST'])]
    public function memberSearch(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $search = SearchRequest::fromRequest(
            $payload,
            $sender['mip_id'],
            $sender['connection']->organizationName
        );

        $store->addSearchRequest($search);

        return $this->mipResponse(true, [
            'status' => 'PENDING',
            'shared_identifier' => $search->sharedIdentifier,
        ]);
    }

    #[Route('/mip_member_searches/reply', name: 'mip_member_search_reply', methods: ['POST'])]
    public function memberSearchReply(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $data = $payload['data'] ?? $payload;
        $sharedId = $data['shared_identifier'] ?? null;

        $search = $sharedId !== null ? $store->findSearchRequest($sharedId) : null;

        if ($search !== null) {
            if (($data['status'] ?? '') === 'APPROVED') {
                $search->approve($data['matches'] ?? []);
                $store->logActivity("Search results received: " . count($search->matches) . " matches");
            } else {
                $search->decline($data['reason'] ?? null);
                $store->logActivity("Search declined");
            }
            $store->saveSearchRequest($search);
        }

        return $this->mipResponse(true, ['acknowledged' => true]);
    }

    #[Route('/member_status_checks', name: 'mip_member_status_check', methods: ['POST'])]
    public function memberStatusCheck(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $memberNumber = $payload['member_number'] ?? null;
        $member = $memberNumber !== null ? $store->findMember($memberNumber) : null;

        if ($member !== null) {
            return $this->mipResponse(true, $member->toStatusCheck());
        }

        return $this->mipResponse(true, ['found' => false, 'member_number' => $memberNumber]);
    }

    // ============================================================================
    // COGS Protocol
    // ============================================================================

    #[Route('/certificates_of_good_standing', name: 'mip_cogs_request', methods: ['POST'])]
    public function cogsRequest(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $cogs = CogsRequest::fromRequest(
            $payload,
            $sender['mip_id'],
            $sender['connection']->organizationName
        );

        $store->addCogsRequest($cogs);

        return $this->mipResponse(true, [
            'status' => 'PENDING',
            'shared_identifier' => $cogs->sharedIdentifier,
        ]);
    }

    #[Route('/certificates_of_good_standing/reply', name: 'mip_cogs_reply', methods: ['POST'])]
    public function cogsReply(Request $request, string $mipId): JsonResponse
    {
        $store = $this->getStore($request);
        $sender = $this->verifyMipRequest($request, $store);

        if ($sender instanceof JsonResponse) {
            return $sender;
        }

        $error = $this->requireActiveConnection($sender);
        if ($error !== null) {
            return $error;
        }

        $payload = json_decode($request->getContent(), true);
        $sharedId = $payload['shared_identifier'] ?? null;
        $cogs = $sharedId !== null ? $store->findCogsRequest($sharedId) : null;

        if ($cogs !== null) {
            if (($payload['status'] ?? '') === 'APPROVED') {
                $cogs->status = 'APPROVED';
                $cogs->certificate = $payload;
                $memberNumber = $payload['member_profile']['member_number'] ?? 'unknown';
                $store->logActivity("COGS received for {$memberNumber}");
            } else {
                $cogs->status = 'DECLINED';
                $cogs->declineReason = $payload['reason'] ?? null;
                $store->logActivity("COGS declined: {$cogs->declineReason}");
            }
            $store->saveCogsRequest($cogs);
        }

        return $this->mipResponse(true, [
            'acknowledged' => true,
            'shared_identifier' => $sharedId,
        ]);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    private function sendEndorsementToConnection(Store $store, Connection $connection): void
    {
        if ($connection->publicKey === null) {
            return;
        }

        $endorsement = Endorsement::create(
            $store->getNodeIdentity(),
            $connection->mipIdentifier,
            $connection->publicKey
        );

        try {
            $client = $this->getClient($store);
            $client->sendEndorsement($connection->mipUrl, $endorsement);
            $store->logActivity("Sent endorsement to {$connection->organizationName}");
        } catch (\Throwable $e) {
            $store->logActivity("Failed to send endorsement: {$e->getMessage()}");
        }
    }

    private function countTrustedEndorsements(Store $store, array $endorsements, ?string $endorsedPublicKey): int
    {
        if ($endorsedPublicKey === null) {
            return 0;
        }

        $fingerprint = Crypto::fingerprint($endorsedPublicKey);
        $count = 0;

        foreach ($endorsements as $endorsementData) {
            $endorserId = $endorsementData['endorser_mip_identifier'] ?? null;
            $connection = $endorserId !== null ? $store->findConnection($endorserId) : null;

            if ($connection === null || !$connection->isActive()) {
                continue;
            }

            $endorsement = Endorsement::fromPayload($endorsementData);

            if (!$endorsement->validFor($fingerprint)) {
                continue;
            }

            if (!$endorsement->verifySignature($connection->publicKey)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function checkPendingConnectionsForAutoApproval(Store $store): void
    {
        $identity = $store->getNodeIdentity();

        foreach ($store->getPendingConnections() as $connection) {
            $endorsements = $store->findEndorsementsFor($connection->mipIdentifier);

            $trustedCount = 0;
            foreach ($endorsements as $endorsement) {
                $endorserConnection = $store->findConnection($endorsement->endorserMipIdentifier);
                if ($endorserConnection === null || !$endorserConnection->isActive()) {
                    continue;
                }

                if (!$endorsement->validFor($connection->getPublicKeyFingerprint())) {
                    continue;
                }

                if (!$endorsement->verifySignature($endorserConnection->publicKey)) {
                    continue;
                }

                $trustedCount++;
            }

            if ($trustedCount >= $identity->trustThreshold) {
                $connection->approve(dailyRateLimit: 100);
                $store->saveConnection($connection);
                $store->logActivity("Auto-approved pending connection: {$connection->organizationName}");

                // Notify and exchange endorsements
                try {
                    $client = $this->getClient($store);
                    $client->approveConnection(
                        $connection->mipUrl,
                        $identity->toNodeProfile(),
                        100
                    );
                    $this->sendEndorsementToConnection($store, $connection);
                } catch (\Throwable $e) {
                    $store->logActivity("Failed to notify auto-approval: {$e->getMessage()}");
                }
            }
        }
    }
}
