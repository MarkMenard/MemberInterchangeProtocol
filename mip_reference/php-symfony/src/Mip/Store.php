<?php

namespace App\Mip;

use App\Mip\Model\Connection;
use App\Mip\Model\CogsRequest;
use App\Mip\Model\Endorsement;
use App\Mip\Model\Member;
use App\Mip\Model\NodeIdentity;
use App\Mip\Model\SearchRequest;

/**
 * File-based data store for MIP node data
 */
class Store
{
    private ?NodeIdentity $nodeIdentity = null;
    private array $connections = [];
    private array $members = [];
    private array $endorsements = [];
    private array $searchRequests = [];
    private array $cogsRequests = [];
    private array $activityLog = [];

    private string $dataDir;
    private int $port;

    public function __construct(int $port)
    {
        $this->port = $port;
        $this->dataDir = dirname(__DIR__, 2) . '/data/node' . $port;

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $this->load();
    }

    public function getNodeIdentity(): ?NodeIdentity
    {
        return $this->nodeIdentity;
    }

    public function setNodeIdentity(NodeIdentity $identity): void
    {
        $this->nodeIdentity = $identity;
        $this->save();
    }

    // Connection management
    public function addConnection(Connection $connection): void
    {
        $this->connections[$connection->mipIdentifier] = $connection;
        $this->logActivity("Connection added: {$connection->organizationName} ({$connection->status})");
        $this->save();
    }

    public function findConnection(string $mipIdentifier): ?Connection
    {
        return $this->connections[$mipIdentifier] ?? null;
    }

    /**
     * @return Connection[]
     */
    public function getActiveConnections(): array
    {
        return array_filter($this->connections, fn(Connection $c) => $c->isActive());
    }

    /**
     * @return Connection[]
     */
    public function getPendingConnections(): array
    {
        return array_filter($this->connections, fn(Connection $c) => $c->isPending());
    }

    /**
     * @return Connection[]
     */
    public function getAllConnections(): array
    {
        return array_values($this->connections);
    }

    // Member management
    public function addMember(Member $member): void
    {
        $this->members[$member->memberNumber] = $member;
        $this->save();
    }

    public function findMember(string $memberNumber): ?Member
    {
        return $this->members[$memberNumber] ?? null;
    }

    /**
     * @return Member[]
     */
    public function searchMembers(array $query): array
    {
        return array_filter($this->members, function (Member $m) use ($query) {
            if (!empty($query['member_number'])) {
                return stripos($m->memberNumber, $query['member_number']) !== false;
            }

            if (!empty($query['first_name']) && !empty($query['last_name'])) {
                $nameMatch = stripos($m->firstName, $query['first_name']) !== false &&
                    stripos($m->lastName, $query['last_name']) !== false;

                if (!empty($query['birthdate'])) {
                    return $nameMatch && $m->birthdate === $query['birthdate'];
                }

                return $nameMatch;
            }

            return false;
        });
    }

    /**
     * @return Member[]
     */
    public function getAllMembers(): array
    {
        return array_values($this->members);
    }

    // Endorsement management
    public function addEndorsement(Endorsement $endorsement): void
    {
        $this->endorsements[$endorsement->id] = $endorsement;
        $this->logActivity("Endorsement received from {$endorsement->endorserMipIdentifier}");
        $this->save();
    }

    /**
     * @return Endorsement[]
     */
    public function findEndorsementsFor(string $mipIdentifier): array
    {
        return array_filter(
            $this->endorsements,
            fn(Endorsement $e) => $e->endorsedMipIdentifier === $mipIdentifier
        );
    }

    /**
     * @return Endorsement[]
     */
    public function findEndorsementsFrom(string $mipIdentifier): array
    {
        return array_filter(
            $this->endorsements,
            fn(Endorsement $e) => $e->endorserMipIdentifier === $mipIdentifier
        );
    }

    // Search request management
    public function addSearchRequest(SearchRequest $searchRequest): void
    {
        $this->searchRequests[$searchRequest->sharedIdentifier] = $searchRequest;
        $this->logActivity("Search request: {$searchRequest->direction} - {$searchRequest->targetOrg}");
        $this->save();
    }

