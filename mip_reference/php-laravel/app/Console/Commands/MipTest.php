<?php

namespace App\Console\Commands;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MipTest extends Command
{
    protected $signature = 'mip:test {action} {--target=}';
    protected $description = 'Test MIP protocol operations';

    public function handle()
    {
        $action = $this->argument('action');
        $target = $this->option('target');

        switch ($action) {
            case 'status':
                $this->showStatus();
                break;
            case 'connect':
                $this->requestConnection($target);
                break;
            case 'approve':
                $this->approveConnection($target);
                break;
            case 'search':
                $this->memberSearch($target);
                break;
            default:
                $this->error("Unknown action: $action");
        }
    }

    private function showStatus()
    {
        $store = Store::getInstance();
        $identity = $store->getIdentity();

        $this->info("Node: {$identity['organization_name']}");
        $this->info("MIP ID: {$identity['mip_identifier']}");
        $this->info("URL: {$identity['mip_url']}");

        $connections = $store->getConnections();
        $this->info("\nConnections: " . count($connections));
        foreach ($connections as $conn) {
            $this->line("  - {$conn['organization_name']} ({$conn['status']})");
        }

        $this->info("\nMembers: " . count($store->getMembers()));
        $this->info("Endorsements: " . count($store->getEndorsements()));
    }

    private function requestConnection(string $targetUrl)
    {
        if (!$targetUrl) {
            $this->error("Please specify --target=<url>");
            return;
        }

        $client = app(Client::class);
        $store = Store::getInstance();

        $this->info("Requesting connection to: $targetUrl");

        // Fetch target profile
        $profileResponse = Http::get("$targetUrl/profile");
        if (!$profileResponse->successful()) {
            $this->error("Failed to fetch target profile");
            return;
        }

        $profile = $profileResponse->json()['data'];
        $this->info("Target: {$profile['organization_legal_name']}");

        // Request connection
        $result = $client->requestConnection($targetUrl);

        if ($result['success']) {
            // Save local connection record
            $connection = [
                'id' => bin2hex(random_bytes(8)),
                'mip_identifier' => $profile['mip_identifier'],
                'mip_url' => $targetUrl,
                'organization_name' => $profile['organization_legal_name'],
                'contact_person' => $profile['contact_person'] ?? null,
                'public_key' => $profile['public_key'],
                'status' => 'PENDING',
                'direction' => 'outbound',
                'created_at' => now()->toIso8601String(),
            ];
            $store->saveConnection($connection);

            $this->info("Connection request sent successfully!");
            $this->info("Response: " . json_encode($result['data'] ?? []));
        } else {
            $this->error("Failed: " . ($result['error'] ?? 'Unknown error'));
        }
    }

    private function approveConnection(string $mipId)
    {
        if (!$mipId) {
            $this->error("Please specify --target=<mip_id>");
            return;
        }

        $store = Store::getInstance();
        $client = app(Client::class);

        // Find pending connection
        $connection = $store->findConnection($mipId);
        if (!$connection) {
            // Try to find by partial match
            foreach ($store->getConnections() as $conn) {
                if (str_starts_with($conn['mip_identifier'], $mipId)) {
                    $connection = $conn;
                    break;
                }
            }
        }

        if (!$connection) {
            $this->error("Connection not found: $mipId");
            $this->showStatus();
            return;
        }

        if ($connection['status'] !== 'PENDING') {
            $this->error("Connection is not pending: {$connection['status']}");
            return;
        }

        $this->info("Approving connection from: {$connection['organization_name']}");

        // Update status
        $store->updateConnection($connection['mip_identifier'], [
            'status' => 'ACTIVE',
            'daily_rate_limit' => 100,
            'approved_at' => now()->toIso8601String(),
        ]);

        // Get updated connection
        $connection = $store->findConnection($connection['mip_identifier']);

        // Notify approval
        $this->info("Sending approval notification...");
        $result = $client->notifyApproval($connection);
        $this->info("Notification result: " . ($result['success'] ? 'OK' : 'Failed'));

        // Send endorsement
        $this->info("Sending endorsement...");
        $result = $client->sendEndorsement($connection);
        $this->info("Endorsement result: " . ($result['success'] ? 'OK' : 'Failed'));

        $this->info("Connection approved!");
    }

    private function memberSearch(string $targetMipId)
    {
        if (!$targetMipId) {
            $this->error("Please specify --target=<mip_id>");
            return;
        }

        $store = Store::getInstance();
        $client = app(Client::class);

        $connection = $store->findConnection($targetMipId);
        if (!$connection || $connection['status'] !== 'ACTIVE') {
            $this->error("No active connection found for: $targetMipId");
            return;
        }

        $this->info("Sending member search to: {$connection['organization_name']}");

        $requestId = bin2hex(random_bytes(8));
        $criteria = ['last_name' => 'Smith'];

        $result = $client->memberSearch($connection, $criteria, $requestId);

        if ($result['success']) {
            $this->info("Search request sent!");
        } else {
            $this->error("Failed: " . ($result['error'] ?? 'Unknown error'));
        }
    }
}
