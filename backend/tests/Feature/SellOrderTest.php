<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can place sell order with sufficient assets', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'order' => ['id', 'user_id', 'symbol', 'side', 'price', 'amount', 'status', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('message', 'Order created successfully')
        ->assertJsonPath('order.status', Order::STATUS_OPEN)
        ->assertJsonPath('order.side', 'sell')
        ->assertJsonPath('order.symbol', 'BTC')
        ->assertJsonPath('order.user_id', $user->id);
});

test('sell order moves amount from available to locked', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(201);

    $asset->refresh();
    expect($asset->amount)->toBe('0.50000000');
    expect($asset->locked_amount)->toBe('0.50000000');
});

test('sell order returns 409 with insufficient assets', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.10000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Insufficient BTC. You need 0.5 but only have 0.10000000 available.');
});

test('sell order returns 409 when user has no asset record for symbol', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Insufficient BTC. You need 0.5 but only have 0.00000000 available.');
});

test('sell order validation rejects missing fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/orders', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
});

test('sell order validation rejects invalid symbol', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'DOGE',
        'side' => 'sell',
        'price' => '100.00',
        'amount' => '1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol']);
});

test('sell order validation rejects invalid side', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'invalid',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['side']);
});

test('sell order validation rejects non-positive price', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '0',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '-100',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('sell order validation rejects non-positive amount', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '-1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('unauthenticated sell order request returns 401', function () {
    $response = $this->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(401);
});

test('transaction rollback on insufficient assets leaves no order and asset unchanged', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.10000000',
        'locked_amount' => '0.00000000',
    ]);
    $initialOrderCount = Order::count();

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(409);

    expect(Order::count())->toBe($initialOrderCount);

    $asset->refresh();
    expect($asset->amount)->toBe('0.10000000');
    expect($asset->locked_amount)->toBe('0.00000000');
});
