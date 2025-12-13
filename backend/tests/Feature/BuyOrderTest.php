<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can place buy order with sufficient balance', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'order' => ['id', 'user_id', 'symbol', 'side', 'price', 'amount', 'status', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('message', 'Order created successfully')
        ->assertJsonPath('order.status', Order::STATUS_OPEN)
        ->assertJsonPath('order.side', 'buy')
        ->assertJsonPath('order.symbol', 'BTC')
        ->assertJsonPath('order.user_id', $user->id);
});

test('buy order deducts correct amount from user balance', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->balance)->toBe('5500.00000000');
});

test('buy order returns 409 with insufficient balance', function () {
    $user = User::factory()->create(['balance' => '1000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Insufficient USD balance. You need $4,500.00 but only have $1,000.00 available.');
});

test('buy order validation rejects missing fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/orders', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
});

test('buy order validation rejects invalid symbol', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'DOGE',
        'side' => 'buy',
        'price' => '100.00',
        'amount' => '1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol']);
});

test('buy order validation rejects invalid side', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'invalid',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['side']);
});

test('buy order validation rejects non-positive price', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '0',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '-100',
        'amount' => '0.1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('buy order validation rejects non-positive amount', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '-1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('unauthenticated request returns 401', function () {
    $response = $this->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(401);
});

test('transaction rollback on insufficient balance leaves no order and balance unchanged', function () {
    $user = User::factory()->create(['balance' => '1000.00000000']);
    $initialOrderCount = Order::count();

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(409);

    expect(Order::count())->toBe($initialOrderCount);

    $user->refresh();
    expect($user->balance)->toBe('1000.00000000');
});
