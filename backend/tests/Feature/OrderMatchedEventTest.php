<?php

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('OrderMatched event is dispatched after successful match', function () {
    Event::fake([OrderMatched::class]);

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

    Event::assertDispatched(OrderMatched::class, function ($event) use ($buyer, $seller) {
        return $event->buyerId === $buyer->id
            && $event->sellerId === $seller->id;
    });
});

test('OrderMatched event is not dispatched when no match occurs', function () {
    Event::fake([OrderMatched::class]);

    $buyer = User::factory()->create(['balance' => '50000.00000000']);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '45000.00',
        'amount' => '0.1',
    ]);

    Event::assertNotDispatched(OrderMatched::class);
});

test('OrderMatched broadcasts to correct private channels', function () {
    Event::fake([OrderMatched::class]);

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

    Event::assertDispatched(OrderMatched::class, function ($event) use ($buyer, $seller) {
        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($ch) => $ch->name, $channels);

        return in_array('private-user.'.$buyer->id, $channelNames)
            && in_array('private-user.'.$seller->id, $channelNames);
    });
});

test('OrderMatched payload contains required order and balance data', function () {
    Event::fake([OrderMatched::class]);

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

    Event::assertDispatched(OrderMatched::class, function ($event) {
        $payload = $event->broadcastWith();

        return isset($payload['order_details'])
            && isset($payload['buyer'])
            && isset($payload['seller'])
            && isset($payload['order_details']['symbol'])
            && isset($payload['order_details']['executed_price'])
            && isset($payload['order_details']['commission'])
            && isset($payload['buyer']['balance'])
            && isset($payload['buyer']['assets'])
            && isset($payload['buyer']['order'])
            && isset($payload['seller']['balance'])
            && isset($payload['seller']['assets'])
            && isset($payload['seller']['order']);
    });
});
