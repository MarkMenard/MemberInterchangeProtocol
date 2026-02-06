<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use App\Services\Mip\Crypto;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    private Store $store;
    private Client $client;

    public function __construct()
    {
        $this->store = app(Store::class);
        $this->client = app(Client::class);
    }

    public function index()
    {
        $connections = $this->store->getConnections();
        $identity = $this->store->getIdentity();

        return view('connections.index', [
            'connections' => $connections,
            'identity' => $identity,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'mip_url' => 'required|url',
        ]);

        $targetUrl = rtrim($request->input('mip_url'), '/');

        // Request connection via client
        $result = $this->client->requestConnection($targetUrl);

        if ($result['success']) {
            // Get target profile to create local connection record
            $profileResponse = \Illuminate\Support\Facades\Http::get("{$targetUrl}/profile");
            if ($profileResponse->successful()) {
                $profile = $profileResponse->json()['data'] ?? $profileResponse->json();

                $connection = [
                    'id' => bin2hex(random_bytes(8)),
                    'mip_identifier' => $profile['mip_identifier'],
                    'mip_url' => $targetUrl,
                    'organization_name' => $profile['organization_legal_name'],
                    'contact_person' => $profile['contact_person'] ?? null,
                    'contact_phone' => $profile['contact_phone'] ?? null,
                    'public_key' => $profile['public_key'],
                    'status' => 'PENDING',
                    'direction' => 'outbound',
                    'created_at' => now()->toIso8601String(),
                ];

                $this->store->saveConnection($connection);
                $this->store->addActivity("Sent connection request to {$profile['organization_legal_name']}");
            }

            return redirect()->route('connections.index')
                ->with('success', 'Connection request sent successfully');
        }

        return redirect()->route('connections.index')
            ->with('error', 'Failed to send connection request: ' . ($result['error'] ?? 'Unknown error'));
    }

    public function approve(string $id)
    {
        $connection = $this->store->findConnectionById($id);

        if (!$connection) {
            return redirect()->route('connections.index')
                ->with('error', 'Connection not found');
        }

        // Update status
        $this->store->updateConnection($connection['mip_identifier'], [
            'status' => 'ACTIVE',
            'daily_rate_limit' => 100,
            'approved_at' => now()->toIso8601String(),
        ]);

        // Get updated connection
        $connection = $this->store->findConnection($connection['mip_identifier']);

        // Notify the other party
        $this->client->notifyApproval($connection);

        // Send endorsement
        $this->client->sendEndorsement($connection);

        $this->store->addActivity("Approved connection from {$connection['organization_name']}", 'success');

        return redirect()->route('connections.index')
            ->with('success', 'Connection approved');
    }

    public function decline(string $id)
    {
        $connection = $this->store->findConnectionById($id);

        if (!$connection) {
            return redirect()->route('connections.index')
                ->with('error', 'Connection not found');
        }

        // Update status
        $this->store->updateConnection($connection['mip_identifier'], [
            'status' => 'DECLINED',
            'declined_at' => now()->toIso8601String(),
        ]);

        // Get updated connection
        $connection = $this->store->findConnection($connection['mip_identifier']);

        // Notify the other party
        $this->client->notifyDecline($connection);

        $this->store->addActivity("Declined connection from {$connection['organization_name']}", 'warning');

        return redirect()->route('connections.index')
            ->with('success', 'Connection declined');
    }

    public function revoke(string $id)
    {
        $connection = $this->store->findConnectionById($id);

        if (!$connection) {
            return redirect()->route('connections.index')
                ->with('error', 'Connection not found');
        }

        $this->store->updateConnection($connection['mip_identifier'], [
            'status' => 'REVOKED',
            'revoked_at' => now()->toIso8601String(),
        ]);

        $this->store->addActivity("Revoked connection with {$connection['organization_name']}", 'warning');

        return redirect()->route('connections.index')
            ->with('success', 'Connection revoked');
    }

    public function restore(string $id)
    {
        $connection = $this->store->findConnectionById($id);

        if (!$connection) {
            return redirect()->route('connections.index')
                ->with('error', 'Connection not found');
        }

        $this->store->updateConnection($connection['mip_identifier'], [
            'status' => 'ACTIVE',
            'restored_at' => now()->toIso8601String(),
        ]);

        $this->store->addActivity("Restored connection with {$connection['organization_name']}", 'success');

        return redirect()->route('connections.index')
            ->with('success', 'Connection restored');
    }
}
