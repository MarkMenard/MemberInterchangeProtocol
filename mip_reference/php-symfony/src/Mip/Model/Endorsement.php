<?php

namespace App\Mip\Model;

use App\Mip\Crypto;

/**
 * Represents an endorsement of one organization by another
 */
class Endorsement
{
    public function __construct(
        public string $id,
        public string $endorserMipIdentifier,
        public string $endorsedMipIdentifier,
        public string $endorsedPublicKeyFingerprint,
        public string $endorsementDocument,
        public string $endorsementSignature,
        public string $issuedAt,
        public string $expiresAt
    ) {
        if ($this->id === '') {
            $this->id = self::generateUuid();
        }
    }

    public function isExpired(): bool
    {
        try {
            $expiry = new \DateTime($this->expiresAt);
            $now = new \DateTime();
            return $now > $expiry;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function validFor(string $fingerprint): bool
    {
        return !$this->isExpired() && $this->endorsedPublicKeyFingerprint === $fingerprint;
    }

    public function verifySignature(string $endorserPublicKey): bool
    {
        return Crypto::verify($endorserPublicKey, $this->endorsementSignature, $this->endorsementDocument);
    }

    public function toPayload(): array
    {
        return [
            'endorser_mip_identifier' => $this->endorserMipIdentifier,
            'endorsed_mip_identifier' => $this->endorsedMipIdentifier,
            'endorsed_public_key_fingerprint' => $this->endorsedPublicKeyFingerprint,
            'endorsement_document' => $this->endorsementDocument,
            'endorsement_signature' => $this->endorsementSignature,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            id: '',
            endorserMipIdentifier: $payload['endorser_mip_identifier'],
            endorsedMipIdentifier: $payload['endorsed_mip_identifier'],
            endorsedPublicKeyFingerprint: $payload['endorsed_public_key_fingerprint'],
            endorsementDocument: $payload['endorsement_document'],
            endorsementSignature: $payload['endorsement_signature'],
            issuedAt: $payload['issued_at'],
            expiresAt: $payload['expires_at']
        );
    }

    /**
     * Create a new endorsement for another node
     */
    public static function create(NodeIdentity $identity, string $endorsedMipIdentifier, string $endorsedPublicKey): self
    {
        $issuedAt = (new \DateTime())->format(\DateTime::ATOM);
        $expiresAt = (new \DateTime())->modify('+1 year')->format(\DateTime::ATOM);
        $fingerprint = Crypto::fingerprint($endorsedPublicKey);

        $document = json_encode([
            'type' => 'MIP_ENDORSEMENT_V1',
            'endorser_mip_identifier' => $identity->mipIdentifier,
            'endorsed_mip_identifier' => $endorsedMipIdentifier,
            'endorsed_public_key_fingerprint' => $fingerprint,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ], JSON_UNESCAPED_SLASHES);

        $signature = Crypto::sign($identity->privateKey, $document);

        return new self(
            id: '',
            endorserMipIdentifier: $identity->mipIdentifier,
            endorsedMipIdentifier: $endorsedMipIdentifier,
            endorsedPublicKeyFingerprint: $fingerprint,
            endorsementDocument: $document,
            endorsementSignature: $signature,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'endorser_mip_identifier' => $this->endorserMipIdentifier,
            'endorsed_mip_identifier' => $this->endorsedMipIdentifier,
            'endorsed_public_key_fingerprint' => $this->endorsedPublicKeyFingerprint,
            'endorsement_document' => $this->endorsementDocument,
            'endorsement_signature' => $this->endorsementSignature,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            endorserMipIdentifier: $data['endorser_mip_identifier'],
            endorsedMipIdentifier: $data['endorsed_mip_identifier'],
            endorsedPublicKeyFingerprint: $data['endorsed_public_key_fingerprint'],
            endorsementDocument: $data['endorsement_document'],
            endorsementSignature: $data['endorsement_signature'],
            issuedAt: $data['issued_at'],
            expiresAt: $data['expires_at']
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
