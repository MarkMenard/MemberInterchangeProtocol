<?php

namespace App\Http\Controllers;

use App\Services\Mip\Store;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    private Store $store;

    public function __construct()
    {
        $this->store = app(Store::class);
    }

    public function index()
    {
        $members = $this->store->getMembers();
        $identity = $this->store->getIdentity();

        return view('members.index', [
            'members' => $members,
            'identity' => $identity,
        ]);
    }

    public function show(string $memberNumber)
    {
        $member = $this->store->findMember($memberNumber);
        $identity = $this->store->getIdentity();

        if (!$member) {
            abort(404, 'Member not found');
        }

        return view('members.show', [
            'member' => $member,
            'identity' => $identity,
        ]);
    }
}
