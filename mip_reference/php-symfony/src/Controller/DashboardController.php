<?php

namespace App\Controller;

use App\Mip\Client;
use App\Mip\Model\Connection;
use App\Mip\Model\CogsRequest;
use App\Mip\Model\Endorsement;
use App\Mip\Model\SearchRequest;
use App\Mip\Store;
use App\Mip\StoreFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Admin dashboard controller for the MIP web UI
 */
class DashboardController extends AbstractController
{
    private Store $store;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    private function getStore(Request $request): Store
    {
        $port = (int)$request->server->get('SERVER_PORT', 4013);
        return StoreFactory::initializeFromConfig($port);
    }

    private function getClient(Store $store): Client
    {
        $identity = $store->getNodeIdentity();
        return new Client($this->httpClient, $identity);
    }

    #[Route('/', name: 'dashboard')]
    public function dashboard(Request $request): Response
    {
        $store = $this->getStore($request);

        return $this->render('dashboard.html.twig', [
            'identity' => $store->getNodeIdentity(),
            'store' => $store,
        ]);
    }

    #[Route('/connections', name: 'connections')]
    public function connections(Request $request): Response
    {
        $store = $this->getStore($request);

        return $this->render('connections/index.html.twig', [
            'identity' => $store->getNodeIdentity(),
            'connections' => $store->getAllConnections(),
            'store' => $store,
        ]);
    }

