<?php

namespace App\Providers;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use App\Services\Mip\Crypto;
use App\Services\Mip\Identifier;
use Illuminate\Support\ServiceProvider;

class MipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Store as singleton using the static instance
        $this->app->singleton(Store::class, function ($app) {
            return Store::getInstance();
        });

        // Register Client
        $this->app->singleton(Client::class, function ($app) {
            return new Client($app->make(Store::class));
        });
    }

    public function boot(): void
    {
        // Initialize node on boot
        $this->initializeNode();
    }

    private function initializeNode(): void
    {
        $store = Store::getInstance();

        // Check if already initialized
        if ($store->getIdentity()) {
            return;
        }

        $nodeConfig = env('MIP_NODE_CONFIG', 'node1');
        $configPath = config_path("mip/{$nodeConfig}.php");

        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;
        $port = $config['port'] ?? 8000;

        // Generate key pair
        $keyPair = Crypto::generateKeyPair();

        // Generate MIP identifier
        $mipIdentifier = Identifier::generate($config['organization_name']);

        // Create identity
        $identity = [
            'mip_identifier' => $mipIdentifier,
            'mip_url' => "http://localhost:{$port}",
            'organization_name' => $config['organization_name'],
            'contact_person' => $config['contact_person'],
            'contact_phone' => $config['contact_phone'],
            'share_my_organization' => $config['share_my_organization'] ?? true,
            'trust_threshold' => $config['trust_threshold'] ?? 1,
            'public_key' => $keyPair['public_key'],
            'private_key' => $keyPair['private_key'],
        ];

        $store->setIdentity($identity);

        // Load members
        $store->setMembers($config['members'] ?? []);

        $store->addActivity("Node initialized: {$config['organization_name']}", 'success');
    }
}
