<?php

namespace App\Mip\Model;

/**
 * Tracks Certificate of Good Standing requests (inbound and outbound)
 */
class CogsRequest
{
    public const STATUSES = ['PENDING', 'APPROVED', 'DECLINED'];

    public function __construct(
        public string $sharedIdentifier,
        public string $direction,
        public string $targetMipIdentifier,
        public string $targetOrg,
        public array $requestingMember = [],
        public ?string $requestedMemberNumber = null,
        public ?string $notes = null,
        public string $status = 'PENDING',
        public ?array $certificate = null,
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

    public function approve(Member $member, array $issuingOrg): void
    {
        $this->status = 'APPROVED';
        $validUntil = new \DateTime();
        $validUntil->modify('+90 days');

        $this->certificate = [
            'shared_identifier' => $this->sharedIdentifier,
            'status' => 'APPROVED',
            'good_standing' => $member->goodStanding,
            'issued_at' => (new \DateTime())->format(\DateTime::ATOM),
            'valid_until' => $validUntil->format(\DateTime::ATOM),
            'issuing_organization' => $issuingOrg,
            'member_profile' => $member->toMemberProfile(),
        ];
    }

    public function decline(?string $reason = null): void
    {
        $this->status = 'DECLINED';
        $this->declineReason = $reason;
        $this->certificate = [
            'shared_identifier' => $this->sharedIdentifier,
            'status' => 'DECLINED',
            'good_standing' => false,
            'reason' => $reason,
        ];
    }

    public function toRequestPayload(): array
    {
        return array_filter([
            'shared_identifier' => $this->sharedIdentifier,
            'requesting_member' => $this->requestingMember,
            'requested_member_number' => $this->requestedMemberNumber,
            'notes' => $this->notes,
        ], fn($v) => $v !== null);
    }

    public function toReplyPayload(): array
    {
        return $this->certificate ?? [
            'shared_identifier' => $this->sharedIdentifier,
            'status' => $this->status,
        ];
    }

    public static function fromRequest(array $payload, string $senderMipId, string $senderOrg): self
    {
        return new self(
            sharedIdentifier: $payload['shared_identifier'] ?? '',
            direction: 'inbound',
            targetMipIdentifier: $senderMipId,
            targetOrg: $senderOrg,
            requestingMember: $payload['requesting_member'] ?? [],
            requestedMemberNumber: $payload['requested_member_number'] ?? null,
            notes: $payload['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'shared_identifier' => $this->sharedIdentifier,
            'direction' => $this->direction,
            'target_mip_identifier' => $this->targetMipIdentifier,
            'target_org' => $this->targetOrg,
            'requesting_member' => $this->requestingMember,
            'requested_member_number' => $this->requestedMemberNumber,
            'notes' => $this->notes,
            'status' => $this->status,
            'certificate' => $this->certificate,
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
            requestingMember: $data['requesting_member'] ?? [],
            requestedMemberNumber: $data['requested_member_number'] ?? null,
            notes: $data['notes'] ?? null,
            status: $data['status'] ?? 'PENDING',
            certificate: $data['certificate'] ?? null,
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
