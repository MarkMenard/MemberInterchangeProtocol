<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private Store $store;

    public function __construct()
    {
        $this->store = app(Store::class);
    }

    public function index()
    {
        $identity = $this->store->getIdentity();
        $connections = $this->store->getConnections();
        $members = $this->store->getMembers();
        $searchRequests = $this->store->getSearchRequests();
        $cogsRequests = $this->store->getCogsRequests();
        $activityLog = array_slice($this->store->getActivityLog(), 0, 10);

        $activeConnections = array_filter($connections, fn($c) => $c['status'] === 'ACTIVE');
        $pendingInbound = array_filter($connections, fn($c) => $c['status'] === 'PENDING' && $c['direction'] === 'inbound');
        $pendingOutbound = array_filter($connections, fn($c) => $c['status'] === 'PENDING' && $c['direction'] === 'outbound');

        return view('dashboard', [
            'identity' => $identity,
            'stats' => [
                'total_connections' => count($connections),
                'active_connections' => count($activeConnections),
                'pending_inbound' => count($pendingInbound),
                'pending_outbound' => count($pendingOutbound),
                'total_members' => count($members),
                'total_searches' => count($searchRequests),
                'total_cogs' => count($cogsRequests),
            ],
            'activity_log' => $activityLog,
        ]);
    }

    public function profile()
    {
        $identity = $this->store->getIdentity();

        return response()->json([
            'data' => [
                'mip_identifier' => $identity['mip_identifier'],
                'mip_url' => $identity['mip_url'],
                'organization_legal_name' => $identity['organization_name'],
                'contact_person' => $identity['contact_person'],
                'contact_phone' => $identity['contact_phone'],
                'share_my_organization' => $identity['share_my_organization'],
                'public_key' => $identity['public_key'],
            ],
        ]);
    }
}
