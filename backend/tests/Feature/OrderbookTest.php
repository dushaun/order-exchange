<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns open buy and sell orders for symbol', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Order::factory()->create([
        'user_id' => $user1->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '44000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    Order::factory()->create([
        'user_id' => $user2->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user1)->getJson('/api/orders?symbol=BTC');

    $response->assertOk()
        ->assertJsonStructure([
            'buy_orders' => [
                '*' => ['id', 'symbol', 'side', 'price', 'amount', 'created_at'],
            ],
            'sell_orders' => [
                '*' => ['id', 'symbol', 'side', 'price', 'amount', 'created_at'],
            ],
        ])
        ->assertJsonCount(1, 'buy_orders')
        ->assertJsonCount(1, 'sell_orders');
});

test('response excludes user_id and status fields', function () {
    $user = User::factory()->create();
    Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk();
    $buyOrder = $response->json('buy_orders.0');
    expect($buyOrder)->not->toHaveKey('user_id')
        ->not->toHaveKey('status')
        ->not->toHaveKey('updated_at');
});

test('buy orders sorted by price descending then created_at ascending', function () {
    $user = User::factory()->create();

    $order1 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '44000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(10),
    ]);

    $order2 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(5),
    ]);

    $order3 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(15),
    ]);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk();
    $buyOrders = $response->json('buy_orders');

    expect($buyOrders[0]['id'])->toBe($order3->id)
        ->and($buyOrders[1]['id'])->toBe($order2->id)
        ->and($buyOrders[2]['id'])->toBe($order1->id);
});

test('sell orders sorted by price ascending then created_at ascending', function () {
    $user = User::factory()->create();

    $order1 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '46000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(10),
    ]);

    $order2 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(5),
    ]);

    $order3 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(15),
    ]);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk();
    $sellOrders = $response->json('sell_orders');

    expect($sellOrders[0]['id'])->toBe($order3->id)
        ->and($sellOrders[1]['id'])->toBe($order2->id)
        ->and($sellOrders[2]['id'])->toBe($order1->id);
});

test('excludes filled and cancelled orders', function () {
    $user = User::factory()->create();

    $openOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'status' => Order::STATUS_OPEN,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'status' => Order::STATUS_FILLED,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'status' => Order::STATUS_CANCELLED,
    ]);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk()
        ->assertJsonCount(1, 'buy_orders')
        ->assertJsonPath('buy_orders.0.id', $openOrder->id);
});

test('returns empty arrays when no open orders', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk()
        ->assertJson([
            'buy_orders' => [],
            'sell_orders' => [],
        ]);
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/orders?symbol=BTC');

    $response->assertUnauthorized();
});

test('missing symbol returns 422', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/orders');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['symbol']);
});

test('invalid symbol returns 422', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=DOGE');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['symbol']);
});

test('only returns orders for requested symbol', function () {
    $user = User::factory()->create();

    $btcOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'status' => Order::STATUS_OPEN,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'ETH',
        'side' => 'buy',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

    $response->assertOk()
        ->assertJsonCount(1, 'buy_orders')
        ->assertJsonPath('buy_orders.0.id', $btcOrder->id);
});
