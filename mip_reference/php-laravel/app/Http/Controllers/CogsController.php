<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use Illuminate\Http\Request;

class CogsController extends Controller
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
        $cogsRequests = $this->store->getCogsRequests();
        $identity = $this->store->getIdentity();

        // Separate by direction
        $outbound = array_filter($cogsRequests, fn($r) => $r['direction'] === 'outbound');
        $inbound = array_filter($cogsRequests, fn($r) => $r['direction'] === 'inbound');

        return view('cogs.index', [
            'outbound_requests' => $outbound,
            'inbound_requests' => $inbound,
            'identity' => $identity,
        ]);
    }

    public function create()
    {
        $connections = $this->store->getConnections();
        $identity = $this->store->getIdentity();

        // Only show active connections
        $activeConnections = array_filter($connections, fn($c) => $c['status'] === 'ACTIVE');

        return view('cogs.create', [
            'connections' => $activeConnections,
            'identity' => $identity,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_mip_id' => 'required|string',
            'member_number' => 'required|string',
        ]);

        $connection = $this->store->findConnection($request->input('target_mip_id'));

        if (!$connection || $connection['status'] !== 'ACTIVE') {
            return redirect()->route('cogs.create')
                ->with('error', 'Invalid or inactive connection');
        }

        // Create COGS request
        $cogsRequest = [
            'direction' => 'outbound',
            'target_mip_identifier' => $connection['mip_identifier'],
            'target_organization' => $connection['organization_name'],
            'status' => 'PENDING',
            'member_number' => $request->input('member_number'),
            'member_profile' => null,
        ];

        $requestId = $this->store->saveCogsRequest($cogsRequest);

        // Send COGS request to target
        $result = $this->client->requestCogs(
            $connection,
            $request->input('member_number'),
            $requestId
        );

        if ($result['success']) {
            $this->store->addActivity("Requested COGS from {$connection['organization_name']}");
            return redirect()->route('cogs.index')
                ->with('success', 'COGS request sent');
        }

        return redirect()->route('cogs.index')
            ->with('error', 'Failed to send COGS request');
    }

    public function approve(string $id)
    {
        $request = $this->store->findCogsRequest($id);

        if (!$request || $request['direction'] !== 'inbound') {
            return redirect()->route('cogs.index')
                ->with('error', 'Invalid COGS request');
        }

        // Find the member
        $member = $this->store->findMember($request['member_number']);

        if (!$member) {
            return redirect()->route('cogs.index')
                ->with('error', 'Member not found');
        }

        // Update request status
        $this->store->updateCogsRequest($id, [
            'status' => 'APPROVED',
            'member_profile' => $member,
            'approved_at' => now()->toIso8601String(),
        ]);

        // Send reply
        $connection = $this->store->findConnection($request['source_mip_identifier']);
        if ($connection) {
            $this->client->cogsReply($connection, $request['original_request_id'], $member, true);
        }

        $this->store->addActivity("Approved COGS for {$member['first_name']} {$member['last_name']}", 'success');

        return redirect()->route('cogs.index')
            ->with('success', 'COGS approved and sent');
    }

    public function decline(string $id)
    {
        $request = $this->store->findCogsRequest($id);

        if (!$request || $request['direction'] !== 'inbound') {
            return redirect()->route('cogs.index')
                ->with('error', 'Invalid COGS request');
        }

        $this->store->updateCogsRequest($id, [
            'status' => 'DECLINED',
            'declined_at' => now()->toIso8601String(),
        ]);

        // Send declined reply
        $connection = $this->store->findConnection($request['source_mip_identifier']);
        if ($connection) {
            $this->client->cogsReply($connection, $request['original_request_id'], null, false);
        }

        $this->store->addActivity("Declined COGS request", 'warning');

        return redirect()->route('cogs.index')
            ->with('success', 'COGS request declined');
    }

    public function show(string $id)
    {
        $request = $this->store->findCogsRequest($id);
        $identity = $this->store->getIdentity();

        if (!$request) {
            abort(404, 'COGS request not found');
        }

        return view('cogs.show', [
            'request' => $request,
            'identity' => $identity,
        ]);
    }
}
