<?php

namespace App\Services\Mip;

use Carbon\Carbon;

/**
 * File-based store for MIP data.
 * Each node has its own storage file that persists between requests.
 */
class Store
{
    private static ?Store $instance = null;
    private string $storePath;
    private array $data;

    private function __construct(string $nodeId)
    {
        $this->storePath = storage_path("mip_{$nodeId}.json");
        $this->load();
    }

    public static function getInstance(): Store
    {
        if (self::$instance === null) {
            $nodeId = env('MIP_NODE_CONFIG', 'node1');
            self::$instance = new self($nodeId);
        }
        return self::$instance;
    }

    private function load(): void
    {
        if (file_exists($this->storePath)) {
            $content = file_get_contents($this->storePath);
            $this->data = json_decode($content, true) ?? $this->getDefaultData();
        } else {
            $this->data = $this->getDefaultData();
        }
    }

    private function save(): void
    {
        file_put_contents($this->storePath, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    private function getDefaultData(): array
    {
        return [
            'identity' => null,
            'connections' => [],
            'members' => [],
            'endorsements' => [],
            'search_requests' => [],
            'cogs_requests' => [],
            'activity_log' => [],
        ];
    }

    // Identity Management

    public function getIdentity(): ?array
    {
        return $this->data['identity'];
    }

    public function setIdentity(array $identity): void
    {
        $this->data['identity'] = $identity;
        $this->save();
    }

    // Connection Management

    public function getConnections(): array
    {
        return $this->data['connections'] ?? [];
    }

    public function findConnection(string $mipId): ?array
    {
        return $this->data['connections'][$mipId] ?? null;
    }

    public function findConnectionById(string $id): ?array
    {
        foreach ($this->data['connections'] as $conn) {
            if (($conn['id'] ?? null) === $id) {
                return $conn;
            }
        }
        return null;
    }

    public function saveConnection(array $connection): void
    {
        $this->data['connections'][$connection['mip_identifier']] = $connection;
        $this->save();
    }

    public function updateConnection(string $mipId, array $updates): ?array
    {
        if (isset($this->data['connections'][$mipId])) {
            $this->data['connections'][$mipId] = array_merge($this->data['connections'][$mipId], $updates);
            $this->save();
            return $this->data['connections'][$mipId];
        }
        return null;
    }

    // Member Management

    public function getMembers(): array
    {
        return $this->data['members'] ?? [];
    }

    public function findMember(string $memberNumber): ?array
    {
        foreach ($this->data['members'] as $member) {
            if ($member['member_number'] === $memberNumber) {
                return $member;
            }
        }
        return null;
    }

    public function searchMembers(array $criteria): array
    {
        $results = [];

        foreach ($this->data['members'] as $member) {
            $match = true;

            if (!empty($criteria['first_name'])) {
                if (stripos($member['first_name'] ?? '', $criteria['first_name']) === false) {
                    $match = false;
                }
            }

            if (!empty($criteria['last_name'])) {
                if (stripos($member['last_name'] ?? '', $criteria['last_name']) === false) {
                    $match = false;
                }
            }

            if (!empty($criteria['member_number'])) {
                if (($member['member_number'] ?? '') !== $criteria['member_number']) {
                    $match = false;
                }
            }

            if ($match) {
                $results[] = $member;
            }
        }

        return $results;
    }

    public function setMembers(array $members): void
    {
        $this->data['members'] = $members;
        $this->save();
    }

    // Endorsement Management

    public function getEndorsements(): array
    {
        return $this->data['endorsements'] ?? [];
    }

    public function findEndorsement(string $endorserMipId, string $endorsedMipId): ?array
    {
        foreach ($this->data['endorsements'] as $endorsement) {
            if ($endorsement['endorser_mip_identifier'] === $endorserMipId &&
                $endorsement['endorsed_mip_identifier'] === $endorsedMipId) {
                return $endorsement;
            }
        }
        return null;
    }

    public function addEndorsement(array $endorsement): void
    {
        $endorsement['id'] = $this->generateId();
        $endorsement['created_at'] = Carbon::now()->toIso8601String();
        $this->data['endorsements'][] = $endorsement;
        $this->save();
    }

    public function getEndorsementsFor(string $mipId): array
    {
        return array_filter($this->data['endorsements'] ?? [], function ($e) use ($mipId) {
            return $e['endorsed_mip_identifier'] === $mipId;
        });
    }

    // Search Request Management

    public function getSearchRequests(): array
    {
        return $this->data['search_requests'] ?? [];
    }

    public function findSearchRequest(string $id): ?array
    {
        return $this->data['search_requests'][$id] ?? null;
    }

    public function saveSearchRequest(array $request): string
    {
        if (!isset($request['id'])) {
            $request['id'] = $this->generateId();
        }
        $request['created_at'] = $request['created_at'] ?? Carbon::now()->toIso8601String();
        $this->data['search_requests'][$request['id']] = $request;
        $this->save();
        return $request['id'];
    }

    public function updateSearchRequest(string $id, array $updates): ?array
    {
        if (isset($this->data['search_requests'][$id])) {
            $this->data['search_requests'][$id] = array_merge($this->data['search_requests'][$id], $updates);
            $this->save();
            return $this->data['search_requests'][$id];
        }
        return null;
    }

    // COGS Request Management

    public function getCogsRequests(): array
    {
        return $this->data['cogs_requests'] ?? [];
    }

    public function findCogsRequest(string $id): ?array
    {
        return $this->data['cogs_requests'][$id] ?? null;
    }

    public function saveCogsRequest(array $request): string
    {
        if (!isset($request['id'])) {
            $request['id'] = $this->generateId();
        }
        $request['created_at'] = $request['created_at'] ?? Carbon::now()->toIso8601String();
        $this->data['cogs_requests'][$request['id']] = $request;
        $this->save();
        return $request['id'];
    }

    public function updateCogsRequest(string $id, array $updates): ?array
    {
        if (isset($this->data['cogs_requests'][$id])) {
            $this->data['cogs_requests'][$id] = array_merge($this->data['cogs_requests'][$id], $updates);
            $this->save();
            return $this->data['cogs_requests'][$id];
        }
        return null;
    }

    // Activity Log

    public function getActivityLog(): array
    {
        return $this->data['activity_log'] ?? [];
    }

    public function addActivity(string $message, string $type = 'info'): void
    {
        array_unshift($this->data['activity_log'], [
            'timestamp' => Carbon::now()->toIso8601String(),
            'message' => $message,
            'type' => $type,
        ]);
        // Keep only last 100 activities
        $this->data['activity_log'] = array_slice($this->data['activity_log'], 0, 100);
        $this->save();
    }

    // Utility

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function clear(): void
    {
        $this->data = $this->getDefaultData();
        $this->save();
    }
}
