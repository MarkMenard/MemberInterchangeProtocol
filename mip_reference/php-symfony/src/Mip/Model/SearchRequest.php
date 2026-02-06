<?php

namespace App\Mip\Model;

/**
 * Tracks member search requests (inbound and outbound)
 */
class SearchRequest
{
    public const STATUSES = ['PENDING', 'APPROVED', 'DECLINED'];

    public function __construct(
        public string $sharedIdentifier,
        public string $direction,
        public string $targetMipIdentifier,
        public string $targetOrg,
        public array $searchParams = [],
        public ?string $notes = null,
        public array $documents = [],
        public string $status = 'PENDING',
        public array $matches = [],
        public ?string $declineReason = null,
        public string $createdAt = ''
    ) {
        if ($this->sharedIdentifier === '') {
            $this->sharedIdentifier = self::generateUuid();
        }
        if ($this->createdAt === '') {
            $this->createdAt = (new \DateTime())->format(\DateTime::ATOM);
        }
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'DECLINED';
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function approve(array $matches): void
    {
        $this->status = 'APPROVED';
        $this->matches = $matches;
    }

    public function decline(?string $reason = null): void
    {
        $this->status = 'DECLINED';
        $this->declineReason = $reason;
    }

    public function getSearchDescription(): string
    {
        if (!empty($this->searchParams['member_number'])) {
            return "Member #{$this->searchParams['member_number']}";
        }

        if (!empty($this->searchParams['first_name']) && !empty($this->searchParams['last_name'])) {
            $name = "{$this->searchParams['first_name']} {$this->searchParams['last_name']}";
            if (!empty($this->searchParams['birthdate'])) {
                $name .= " ({$this->searchParams['birthdate']})";
            }
            return $name;
        }

        return 'Unknown search';
    }

    public function toRequestPayload(): array
    {
        $payload = [
            'shared_identifier' => $this->sharedIdentifier,
        ];

        foreach (['member_number', 'first_name', 'last_name', 'birthdate'] as $key) {
            if (!empty($this->searchParams[$key])) {
                $payload[$key] = $this->searchParams[$key];
            }
        }

        if ($this->notes !== null) {
            $payload['notes'] = $this->notes;
        }

        if (!empty($this->documents)) {
            $payload['documents'] = $this->documents;
        }

        return $payload;
    }

    public function toReplyPayload(): array
    {
        return [
            'shared_identifier' => $this->sharedIdentifier,
            'status' => $this->status,
            'matches' => $this->matches,
        ];
    }

    public static function fromRequest(array $payload, string $senderMipId, string $senderOrg): self
    {
        return new self(
            sharedIdentifier: $payload['shared_identifier'] ?? '',
            direction: 'inbound',
            targetMipIdentifier: $senderMipId,
            targetOrg: $senderOrg,
            searchParams: array_filter([
                'member_number' => $payload['member_number'] ?? null,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'birthdate' => $payload['birthdate'] ?? null,
            ], fn($v) => $v !== null),
            notes: $payload['notes'] ?? null,
            documents: $payload['documents'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'shared_identifier' => $this->sharedIdentifier,
            'direction' => $this->direction,
            'target_mip_identifier' => $this->targetMipIdentifier,
            'target_org' => $this->targetOrg,
            'search_params' => $this->searchParams,
            'notes' => $this->notes,
            'documents' => $this->documents,
            'status' => $this->status,
            'matches' => $this->matches,
            'decline_reason' => $this->declineReason,
            'created_at' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sharedIdentifier: $data['shared_identifier'] ?? '',
            direction: $data['direction'] ?? 'inbound',
            targetMipIdentifier: $data['target_mip_identifier'] ?? '',
            targetOrg: $data['target_org'] ?? '',
            searchParams: $data['search_params'] ?? [],
            notes: $data['notes'] ?? null,
            documents: $data['documents'] ?? [],
            status: $data['status'] ?? 'PENDING',
            matches: $data['matches'] ?? [],
            declineReason: $data['decline_reason'] ?? null,
            createdAt: $data['created_at'] ?? ''
        );
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
