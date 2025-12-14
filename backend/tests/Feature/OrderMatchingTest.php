<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('placing matching buy order fills both orders', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_FILLED);
});

test('placing matching sell order fills both orders', function () {
    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    $buyOrder = Order::where('user_id', $buyer->id)->first();
    expect($buyOrder->status)->toBe(Order::STATUS_FILLED);
});

test('non-matching order remains open', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '46000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_OPEN);

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_OPEN);
});

test('match selects oldest compatible order when multiple exist', function () {
    $seller1 = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller1->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $seller2 = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller1)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($seller2)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $seller1Order = Order::where('user_id', $seller1->id)->first();
    expect($seller1Order->status)->toBe(Order::STATUS_FILLED);

    $seller2Order = Order::where('user_id', $seller2->id)->first();
    expect($seller2Order->status)->toBe(Order::STATUS_OPEN);
});

test('no match when amounts differ', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.2',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_OPEN);

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_OPEN);
});

test('response includes updated order status', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'order' => ['id', 'user_id', 'symbol', 'side', 'price', 'amount', 'status'],
        ])
        ->assertJsonPath('order.status', Order::STATUS_FILLED);
});

test('buy order matches sell order with lower price', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_FILLED);
});

test('sell order matches buy order with higher price', function () {
    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00',
        'amount' => '0.1',
    ]);

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    $buyOrder = Order::where('user_id', $buyer->id)->first();
    expect($buyOrder->status)->toBe(Order::STATUS_FILLED);
});

test('user cannot match their own order', function () {
    $user = User::factory()->create(['balance' => '50000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_OPEN);

    $orders = Order::where('user_id', $user->id)->get();
    expect($orders)->toHaveCount(2);
    expect($orders[0]->status)->toBe(Order::STATUS_OPEN);
    expect($orders[1]->status)->toBe(Order::STATUS_OPEN);
});

test('best price order is matched first', function () {
    $seller1 = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller1->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $seller2 = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller1)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($seller2)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00',
        'amount' => '0.1',
    ]);

    $seller1Order = Order::where('user_id', $seller1->id)->first();
    $seller2Order = Order::where('user_id', $seller2->id)->first();

    expect($seller2Order->status)->toBe(Order::STATUS_FILLED);
    expect($seller1Order->status)->toBe(Order::STATUS_OPEN);
});

test('matched orders log commission on buyer-initiated match', function () {
    Log::spy();

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Order matched and settled'
                && $context['commission'] === '67.50000000';
        })
        ->once();
});

test('matched orders log commission on seller-initiated match', function () {
    Log::spy();

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);
    expect($response->json('order.status'))->toBe(Order::STATUS_FILLED);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Order matched and settled'
                && $context['commission'] === '67.50000000';
        })
        ->once();
});

test('commission equals 1.5 percent of trade value', function () {
    Log::spy();

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '100000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '50000.00',
        'amount' => '0.5',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '50000.00',
        'amount' => '0.5',
    ]);

    $response->assertStatus(201);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Order matched and settled'
                && $context['commission'] === '375.00000000';
        })
        ->once();
});

test('commission calculated with executed price from maker order on buyer-initiated match', function () {
    Log::spy();

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Order matched and settled'
                && $context['commission'] === '66.00000000'
                && $context['executed_price'] === '44000.00000000';
        })
        ->once();
});

test('commission calculated with executed price from maker order on seller-initiated match', function () {
    Log::spy();

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00',
        'amount' => '0.1',
    ]);

    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $response = $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Order matched and settled'
                && $context['commission'] === '69.00000000'
                && $context['executed_price'] === '46000.00000000';
        })
        ->once();
});

test('buyer asset amount increases after match', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response->assertStatus(201);

    $buyerAsset = Asset::where('user_id', $buyer->id)
        ->where('symbol', 'BTC')
        ->first();

    expect($buyerAsset)->not->toBeNull();
    expect($buyerAsset->amount)->toBe('0.10000000');
});

test('seller balance increases by amount times price minus commission after match', function () {
    $seller = User::factory()->create(['balance' => '1000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $seller->refresh();
    expect($seller->balance)->toBe('5432.50000000');
});

test('seller locked_amount decreases after match', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    $sellerAsset = Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $sellerAsset->refresh();
    expect($sellerAsset->locked_amount)->toBe('0.10000000');

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $sellerAsset->refresh();
    expect($sellerAsset->locked_amount)->toBe('0.00000000');
});

test('new asset created for buyer without existing asset', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    expect(Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->exists())->toBeFalse();

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
    expect($buyerAsset)->not->toBeNull();
    expect($buyerAsset->amount)->toBe('0.10000000');
    expect($buyerAsset->locked_amount)->toBe('0.00000000');
});

test('existing buyer asset incremented on match', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);
    $buyerAsset = Asset::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.00000000',
    ]);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $buyerAsset->refresh();
    expect($buyerAsset->amount)->toBe('0.60000000');
});

test('buyer USD balance unchanged after match since already deducted', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $buyer->refresh();
    expect($buyer->balance)->toBe('45500.00000000');
});
