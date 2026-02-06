<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use App\Services\Mip\Crypto;
use App\Services\Mip\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MipApiController extends Controller
{
    private Store $store;
    private Client $client;

    public function __construct()
    {
        $this->store = app(Store::class);
        $this->client = app(Client::class);
    }

    /**
     * Verify MIP request signature
     */
    private function verifyMipRequest(Request $request): array
    {
        $mipId = $request->header('X-MIP-MIP-IDENTIFIER');
        $timestamp = $request->header('X-MIP-TIMESTAMP');
        $signature = $request->header('X-MIP-SIGNATURE');
        $publicKeyHeader = $request->header('X-MIP-PUBLIC-KEY');

        if (!$mipId || !$timestamp || !$signature) {
            return ['valid' => false, 'error' => 'Missing MIP headers'];
        }

        // Get public key from existing connection or header
        $connection = $this->store->findConnection($mipId);
        $publicKey = null;

        if ($connection) {
            $publicKey = $connection['public_key'];
        } elseif ($publicKeyHeader) {
            $publicKey = base64_decode($publicKeyHeader);
        }

        if (!$publicKey) {
            return ['valid' => false, 'error' => 'No public key available'];
        }

        // Verify signature
        $path = $request->getPathInfo();
        $body = $request->getContent();

        $valid = Signature::verify($path, $body, $timestamp, $signature, $publicKey);

        if (!$valid) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }

        return [
            'valid' => true,
            'mip_id' => $mipId,
            'connection' => $connection,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Handle incoming connection request
     */
    public function connectionRequest(Request $request, string $mipId)
    {
        Log::info("Received connection request", ['from' => $request->header('X-MIP-MIP-IDENTIFIER')]);

        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $data = $request->json()->all();
        $nodeProfile = $data['node_profile'];
        $endorsements = $data['endorsements'] ?? [];

        // Check if connection already exists
        $existingConnection = $this->store->findConnection($nodeProfile['mip_identifier']);
        if ($existingConnection) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => 'Connection already exists'],
            ], 400);
        }

        // Create connection record
        $connection = [
            'id' => bin2hex(random_bytes(8)),
            'mip_identifier' => $nodeProfile['mip_identifier'],
            'mip_url' => $nodeProfile['mip_url'],
            'organization_name' => $nodeProfile['organization_legal_name'],
            'contact_person' => $nodeProfile['contact_person'] ?? null,
            'contact_phone' => $nodeProfile['contact_phone'] ?? null,
            'public_key' => $nodeProfile['public_key'],
            'status' => 'PENDING',
            'direction' => 'inbound',
            'created_at' => now()->toIso8601String(),
        ];

        // Check for auto-approval via endorsements
        $identity = $this->store->getIdentity();
        $trustedCount = $this->countTrustedEndorsements($endorsements, $nodeProfile['public_key']);

        if ($trustedCount >= $identity['trust_threshold']) {
            // Auto-approve!
            $connection['status'] = 'ACTIVE';
            $connection['daily_rate_limit'] = 100;
            $connection['auto_approved'] = true;
            $connection['approved_at'] = now()->toIso8601String();

            $this->store->saveConnection($connection);
            $this->store->addActivity("Auto-approved connection from {$nodeProfile['organization_legal_name']} (trust threshold met)", 'success');

            // Send endorsement back
            $this->client->sendEndorsement($connection);

            // Notify approval
            $this->client->notifyApproval($connection);

            return response()->json([
                'meta' => ['succeeded' => true],
                'data' => [
                    'status' => 'ACTIVE',
                    'message' => 'Connection auto-approved via web of trust',
                ],
            ]);
        }

        $this->store->saveConnection($connection);
        $this->store->addActivity("Received connection request from {$nodeProfile['organization_legal_name']}");

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => [
                'status' => 'PENDING',
                'message' => 'Connection request received, awaiting approval',
            ],
        ]);
    }

    /**
     * Handle connection approval notification
     */
    public function connectionApproved(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $data = $request->json()->all();
        $nodeProfile = $data['node_profile'];
        $sourceMipId = $nodeProfile['mip_identifier'];

        $connection = $this->store->findConnection($sourceMipId);
        if (!$connection) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => 'Connection not found'],
            ], 404);
        }

        // Update connection to active
        $this->store->updateConnection($sourceMipId, [
            'status' => 'ACTIVE',
            'daily_rate_limit' => $data['daily_rate_limit'] ?? 100,
            'approved_at' => now()->toIso8601String(),
            'public_key' => $nodeProfile['public_key'],
        ]);

        // Get updated connection
        $connection = $this->store->findConnection($sourceMipId);

        // Send our endorsement
        $this->client->sendEndorsement($connection);

        $this->store->addActivity("Connection with {$connection['organization_name']} is now active", 'success');

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'Approval acknowledged'],
        ]);
    }

    /**
     * Handle connection decline notification
     */
    public function connectionDeclined(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $sourceMipId = $verification['mip_id'];
        $connection = $this->store->findConnection($sourceMipId);

        if ($connection) {
            $this->store->updateConnection($sourceMipId, [
                'status' => 'DECLINED',
                'declined_at' => now()->toIso8601String(),
            ]);

            $this->store->addActivity("Connection request to {$connection['organization_name']} was declined", 'warning');
        }

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'Decline acknowledged'],
        ]);
    }

    /**
     * Handle endorsement
     */
    public function receiveEndorsement(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $data = $request->json()->all();

        // Verify endorsement signature
        $identity = $this->store->getIdentity();
        $expectedFingerprint = Crypto::fingerprint($identity['public_key']);

        if ($data['endorsed_public_key_fingerprint'] !== $expectedFingerprint) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => 'Fingerprint mismatch'],
            ], 400);
        }

        // Store the endorsement
        $this->store->addEndorsement([
            'endorser_mip_identifier' => $data['endorser_mip_identifier'],
            'endorsed_mip_identifier' => $data['endorsed_mip_identifier'],
            'endorsed_public_key_fingerprint' => $data['endorsed_public_key_fingerprint'],
            'signature' => $data['signature'],
            'expires_at' => $data['expires_at'],
        ]);

        $connection = $this->store->findConnection($data['endorser_mip_identifier']);
        $orgName = $connection ? $connection['organization_name'] : $data['endorser_mip_identifier'];

        $this->store->addActivity("Received endorsement from {$orgName}", 'success');

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'Endorsement received'],
        ]);
    }

    /**
     * Handle member search request
     */
    public function memberSearch(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        if (!$verification['connection'] || $verification['connection']['status'] !== 'ACTIVE') {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => 'No active connection'],
            ], 403);
        }

        $data = $request->json()->all();

        // Create inbound search request
        $searchRequest = [
            'direction' => 'inbound',
            'source_mip_identifier' => $verification['mip_id'],
            'source_organization' => $verification['connection']['organization_name'],
            'original_request_id' => $data['request_id'],
            'status' => 'PENDING',
            'criteria' => $data['search_criteria'] ?? [],
            'results' => null,
        ];

        $this->store->saveSearchRequest($searchRequest);
        $this->store->addActivity("Received search request from {$verification['connection']['organization_name']}");

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'Search request received, awaiting approval'],
        ]);
    }

    /**
     * Handle member search reply
     */
    public function memberSearchReply(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $data = $request->json()->all();
        $requestId = $data['request_id'];
        $results = $data['results'] ?? [];

        // Update our outbound search request
        $searchRequest = $this->store->findSearchRequest($requestId);
        if ($searchRequest && $searchRequest['direction'] === 'outbound') {
            $this->store->updateSearchRequest($requestId, [
                'status' => 'COMPLETED',
                'results' => $results,
                'completed_at' => now()->toIso8601String(),
            ]);

            $this->store->addActivity("Received search results: " . count($results) . " match(es)", 'success');
        }

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'Search results received'],
        ]);
    }

    /**
     * Handle COGS request
     */
    public function cogsRequest(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        if (!$verification['connection'] || $verification['connection']['status'] !== 'ACTIVE') {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => 'No active connection'],
            ], 403);
        }

        $data = $request->json()->all();

        // Create inbound COGS request
        $cogsRequest = [
            'direction' => 'inbound',
            'source_mip_identifier' => $verification['mip_id'],
            'source_organization' => $verification['connection']['organization_name'],
            'original_request_id' => $data['request_id'],
            'status' => 'PENDING',
            'member_number' => $data['member_number'],
            'member_profile' => null,
        ];

        $this->store->saveCogsRequest($cogsRequest);
        $this->store->addActivity("Received COGS request from {$verification['connection']['organization_name']} for member {$data['member_number']}");

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'COGS request received, awaiting approval'],
        ]);
    }

    /**
     * Handle COGS reply
     */
    public function cogsReply(Request $request, string $mipId)
    {
        $verification = $this->verifyMipRequest($request);
        if (!$verification['valid']) {
            return response()->json([
                'meta' => ['succeeded' => false, 'error' => $verification['error']],
            ], 401);
        }

        $data = $request->json()->all();
        $requestId = $data['request_id'];

        // Update our outbound COGS request
        $cogsRequest = $this->store->findCogsRequest($requestId);
        if ($cogsRequest && $cogsRequest['direction'] === 'outbound') {
            $status = $data['approved'] ? 'APPROVED' : 'DECLINED';
            $this->store->updateCogsRequest($requestId, [
                'status' => $status,
                'member_profile' => $data['member_profile'],
                'issued_at' => $data['issued_at'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'completed_at' => now()->toIso8601String(),
            ]);

            if ($data['approved']) {
                $this->store->addActivity("COGS received for {$data['member_profile']['first_name']} {$data['member_profile']['last_name']}", 'success');
            } else {
                $this->store->addActivity("COGS request was declined", 'warning');
            }
        }

        return response()->json([
            'meta' => ['succeeded' => true],
            'data' => ['message' => 'COGS reply received'],
        ]);
    }

    /**
     * Count trusted endorsements from active connections
     */
    private function countTrustedEndorsements(array $endorsements, string $targetPublicKey): int
    {
        $count = 0;
        $targetFingerprint = Crypto::fingerprint($targetPublicKey);

        foreach ($endorsements as $endorsement) {
            // Check if endorser is one of our active connections
            $connection = $this->store->findConnection($endorsement['endorser_mip_identifier']);

            if ($connection && $connection['status'] === 'ACTIVE') {
                // Verify the endorsement signature
                $dataToVerify = $targetPublicKey . $targetFingerprint;
                $valid = Crypto::verify($dataToVerify, $endorsement['signature'], $connection['public_key']);

                if ($valid && $endorsement['endorsed_public_key_fingerprint'] === $targetFingerprint) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
