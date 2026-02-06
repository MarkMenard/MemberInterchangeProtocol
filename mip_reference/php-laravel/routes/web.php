<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CogsController;
use App\Http\Controllers\MipApiController;

// Admin UI Routes
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');

// Connections
Route::get('/connections', [ConnectionController::class, 'index'])->name('connections.index');
Route::post('/connections', [ConnectionController::class, 'create'])->name('connections.create');
Route::post('/connections/{id}/approve', [ConnectionController::class, 'approve'])->name('connections.approve');
Route::post('/connections/{id}/decline', [ConnectionController::class, 'decline'])->name('connections.decline');
Route::post('/connections/{id}/revoke', [ConnectionController::class, 'revoke'])->name('connections.revoke');
Route::post('/connections/{id}/restore', [ConnectionController::class, 'restore'])->name('connections.restore');

// Members
Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::get('/members/{memberNumber}', [MemberController::class, 'show'])->name('members.show');

// Searches
Route::get('/searches', [SearchController::class, 'index'])->name('searches.index');
Route::get('/searches/new', [SearchController::class, 'create'])->name('searches.create');
Route::post('/searches', [SearchController::class, 'store'])->name('searches.store');
Route::post('/searches/{id}/approve', [SearchController::class, 'approve'])->name('searches.approve');
Route::post('/searches/{id}/decline', [SearchController::class, 'decline'])->name('searches.decline');

// COGS (Certificate of Good Standing)
Route::get('/cogs', [CogsController::class, 'index'])->name('cogs.index');
Route::get('/cogs/new', [CogsController::class, 'create'])->name('cogs.create');
Route::post('/cogs', [CogsController::class, 'store'])->name('cogs.store');
Route::get('/cogs/{id}', [CogsController::class, 'show'])->name('cogs.show');
Route::post('/cogs/{id}/approve', [CogsController::class, 'approve'])->name('cogs.approve');
Route::post('/cogs/{id}/decline', [CogsController::class, 'decline'])->name('cogs.decline');

// Test API Routes (for curl testing - bypasses CSRF)
Route::prefix('api')->withoutMiddleware(['web'])->group(function () {
    Route::post('/connections', [ConnectionController::class, 'create']);
    Route::post('/connections/{id}/approve', [ConnectionController::class, 'approve']);
    Route::post('/searches', [SearchController::class, 'store']);
    Route::post('/searches/{id}/approve', [SearchController::class, 'approve']);
    Route::post('/cogs', [CogsController::class, 'store']);
    Route::post('/cogs/{id}/approve', [CogsController::class, 'approve']);
});

// MIP Protocol API Routes
Route::prefix('mip/node/{mipId}')->group(function () {
    // Connection Protocol
    Route::post('/mip_connections', [MipApiController::class, 'connectionRequest']);
    Route::post('/mip_connections/approved', [MipApiController::class, 'connectionApproved']);
    Route::post('/mip_connections/declined', [MipApiController::class, 'connectionDeclined']);
    Route::post('/endorsements', [MipApiController::class, 'receiveEndorsement']);

    // Member Protocol
    Route::post('/mip_member_searches', [MipApiController::class, 'memberSearch']);
    Route::post('/mip_member_searches/reply', [MipApiController::class, 'memberSearchReply']);
    Route::post('/certificates_of_good_standing', [MipApiController::class, 'cogsRequest']);
    Route::post('/certificates_of_good_standing/reply', [MipApiController::class, 'cogsReply']);
});
