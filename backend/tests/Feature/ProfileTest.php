<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can get profile with balance and assets', function () {
    $user = User::factory()->create(['balance' => '5000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.50000000',
        'locked_amount' => '0.25000000',
    ]);

    $response = $this->actingAs($user)->getJson('/api/profile');

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'balance'],
            'assets' => [
                '*' => ['id', 'user_id', 'symbol', 'amount', 'locked_amount'],
            ],
        ])
        ->assertJsonPath('user.balance', '5000.00000000')
        ->assertJsonPath('assets.0.symbol', 'BTC');
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/profile');

    $response->assertUnauthorized();
});

test('response structure matches expected JSON format', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'ETH',
        'amount' => '10.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($user)->getJson('/api/profile');

    $response->assertOk()
        ->assertJsonCount(2, 'assets')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', $user->name)
        ->assertJsonPath('user.email', $user->email);
});
