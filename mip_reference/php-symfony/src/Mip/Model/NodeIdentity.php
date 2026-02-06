<?php

namespace App\Mip\Model;

use App\Mip\Crypto;
use App\Mip\Identifier;

/**
 * Represents this node's identity in the MIP network
 */
class NodeIdentity
{
    public function __construct(
        public string $mipIdentifier,
        public string $privateKey,
        public string $publicKey,
        public string $organizationName,
        public string $contactPerson,
        public string $contactPhone,
        public string $mipUrl,
        public bool $shareMyOrganization = true,
        public int $trustThreshold = 1,
        public int $port = 4013
    ) {}

    public function getPublicKeyFingerprint(): string
    {
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
     * Generate a new node identity from config
     */
    public static function fromConfig(array $config, int $port): self
    {
        $keys = Crypto::generateKeyPair();
        $mipId = Identifier::generate($config['organization_name']);

        return new self(
            mipIdentifier: $mipId,
            privateKey: $keys['private_key'],
            publicKey: $keys['public_key'],
            organizationName: $config['organization_name'],
            contactPerson: $config['contact_person'] ?? '',
            contactPhone: $config['contact_phone'] ?? '',
            mipUrl: "http://localhost:{$port}/mip/node/{$mipId}",
            shareMyOrganization: $config['share_my_organization'] ?? true,
            trustThreshold: $config['trust_threshold'] ?? 1,
            port: $port
        );
    }

    public function toArray(): array
    {
        return [
            'mip_identifier' => $this->mipIdentifier,
            'private_key' => $this->privateKey,
            'public_key' => $this->publicKey,
            'organization_name' => $this->organizationName,
            'contact_person' => $this->contactPerson,
            'contact_phone' => $this->contactPhone,
            'mip_url' => $this->mipUrl,
            'share_my_organization' => $this->shareMyOrganization,
            'trust_threshold' => $this->trustThreshold,
            'port' => $this->port,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mipIdentifier: $data['mip_identifier'],
            privateKey: $data['private_key'],
            publicKey: $data['public_key'],
            organizationName: $data['organization_name'],
            contactPerson: $data['contact_person'] ?? '',
            contactPhone: $data['contact_phone'] ?? '',
            mipUrl: $data['mip_url'],
            shareMyOrganization: $data['share_my_organization'] ?? true,
            trustThreshold: $data['trust_threshold'] ?? 1,
            port: $data['port'] ?? 4013
        );
    }
}
