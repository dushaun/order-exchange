<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order match transaction is atomic - both orders filled or neither', function () {
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

    $buyOrder = Order::where('user_id', $buyer->id)->first();
    $sellOrder = Order::where('user_id', $seller->id)->first();

    $buyOrder->refresh();
    $sellOrder->refresh();

    expect($buyOrder->status)->toBe(Order::STATUS_FILLED);
    expect($sellOrder->status)->toBe(Order::STATUS_FILLED);
});

test('no partial balance transfer on match - buyer and seller balances consistent', function () {
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
    $seller->refresh();

    $expectedBuyerBalance = '45500.00000000';
    $expectedSellerBalance = '14432.50000000';

    expect($buyer->balance)->toBe($expectedBuyerBalance);
    expect($seller->balance)->toBe($expectedSellerBalance);

    $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
    $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->first();

    expect($buyerAsset->amount)->toBe('0.10000000');
    expect($sellerAsset->amount)->toBe('0.90000000');
    expect($sellerAsset->locked_amount)->toBe('0.00000000');
});

test('concurrent order placement does not result in negative balance', function () {
    $user = User::factory()->create(['balance' => '5000.00000000']);

    $response1 = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '50000.00',
        'amount' => '0.1',
    ]);

    $user->refresh();

    $response2 = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '50000.00',
        'amount' => '0.1',
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(409);

    $user->refresh();
    expect(bccomp($user->balance, '0', 8))->toBeGreaterThanOrEqual(0);

    $orderCount = Order::where('user_id', $user->id)->count();
    expect($orderCount)->toBe(1);
});

test('concurrent sell orders do not exceed available assets', function () {
    $user = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.10000000',
        'locked_amount' => '0.00000000',
    ]);

    $response1 = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response2 = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(409);

    $asset = Asset::where('user_id', $user->id)->where('symbol', 'BTC')->first();
    $asset->refresh();

    expect(bccomp($asset->amount, '0', 8))->toBeGreaterThanOrEqual(0);
    expect(bccomp($asset->locked_amount, '0.10000000', 8))->toBeLessThanOrEqual(0);

    $orderCount = Order::where('user_id', $user->id)->count();
    expect($orderCount)->toBe(1);
});

test('database state is consistent after failed match attempt', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '1000.00000000']);

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

    $response->assertStatus(409);

    $buyer->refresh();
    $seller->refresh();

    expect($buyer->balance)->toBe('1000.00000000');
    expect($seller->balance)->toBe('10000.00000000');

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_OPEN);

    $buyOrderCount = Order::where('user_id', $buyer->id)->count();
    expect($buyOrderCount)->toBe(0);

    $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->first();
    expect($sellerAsset->amount)->toBe('0.90000000');
    expect($sellerAsset->locked_amount)->toBe('0.10000000');
});

test('all balance changes occur within single transaction', function () {
    $seller = User::factory()->create(['balance' => '5000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '1.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $initialSellerBalance = $seller->balance;
    $initialBuyerBalance = $buyer->balance;

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
    $seller->refresh();

    $tradeValue = bcmul('0.1', '45000.00', 8);
    $commission = bcmul($tradeValue, '0.015', 8);
    $sellerNet = bcsub($tradeValue, $commission, 8);

    $expectedBuyerBalance = bcsub($initialBuyerBalance, $tradeValue, 8);
    $expectedSellerBalance = bcadd($initialSellerBalance, $sellerNet, 8);

    expect($buyer->balance)->toBe($expectedBuyerBalance);
    expect($seller->balance)->toBe($expectedSellerBalance);
});

test('buyer balance deducted, asset credited atomically on match', function () {
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

    $buyer->refresh();
    $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();

    expect($buyer->balance)->toBe('45500.00000000');
    expect($buyerAsset)->not->toBeNull();
    expect($buyerAsset->amount)->toBe('0.10000000');

    $buyOrder = Order::where('user_id', $buyer->id)->first();
    expect($buyOrder->status)->toBe(Order::STATUS_FILLED);
});

test('seller locked_amount released, balance credited atomically on match', function () {
    $seller = User::factory()->create(['balance' => '5000.00000000']);
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
    expect($sellerAsset->amount)->toBe('0.90000000');

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    $seller->refresh();
    $sellerAsset->refresh();

    expect($sellerAsset->locked_amount)->toBe('0.00000000');
    expect($sellerAsset->amount)->toBe('0.90000000');

    $expectedBalance = bcadd('5000.00000000', bcsub('4500.00000000', '67.50000000', 8), 8);
    expect($seller->balance)->toBe($expectedBalance);

    $sellOrder = Order::where('user_id', $seller->id)->first();
    expect($sellOrder->status)->toBe(Order::STATUS_FILLED);
});

test('no orphaned locked funds after successful match', function () {
    $seller = User::factory()->create(['balance' => '10000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '100000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.5',
    ]);

    $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->first();
    $sellerAsset->refresh();

    expect($sellerAsset->locked_amount)->toBe('0.00000000');
    expect($sellerAsset->amount)->toBe('0.00000000');

    $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
    expect($buyerAsset->amount)->toBe('0.50000000');
    expect($buyerAsset->locked_amount)->toBe('0.00000000');

    $openOrders = Order::where('status', Order::STATUS_OPEN)->count();
    expect($openOrders)->toBe(0);
});

test('commission rounds correctly to 8 decimal places', function () {
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
        'price' => '12345.67',
        'amount' => '0.12345678',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '12345.67',
        'amount' => '0.12345678',
    ]);

    $seller->refresh();

    $tradeValue = bcmul('0.12345678', '12345.67', 8);
    $commission = bcmul($tradeValue, '0.015', 8);
    $sellerNet = bcsub($tradeValue, $commission, 8);

    expect(strlen(explode('.', $commission)[1] ?? ''))->toBeLessThanOrEqual(8);
    expect(strlen(explode('.', $sellerNet)[1] ?? ''))->toBeLessThanOrEqual(8);

    $expectedBalance = bcadd('10000.00000000', $sellerNet, 8);
    expect($seller->balance)->toBe($expectedBalance);
});

test('commission deducted from seller receives correct net amount', function () {
    $seller = User::factory()->create(['balance' => '0.00000000']);
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
        'amount' => '1.0',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '50000.00',
        'amount' => '1.0',
    ]);

    $seller->refresh();

    $tradeValue = '50000.00000000';
    $commission = bcmul($tradeValue, '0.015', 8);
    $expectedNet = bcsub($tradeValue, $commission, 8);

    expect($commission)->toBe('750.00000000');
    expect($expectedNet)->toBe('49250.00000000');
    expect($seller->balance)->toBe($expectedNet);
});

test('small trade commission calculated correctly', function () {
    $seller = User::factory()->create(['balance' => '1000.00000000']);
    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => '0.00100000',
        'locked_amount' => '0.00000000',
    ]);

    $buyer = User::factory()->create(['balance' => '10000.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '100.00',
        'amount' => '0.001',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '100.00',
        'amount' => '0.001',
    ]);

    $seller->refresh();

    $tradeValue = bcmul('0.001', '100.00', 8);
    $commission = bcmul($tradeValue, '0.015', 8);
    $sellerNet = bcsub($tradeValue, $commission, 8);

    expect($tradeValue)->toBe('0.10000000');
    expect($commission)->toBe('0.00150000');
    expect($sellerNet)->toBe('0.09850000');

    $expectedBalance = bcadd('1000.00000000', $sellerNet, 8);
    expect($seller->balance)->toBe($expectedBalance);
});