    public function findSearchRequest(string $sharedIdentifier): ?SearchRequest
    {
        return $this->searchRequests[$sharedIdentifier] ?? null;
    }

    /**
     * @return SearchRequest[]
     */
    public function getAllSearchRequests(): array
    {
        return array_values($this->searchRequests);
    }

    // COGS request management
    public function addCogsRequest(CogsRequest $cogsRequest): void
    {
        $this->cogsRequests[$cogsRequest->sharedIdentifier] = $cogsRequest;
        $this->logActivity("COGS request: {$cogsRequest->direction} - {$cogsRequest->targetOrg}");
        $this->save();
    }

    public function findCogsRequest(string $sharedIdentifier): ?CogsRequest
    {
        return $this->cogsRequests[$sharedIdentifier] ?? null;
    }

    /**
     * @return CogsRequest[]
     */
    public function getAllCogsRequests(): array
    {
        return array_values($this->cogsRequests);
    }

    // Activity log
    public function logActivity(string $message): void
    {
        array_unshift($this->activityLog, [
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
            'message' => $message,
        ]);

        // Keep only last 100 entries
        $this->activityLog = array_slice($this->activityLog, 0, 100);
        $this->save();
    }

    public function getRecentActivity(int $count = 20): array
    {
        return array_slice($this->activityLog, 0, $count);
    }

    // Persistence
    private function save(): void
    {
        $data = [
            'node_identity' => $this->nodeIdentity?->toArray(),
            'connections' => array_map(fn(Connection $c) => $c->toArray(), $this->connections),
            'members' => array_map(fn(Member $m) => $m->toArray(), $this->members),
            'endorsements' => array_map(fn(Endorsement $e) => $e->toArray(), $this->endorsements),
            'search_requests' => array_map(fn(SearchRequest $s) => $s->toArray(), $this->searchRequests),
            'cogs_requests' => array_map(fn(CogsRequest $c) => $c->toArray(), $this->cogsRequests),
            'activity_log' => $this->activityLog,
        ];

        file_put_contents(
            $this->dataDir . '/store.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function load(): void
    {
        $file = $this->dataDir . '/store.json';

        if (!file_exists($file)) {
            return;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!empty($data['node_identity'])) {
            $this->nodeIdentity = NodeIdentity::fromArray($data['node_identity']);
        }

        foreach ($data['connections'] ?? [] as $connData) {
            $conn = Connection::fromArray($connData);
            $this->connections[$conn->mipIdentifier] = $conn;
        }

        foreach ($data['members'] ?? [] as $memberData) {
            $member = Member::fromArray($memberData);
            $this->members[$member->memberNumber] = $member;
        }

        foreach ($data['endorsements'] ?? [] as $endData) {
            $end = Endorsement::fromArray($endData);
            $this->endorsements[$end->id] = $end;
        }

        foreach ($data['search_requests'] ?? [] as $searchData) {
            $search = SearchRequest::fromArray($searchData);
            $this->searchRequests[$search->sharedIdentifier] = $search;
        }

        foreach ($data['cogs_requests'] ?? [] as $cogsData) {
            $cogs = CogsRequest::fromArray($cogsData);
            $this->cogsRequests[$cogs->sharedIdentifier] = $cogs;
        }

        $this->activityLog = $data['activity_log'] ?? [];
    }

    public function reset(): void
    {
        $this->nodeIdentity = null;
        $this->connections = [];
        $this->members = [];
        $this->endorsements = [];
        $this->searchRequests = [];
        $this->cogsRequests = [];
        $this->activityLog = [];

        $file = $this->dataDir . '/store.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function saveConnection(Connection $connection): void
    {
        $this->connections[$connection->mipIdentifier] = $connection;
        $this->save();
    }

    public function saveSearchRequest(SearchRequest $search): void
    {
        $this->searchRequests[$search->sharedIdentifier] = $search;
        $this->save();
    }

    public function saveCogsRequest(CogsRequest $cogs): void
    {
        $this->cogsRequests[$cogs->sharedIdentifier] = $cogs;
        $this->save();
    }
}
