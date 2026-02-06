<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use App\Services\Mip\Client;
use Illuminate\Http\Request;

class SearchController extends Controller
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
        $searchRequests = $this->store->getSearchRequests();
        $identity = $this->store->getIdentity();

        // Separate by direction
        $outbound = array_filter($searchRequests, fn($r) => $r['direction'] === 'outbound');
        $inbound = array_filter($searchRequests, fn($r) => $r['direction'] === 'inbound');

        return view('searches.index', [
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

        return view('searches.create', [
            'connections' => $activeConnections,
            'identity' => $identity,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_mip_id' => 'required|string',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'member_number' => 'nullable|string',
        ]);

        $connection = $this->store->findConnection($request->input('target_mip_id'));

        if (!$connection || $connection['status'] !== 'ACTIVE') {
            return redirect()->route('searches.create')
                ->with('error', 'Invalid or inactive connection');
        }

        // Create search request
        $searchRequest = [
            'direction' => 'outbound',
            'target_mip_identifier' => $connection['mip_identifier'],
            'target_organization' => $connection['organization_name'],
            'status' => 'PENDING',
            'criteria' => [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'member_number' => $request->input('member_number'),
            ],
            'results' => null,
        ];

        $requestId = $this->store->saveSearchRequest($searchRequest);

        // Send search request to target
        $result = $this->client->memberSearch(
            $connection,
            $searchRequest['criteria'],
            $requestId
        );

        if ($result['success']) {
            $this->store->addActivity("Sent member search to {$connection['organization_name']}");
            return redirect()->route('searches.index')
                ->with('success', 'Search request sent');
        }

        return redirect()->route('searches.index')
            ->with('error', 'Failed to send search request');
    }

    public function approve(string $id)
    {
        $request = $this->store->findSearchRequest($id);

        if (!$request || $request['direction'] !== 'inbound') {
            return redirect()->route('searches.index')
                ->with('error', 'Invalid search request');
        }

        // Perform the search
        $results = $this->store->searchMembers($request['criteria'] ?? []);

        // Format results for response
        $formattedResults = array_map(function ($member) {
            return [
                'member_number' => $member['member_number'],
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'status' => $member['status'] ?? 'Active',
                'good_standing' => $member['good_standing'] ?? false,
            ];
        }, $results);

        // Update request status
        $this->store->updateSearchRequest($id, [
            'status' => 'APPROVED',
            'results' => $formattedResults,
            'approved_at' => now()->toIso8601String(),
        ]);

        // Send reply
        $connection = $this->store->findConnection($request['source_mip_identifier']);
        if ($connection) {
            $this->client->memberSearchReply($connection, $request['original_request_id'], $formattedResults);
        }

        $this->store->addActivity("Approved search request, found " . count($formattedResults) . " result(s)", 'success');

        return redirect()->route('searches.index')
            ->with('success', 'Search approved and results sent');
    }

    public function decline(string $id)
    {
        $request = $this->store->findSearchRequest($id);

        if (!$request || $request['direction'] !== 'inbound') {
            return redirect()->route('searches.index')
                ->with('error', 'Invalid search request');
        }

        $this->store->updateSearchRequest($id, [
            'status' => 'DECLINED',
            'declined_at' => now()->toIso8601String(),
        ]);

        // Send empty reply
        $connection = $this->store->findConnection($request['source_mip_identifier']);
        if ($connection) {
            $this->client->memberSearchReply($connection, $request['original_request_id'], []);
        }

        $this->store->addActivity("Declined search request", 'warning');

        return redirect()->route('searches.index')
            ->with('success', 'Search request declined');
    }
}