    #[Route('/connections/new', name: 'connection_new', methods: ['POST'])]
    public function connectionNew(Request $request): Response
    {
        $store = $this->getStore($request);
        $targetUrl = trim($request->request->get('target_url', ''));

        if (empty($targetUrl)) {
            $this->addFlash('error', 'Target URL is required');
            return $this->redirectToRoute('connections');
        }

        $client = $this->getClient($store);
        $endorsements = $store->findEndorsementsFor($store->getNodeIdentity()->mipIdentifier);

        try {
            $result = $client->requestConnection($targetUrl, array_values($endorsements));

            if ($result['success'] && ($result['body']['meta']['succeeded'] ?? false)) {
                $responseData = $result['body']['data']['mip_connection'] ?? [];
                $nodeProfile = $responseData['node_profile'] ?? [];

                // Extract MIP ID from target URL
                preg_match('/\/([a-f0-9]{32})$/', $targetUrl, $matches);
                $targetMipId = $matches[1] ?? $nodeProfile['mip_identifier'] ?? '';

                $connection = new Connection(
                    mipIdentifier: $nodeProfile['mip_identifier'] ?? $targetMipId,
                    mipUrl: $targetUrl,
                    publicKey: $nodeProfile['public_key'] ?? null,
                    organizationName: $nodeProfile['organization_legal_name'] ?? 'Unknown',
                    contactPerson: $nodeProfile['contact_person'] ?? null,
                    contactPhone: $nodeProfile['contact_phone'] ?? null,
                    status: $responseData['status'] ?? 'PENDING',
                    direction: 'outbound',
                    dailyRateLimit: $responseData['daily_rate_limit'] ?? 100
                );

                $store->addConnection($connection);

                // If auto-approved, send endorsement
                if ($connection->isActive()) {
                    $this->sendEndorsementToConnection($store, $connection);
                }

                $this->addFlash('success', 'Connection request sent');
            } else {
                $error = $result['body']['data']['error'] ?? 'Connection request failed';
                $this->addFlash('error', $error);
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Connection failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('connections');
    }

    #[Route('/connections/{mipId}/approve', name: 'connection_approve', methods: ['POST'])]
    public function connectionApprove(Request $request, string $mipId): Response
    {
        $store = $this->getStore($request);
        $connection = $store->findConnection($mipId);

        if (!$connection) {
            $this->addFlash('error', 'Connection not found');
            return $this->redirectToRoute('connections');
        }

        if (!$connection->isPending()) {
            $this->addFlash('error', 'Connection is not pending');
            return $this->redirectToRoute('connections');
        }

        $connection->approve(dailyRateLimit: 100);
        $store->saveConnection($connection);
        $store->logActivity("Approved connection: {$connection->organizationName}");

        // Notify the other node
        try {
            $client = $this->getClient($store);
            $client->approveConnection(
                $connection->mipUrl,
                $store->getNodeIdentity()->toNodeProfile(),
                100
            );

            // Send endorsement
            $this->sendEndorsementToConnection($store, $connection);
        } catch (\Throwable $e) {
            $store->logActivity("Failed to notify approval: {$e->getMessage()}");
        }

        $this->addFlash('success', 'Connection approved');
        return $this->redirectToRoute('connections');
    }

    #[Route('/connections/{mipId}/decline', name: 'connection_decline', methods: ['POST'])]
    public function connectionDecline(Request $request, string $mipId): Response
    {
        $store = $this->getStore($request);
        $connection = $store->findConnection($mipId);

        if (!$connection) {
            $this->addFlash('error', 'Connection not found');
            return $this->redirectToRoute('connections');
        }

        $reason = $request->request->get('reason');
        $connection->decline($reason);
        $store->saveConnection($connection);
        $store->logActivity("Declined connection: {$connection->organizationName}");

        try {
            $client = $this->getClient($store);
            $client->declineConnection($connection->mipUrl, $reason);
        } catch (\Throwable $e) {
            $store->logActivity("Failed to notify decline: {$e->getMessage()}");
        }

        $this->addFlash('success', 'Connection declined');
        return $this->redirectToRoute('connections');
    }

    #[Route('/connections/{mipId}/revoke', name: 'connection_revoke', methods: ['POST'])]
    public function connectionRevoke(Request $request, string $mipId): Response
    {
        $store = $this->getStore($request);
        $connection = $store->findConnection($mipId);

        if (!$connection) {
            $this->addFlash('error', 'Connection not found');
            return $this->redirectToRoute('connections');
        }

        $reason = $request->request->get('reason');
        $connection->revoke($reason);
        $store->saveConnection($connection);
        $store->logActivity("Revoked connection: {$connection->organizationName}");

        try {
            $client = $this->getClient($store);
            $client->revokeConnection($connection->mipUrl, $reason);
        } catch (\Throwable $e) {
            $store->logActivity("Failed to notify revoke: {$e->getMessage()}");
        }

        $this->addFlash('success', 'Connection revoked');
        return $this->redirectToRoute('connections');
    }

    #[Route('/connections/{mipId}/restore', name: 'connection_restore', methods: ['POST'])]
    public function connectionRestore(Request $request, string $mipId): Response
    {
        $store = $this->getStore($request);
        $connection = $store->findConnection($mipId);

        if (!$connection) {
            $this->addFlash('error', 'Connection not found');
            return $this->redirectToRoute('connections');
        }

        $connection->restore();
        $store->saveConnection($connection);
        $store->logActivity("Restored connection: {$connection->organizationName}");

        try {
            $client = $this->getClient($store);
            $client->restoreConnection($connection->mipUrl);
        } catch (\Throwable $e) {
            $store->logActivity("Failed to notify restore: {$e->getMessage()}");
        }

        $this->addFlash('success', 'Connection restored');
        return $this->redirectToRoute('connections');
    }

    #[Route('/members', name: 'members')]
    public function members(Request $request): Response
    {
        $store = $this->getStore($request);

        return $this->render('members/index.html.twig', [
            'identity' => $store->getNodeIdentity(),
            'members' => $store->getAllMembers(),
            'store' => $store,
        ]);
    }

    #[Route('/searches', name: 'searches')]
    public function searches(Request $request): Response
    {
        $store = $this->getStore($request);

        return $this->render('searches/index.html.twig', [
            'identity' => $store->getNodeIdentity(),
            'searches' => $store->getAllSearchRequests(),
            'activeConnections' => $store->getActiveConnections(),
            'store' => $store,
        ]);
    }

    #[Route('/searches/new', name: 'search_new', methods: ['POST'])]
    public function searchNew(Request $request): Response
    {
        $store = $this->getStore($request);
        $targetMipId = $request->request->get('target_mip_id');
        $connection = $store->findConnection($targetMipId);

        if (!$connection || !$connection->isActive()) {
            $this->addFlash('error', 'Invalid connection');
            return $this->redirectToRoute('searches');
        }

        $searchParams = array_filter([
            'member_number' => $request->request->get('member_number') ?: null,
            'first_name' => $request->request->get('first_name') ?: null,
            'last_name' => $request->request->get('last_name') ?: null,
            'birthdate' => $request->request->get('birthdate') ?: null,
        ], fn($v) => $v !== null);

        if (empty($searchParams)) {
            $this->addFlash('error', 'Search criteria required');
            return $this->redirectToRoute('searches');
        }

        $searchRequest = new SearchRequest(
            sharedIdentifier: '',
            direction: 'outbound',
            targetMipIdentifier: $connection->mipIdentifier,
            targetOrg: $connection->organizationName,
            searchParams: $searchParams,
            notes: $request->request->get('notes') ?: null
        );

        $store->addSearchRequest($searchRequest);

        try {
            $client = $this->getClient($store);
            $result = $client->memberSearch($connection->mipUrl, $searchRequest);
            if ($result['success']) {
                $store->logActivity("Search sent to {$connection->organizationName}");
            }
        } catch (\Throwable $e) {
            $store->logActivity("Search failed: {$e->getMessage()}");
        }

        $this->addFlash('success', 'Search request sent');
        return $this->redirectToRoute('searches');
    }

    #[Route('/searches/{id}/approve', name: 'search_approve', methods: ['POST'])]
    public function searchApprove(Request $request, string $id): Response
    {
        $store = $this->getStore($request);
        $search = $store->findSearchRequest($id);

        if (!$search) {
            $this->addFlash('error', 'Search not found');
            return $this->redirectToRoute('searches');
        }

        if (!$search->isPending()) {
            $this->addFlash('error', 'Search is not pending');
            return $this->redirectToRoute('searches');
        }

        $matches = array_map(fn($m) => $m->toSearchResult(), $store->searchMembers($search->searchParams));
        $search->approve($matches);
        $store->saveSearchRequest($search);
        $store->logActivity("Approved search from {$search->targetOrg}: " . count($matches) . " matches");

        // Send reply
        $connection = $store->findConnection($search->targetMipIdentifier);
        if ($connection?->isActive()) {
            try {
                $client = $this->getClient($store);
                $client->memberSearchReply($connection->mipUrl, $search);
            } catch (\Throwable $e) {
                $store->logActivity("Failed to send search reply: {$e->getMessage()}");
            }
        }

        $this->addFlash('success', 'Search approved');
        return $this->redirectToRoute('searches');
    }

    #[Route('/searches/{id}/decline', name: 'search_decline', methods: ['POST'])]
    public function searchDecline(Request $request, string $id): Response
    {
        $store = $this->getStore($request);
        $search = $store->findSearchRequest($id);

        if (!$search) {
            $this->addFlash('error', 'Search not found');
            return $this->redirectToRoute('searches');
        }

        $reason = $request->request->get('reason');
        $search->decline($reason);
        $store->saveSearchRequest($search);
        $store->logActivity("Declined search from {$search->targetOrg}");

        // Send reply
        $connection = $store->findConnection($search->targetMipIdentifier);
        if ($connection?->isActive()) {
            try {
                $client = $this->getClient($store);
                $client->memberSearchReply($connection->mipUrl, $search);
            } catch (\Throwable $e) {
                $store->logActivity("Failed to send search reply: {$e->getMessage()}");
            }
        }

        $this->addFlash('success', 'Search declined');
        return $this->redirectToRoute('searches');
    }

    #[Route('/cogs', name: 'cogs')]
    public function cogs(Request $request): Response
    {
        $store = $this->getStore($request);

        return $this->render('cogs/index.html.twig', [
            'identity' => $store->getNodeIdentity(),
            'cogsRequests' => $store->getAllCogsRequests(),
            'activeConnections' => $store->getActiveConnections(),
            'store' => $store,
        ]);
    }

    #[Route('/cogs/new', name: 'cogs_new', methods: ['POST'])]
    public function cogsNew(Request $request): Response
    {
        $store = $this->getStore($request);
        $targetMipId = $request->request->get('target_mip_id');
        $connection = $store->findConnection($targetMipId);

        if (!$connection || !$connection->isActive()) {
            $this->addFlash('error', 'Invalid connection');
            return $this->redirectToRoute('cogs');
        }

        $cogs = new CogsRequest(
            sharedIdentifier: '',
            direction: 'outbound',
            targetMipIdentifier: $connection->mipIdentifier,
            targetOrg: $connection->organizationName,
            requestingMember: [
                'member_number' => $request->request->get('requesting_member_number'),
                'first_name' => $request->request->get('requesting_first_name'),
                'last_name' => $request->request->get('requesting_last_name'),
            ],
            requestedMemberNumber: $request->request->get('requested_member_number'),
            notes: $request->request->get('notes') ?: null
        );

        $store->addCogsRequest($cogs);

        try {
            $client = $this->getClient($store);
            $result = $client->requestCogs($connection->mipUrl, $cogs);
            if ($result['success']) {
                $store->logActivity("COGS requested from {$connection->organizationName}");
            }
        } catch (\Throwable $e) {
            $store->logActivity("COGS request failed: {$e->getMessage()}");
        }

        $this->addFlash('success', 'COGS request sent');
        return $this->redirectToRoute('cogs');
    }

    #[Route('/cogs/{id}/approve', name: 'cogs_approve', methods: ['POST'])]
    public function cogsApprove(Request $request, string $id): Response
    {
        $store = $this->getStore($request);
        $cogs = $store->findCogsRequest($id);

        if (!$cogs) {
            $this->addFlash('error', 'COGS not found');
            return $this->redirectToRoute('cogs');
        }

        if (!$cogs->isPending()) {
            $this->addFlash('error', 'COGS is not pending');
            return $this->redirectToRoute('cogs');
        }

        $member = $store->findMember($cogs->requestedMemberNumber);
        if (!$member) {
            $this->addFlash('error', 'Member not found');
            return $this->redirectToRoute('cogs');
        }

        $identity = $store->getNodeIdentity();
        $issuingOrg = [
            'mip_identifier' => $identity->mipIdentifier,
            'organization_legal_name' => $identity->organizationName,
        ];

        $cogs->approve($member, $issuingOrg);
        $store->saveCogsRequest($cogs);
        $store->logActivity("Approved COGS for {$member->memberNumber}");

        // Send reply
        $connection = $store->findConnection($cogs->targetMipIdentifier);
        if ($connection?->isActive()) {
            try {
                $client = $this->getClient($store);
                $client->cogsReply($connection->mipUrl, $cogs);
            } catch (\Throwable $e) {
                $store->logActivity("Failed to send COGS reply: {$e->getMessage()}");
            }
        }

        $this->addFlash('success', 'COGS approved');
        return $this->redirectToRoute('cogs');
    }

    #[Route('/cogs/{id}/decline', name: 'cogs_decline', methods: ['POST'])]
    public function cogsDecline(Request $request, string $id): Response
    {
        $store = $this->getStore($request);
        $cogs = $store->findCogsRequest($id);

        if (!$cogs) {
            $this->addFlash('error', 'COGS not found');
            return $this->redirectToRoute('cogs');
        }

        $reason = $request->request->get('reason') ?: 'Request declined';
        $cogs->decline($reason);
        $store->saveCogsRequest($cogs);
        $store->logActivity("Declined COGS request");

        // Send reply
        $connection = $store->findConnection($cogs->targetMipIdentifier);
        if ($connection?->isActive()) {
            try {
                $client = $this->getClient($store);
                $client->cogsReply($connection->mipUrl, $cogs);
            } catch (\Throwable $e) {
                $store->logActivity("Failed to send COGS reply: {$e->getMessage()}");
            }
        }

        $this->addFlash('success', 'COGS declined');
        return $this->redirectToRoute('cogs');
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $port = (int)$request->server->get('SERVER_PORT', 4013);
        StoreFactory::reset($port);

        $this->addFlash('success', 'Node reset successfully');
        return $this->redirectToRoute('dashboard');
    }

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
}
