<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can cancel their own open buy order', function () {
    $user = User::factory()->create(['balance' => '5500.00000000']);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'order' => ['id', 'status'],
        ])
        ->assertJsonPath('message', 'Order cancelled successfully')
        ->assertJsonPath('order.status', Order::STATUS_CANCELLED);
});

test('authenticated user can cancel their own open sell order', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.50000000',
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.50000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk()
        ->assertJsonPath('message', 'Order cancelled successfully')
        ->assertJsonPath('order.status', Order::STATUS_CANCELLED);
});

test('cancelling buy order refunds USD to balance', function () {
    $user = User::factory()->create(['balance' => '5500.00000000']);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk();

    $user->refresh();
    expect($user->balance)->toBe('10000.00000000');
});

test('cancelling sell order moves locked_amount back to amount', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.50000000',
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.50000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertOk();

    $asset->refresh();
    expect($asset->amount)->toBe('1.00000000');
    expect($asset->locked_amount)->toBe('0.00000000');
});

test('user cannot cancel another users order', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_OPEN,
    ]);

    $response = $this->actingAs($otherUser)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertForbidden()
        ->assertJsonPath('message', 'You can only cancel your own orders');
});

test('cannot cancel filled order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_FILLED,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only open orders can be cancelled');
});

test('cannot cancel already cancelled order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_CANCELLED,
    ]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only open orders can be cancelled');
});

test('unauthenticated user cannot cancel order', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_OPEN]);

    $response = $this->postJson("/api/orders/{$order->id}/cancel");

    $response->assertUnauthorized();
});

test('cancelling nonexistent order returns 404', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/orders/99999/cancel');

    $response->assertNotFound();
});
