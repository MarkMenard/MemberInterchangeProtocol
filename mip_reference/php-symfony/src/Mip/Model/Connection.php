<?php

namespace App\Mip\Model;

use App\Mip\Crypto;

/**
 * Represents a connection with another MIP node
 */
class Connection
{
    public const STATUSES = ['PENDING', 'ACTIVE', 'DECLINED', 'REVOKED'];

    public function __construct(
        public string $mipIdentifier,
        public string $mipUrl,
        public ?string $publicKey,
        public string $organizationName,
        public ?string $contactPerson = null,
        public ?string $contactPhone = null,
        public string $status = 'PENDING',
        public string $direction = 'inbound',
        public bool $shareMyOrganization = true,
        public int $dailyRateLimit = 100,
        public string $createdAt = '',
        public ?string $declineReason = null,
        public ?string $revokeReason = null
    ) {
        if ($this->createdAt === '') {
            $this->createdAt = (new \DateTime())->format(\DateTime::ATOM);
        }
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'DECLINED';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'REVOKED';
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function approve(?array $nodeProfile = null, int $dailyRateLimit = 100): void
    {
        $this->status = 'ACTIVE';
        $this->dailyRateLimit = $dailyRateLimit;
        if ($nodeProfile !== null) {
            $this->updateFromProfile($nodeProfile);
        }
    }

    public function decline(?string $reason = null): void
    {
        $this->status = 'DECLINED';
        $this->declineReason = $reason;
    }

    public function revoke(?string $reason = null): void
    {
        $this->status = 'REVOKED';
        $this->revokeReason = $reason;
    }

    public function restore(): void
    {
        $this->status = 'ACTIVE';
        $this->revokeReason = null;
    }

    public function getPublicKeyFingerprint(): ?string
    {
        if ($this->publicKey === null) {
            return null;
        }
        return Crypto::fingerprint($this->publicKey);
    }

    public function toNodeProfile(): array
    {
        return [
            'mip_identifier' => $this->mipIdentifier,
            'mip_url' => $this->mipUrl,
            'organization_legal_name' => $this->organizationName,
            'contact_person' => $this->contactPerson,
            'contact_phone' => $this->contactPhone,
            'public_key' => $this->publicKey,
            'share_my_organization' => $this->shareMyOrganization,
        ];
    }

    /**
     * Create from a connection request payload
     */
    public static function fromRequest(array $payload, string $direction = 'inbound'): self
    {
        return new self(
            mipIdentifier: $payload['mip_identifier'],
            mipUrl: $payload['mip_url'],
            publicKey: $payload['public_key'] ?? null,
            organizationName: $payload['organization_legal_name'] ?? 'Unknown',
            contactPerson: $payload['contact_person'] ?? null,
            contactPhone: $payload['contact_phone'] ?? null,
            status: 'PENDING',
            direction: $direction,
            shareMyOrganization: $payload['share_my_organization'] ?? true
        );
    }

    private function updateFromProfile(array $profile): void
    {
        if (!empty($profile['organization_legal_name'])) {
            $this->organizationName = $profile['organization_legal_name'];
        }
        if (!empty($profile['contact_person'])) {
            $this->contactPerson = $profile['contact_person'];
        }
        if (!empty($profile['contact_phone'])) {
            $this->contactPhone = $profile['contact_phone'];
        }
        if (!empty($profile['mip_url'])) {
            $this->mipUrl = $profile['mip_url'];
        }
        if (!empty($profile['public_key'])) {
            $this->publicKey = $profile['public_key'];
        }
    }

    public function toArray(): array
    {
        return [
            'mip_identifier' => $this->mipIdentifier,
            'mip_url' => $this->mipUrl,
            'public_key' => $this->publicKey,
            'organization_name' => $this->organizationName,
            'contact_person' => $this->contactPerson,
            'contact_phone' => $this->contactPhone,
            'status' => $this->status,
            'direction' => $this->direction,
            'share_my_organization' => $this->shareMyOrganization,
            'daily_rate_limit' => $this->dailyRateLimit,
            'created_at' => $this->createdAt,
            'decline_reason' => $this->declineReason,
            'revoke_reason' => $this->revokeReason,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mipIdentifier: $data['mip_identifier'],
            mipUrl: $data['mip_url'],
            publicKey: $data['public_key'] ?? null,
            organizationName: $data['organization_name'] ?? 'Unknown',
            contactPerson: $data['contact_person'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            status: $data['status'] ?? 'PENDING',
            direction: $data['direction'] ?? 'inbound',
            shareMyOrganization: $data['share_my_organization'] ?? true,
            dailyRateLimit: $data['daily_rate_limit'] ?? 100,
            createdAt: $data['created_at'] ?? '',
            declineReason: $data['decline_reason'] ?? null,
            revokeReason: $data['revoke_reason'] ?? null
        );
    }
}
