<?php

namespace App\Mip\Model;

/**
 * Represents a member in the local organization
 */
class Member
{
    public function __construct(
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public ?string $prefix = null,
        public ?string $middleName = null,
        public ?string $suffix = null,
        public ?string $honorific = null,
        public ?string $rank = null,
        public ?string $birthdate = null,
        public ?int $yearsInGoodStanding = null,
        public string $status = 'Active',
        public bool $isActive = true,
        public bool $goodStanding = true,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $cell = null,
        public array $address = [],
        public array $affiliations = [],
        public array $lifeCycleEvents = []
    ) {}

    public function getFullName(): string
    {
        $parts = array_filter([
            $this->prefix,
            $this->firstName,
            $this->middleName,
            $this->lastName,
            $this->suffix,
        ], fn($p) => $p !== null && $p !== '');

        return implode(' ', $parts);
    }

    public function getPartyShortName(): string
    {
        $lastInitial = !empty($this->lastName) ? $this->lastName[0] : '';
        return "{$this->firstName} {$lastInitial}";
    }

    public function getMemberType(): string
    {
        foreach ($this->affiliations as $affiliation) {
            if ($affiliation['is_active'] ?? false) {
                return $affiliation['member_type'] ?? 'Member';
            }
        }
        return 'Member';
    }

    public function toSearchResult(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'birthdate' => $this->birthdate,
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
            ],
            'group_status' => [
                'status' => $this->status,
                'is_active' => $this->isActive,
                'good_standing' => $this->goodStanding,
            ],
            'affiliations' => array_map(fn($aff) => [
                'local_name' => $aff['local_name'] ?? null,
                'local_status' => $aff['local_status'] ?? $aff['status'] ?? null,
                'is_active' => $aff['is_active'] ?? false,
                'member_type' => $aff['member_type'] ?? null,
            ], $this->affiliations),
        ];
    }

    public function toMemberProfile(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'prefix' => $this->prefix,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'suffix' => $this->suffix,
            'honorific' => $this->honorific,
            'rank' => $this->rank,
            'birthdate' => $this->birthdate,
            'years_in_good_standing' => $this->yearsInGoodStanding,
            'group_status' => [
                'status' => $this->status,
                'is_active' => $this->isActive,
            ],
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'cell' => $this->cell,
                'address' => $this->address,
            ],
            'affiliations' => $this->affiliations,
            'life_cycle_events' => $this->lifeCycleEvents,
        ];
    }

    public function toStatusCheck(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'member_type' => $this->getMemberType(),
            'party_short_name' => $this->getPartyShortName(),
            'group_status' => [
                'status' => $this->status,
                'is_active' => $this->isActive,
                'good_standing' => $this->goodStanding,
            ],
        ];
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            memberNumber: $config['member_number'],
            firstName: $config['first_name'],
            lastName: $config['last_name'],
            prefix: $config['prefix'] ?? null,
            middleName: $config['middle_name'] ?? null,
            suffix: $config['suffix'] ?? null,
            honorific: $config['honorific'] ?? null,
            rank: $config['rank'] ?? null,
            birthdate: $config['birthdate'] ?? null,
            yearsInGoodStanding: $config['years_in_good_standing'] ?? null,
            status: $config['status'] ?? 'Active',
            isActive: $config['is_active'] ?? true,
            goodStanding: $config['good_standing'] ?? true,
            email: $config['email'] ?? null,
            phone: $config['phone'] ?? null,
            cell: $config['cell'] ?? null,
            address: $config['address'] ?? [],
            affiliations: $config['affiliations'] ?? [],
            lifeCycleEvents: $config['life_cycle_events'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'member_number' => $this->memberNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'prefix' => $this->prefix,
            'middle_name' => $this->middleName,
            'suffix' => $this->suffix,
            'honorific' => $this->honorific,
            'rank' => $this->rank,
            'birthdate' => $this->birthdate,
            'years_in_good_standing' => $this->yearsInGoodStanding,
            'status' => $this->status,
            'is_active' => $this->isActive,
            'good_standing' => $this->goodStanding,
            'email' => $this->email,
            'phone' => $this->phone,
            'cell' => $this->cell,
            'address' => $this->address,
            'affiliations' => $this->affiliations,
            'life_cycle_events' => $this->lifeCycleEvents,
        ];
    }

    public static function fromArray(array $data): self
    {
        return self::fromConfig($data);
    }
}
