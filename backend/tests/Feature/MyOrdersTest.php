<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can get their orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->getJson('/api/my-orders');

    $response->assertOk()
        ->assertJsonStructure([
            'orders' => [
                '*' => ['id', 'user_id', 'symbol', 'side', 'price', 'amount', 'status', 'created_at', 'updated_at'],
            ],
        ])
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.id', $order->id);
});

test('my orders returns orders sorted by created_at descending', function () {
    $user = User::factory()->create();

    $oldOrder = Order::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subHour(),
    ]);

    $newOrder = Order::factory()->create([
        'user_id' => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/my-orders');

    $response->assertOk()
        ->assertJsonCount(2, 'orders')
        ->assertJsonPath('orders.0.id', $newOrder->id)
        ->assertJsonPath('orders.1.id', $oldOrder->id);
});

test('my orders only returns authenticated users orders', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userOrder = Order::factory()->create(['user_id' => $user->id]);
    $otherOrder = Order::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson('/api/my-orders');

    $response->assertOk()
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.id', $userOrder->id);
});

test('my orders returns all order statuses', function () {
    $user = User::factory()->create();

    $openOrder = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_OPEN,
    ]);
    $filledOrder = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_FILLED,
    ]);
    $cancelledOrder = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_CANCELLED,
    ]);

    $response = $this->actingAs($user)->getJson('/api/my-orders');

    $response->assertOk()
        ->assertJsonCount(3, 'orders');

    $statuses = collect($response->json('orders'))->pluck('status')->sort()->values()->all();
    expect($statuses)->toEqual([
        Order::STATUS_OPEN,
        Order::STATUS_FILLED,
        Order::STATUS_CANCELLED,
    ]);
});

test('my orders returns empty array when user has no orders', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/my-orders');

    $response->assertOk()
        ->assertJsonCount(0, 'orders')
        ->assertJsonPath('orders', []);
});

test('unauthenticated user cannot access my orders', function () {
    $response = $this->getJson('/api/my-orders');

    $response->assertUnauthorized();
});
