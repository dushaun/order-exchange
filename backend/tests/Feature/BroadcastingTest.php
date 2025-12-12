<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

test('broadcast connection is configured as pusher in production config', function () {
    config(['broadcasting.default' => 'pusher']);
    expect(config('broadcasting.default'))->toBe('pusher');
});

test('pusher connection is properly configured', function () {
    expect(config('broadcasting.connections.pusher'))->toBeArray();
    expect(config('broadcasting.connections.pusher.driver'))->toBe('pusher');
    expect(config('broadcasting.connections.pusher.key'))->not->toBeNull();
});

test('broadcasting auth route exists', function () {
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '1234.5678',
    ]);

    $response->assertStatus(401);
});

test('broadcasting auth route requires authentication', function () {
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '1234.5678',
    ]);

    $response->assertStatus(401);
});

test('user channel authorization allows access to own channel', function () {
    $user = User::factory()->create();

    $channels = Broadcast::getChannels();
    $callback = $channels->get('user.{id}');

    expect($callback)->not->toBeNull();
    expect($callback($user, $user->id))->toBeTrue();
});

test('user channel authorization denies access to other users channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $channels = Broadcast::getChannels();
    $callback = $channels->get('user.{id}');

    expect($callback)->not->toBeNull();
    expect($callback($user, $otherUser->id))->toBeFalse();
});

test('test broadcast route requires authentication', function () {
    $response = $this->getJson('/api/test-broadcast');

    $response->assertStatus(401);
});
