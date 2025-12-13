<?php

use App\Models\Order;
use App\Models\User;
use App\Services\OrderMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->matchingService = new OrderMatchingService;
});

test('buy order matches sell order with equal price', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($sellOrder->id);
});

test('buy order matches sell order with lower price', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($sellOrder->id);
});

test('sell order matches buy order with equal price', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($sellOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($buyOrder->id);
});

test('sell order matches buy order with higher price', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($sellOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($buyOrder->id);
});

test('no match when buy price is lower than sell price', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '46000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('no match when sell price is higher than buy price', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '44000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($sellOrder);

    expect($match)->toBeNull();
});

test('no match when amounts differ', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.20000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('oldest compatible order is matched first (FIFO)', function () {
    $seller1 = User::factory()->create();
    $seller2 = User::factory()->create();
    $buyer = User::factory()->create();

    $olderSellOrder = Order::factory()->create([
        'user_id' => $seller1->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(10),
    ]);

    Order::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(5),
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($olderSellOrder->id);
});

test('best price order is matched first for buy orders', function () {
    $seller1 = User::factory()->create();
    $seller2 = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller1->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(10),
    ]);

    $cheaperSellOrder = Order::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(5),
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($cheaperSellOrder->id);
});

test('best price order is matched first for sell orders', function () {
    $buyer1 = User::factory()->create();
    $buyer2 = User::factory()->create();
    $seller = User::factory()->create();

    Order::factory()->create([
        'user_id' => $buyer1->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(10),
    ]);

    $higherBuyOrder = Order::factory()->create([
        'user_id' => $buyer2->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '46000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
        'created_at' => now()->subMinutes(5),
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '44000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($sellOrder);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($higherBuyOrder->id);
});

test('does not match orders from the same user', function () {
    $user = User::factory()->create();

    Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('does not match filled orders', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_FILLED,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('does not match cancelled orders', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_CANCELLED,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('does not match orders with different symbols', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'ETH',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $match = $this->matchingService->findMatchingOrder($buyOrder);

    expect($match)->toBeNull();
});

test('executeMatch updates both orders to filled status', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00000000',
        'amount' => '0.10000000',
        'status' => Order::STATUS_OPEN,
    ]);

    $this->matchingService->executeMatch($buyOrder, $sellOrder);

    $buyOrder->refresh();
    $sellOrder->refresh();

    expect($buyOrder->status)->toBe(Order::STATUS_FILLED);
    expect($sellOrder->status)->toBe(Order::STATUS_FILLED);
});
