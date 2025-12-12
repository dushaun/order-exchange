<?php

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\AssetSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('UserSeeder creates demo users with correct credentials', function () {
    $this->seed(UserSeeder::class);

    $user1 = User::where('email', 'user1@test.com')->first();
    $user2 = User::where('email', 'user2@test.com')->first();

    expect($user1)->not->toBeNull();
    expect($user1->name)->toBe('Test User 1');
    expect(Hash::check('password', $user1->password))->toBeTrue();

    expect($user2)->not->toBeNull();
    expect($user2->name)->toBe('Test User 2');
    expect(Hash::check('password', $user2->password))->toBeTrue();
});

test('UserSeeder sets correct starting balance', function () {
    $this->seed(UserSeeder::class);

    $user1 = User::where('email', 'user1@test.com')->first();
    $user2 = User::where('email', 'user2@test.com')->first();

    expect($user1->balance)->toBe('10000.00000000');
    expect($user2->balance)->toBe('10000.00000000');
});

test('AssetSeeder creates BTC and ETH assets for demo users', function () {
    $this->seed(UserSeeder::class);
    $this->seed(AssetSeeder::class);

    $user1 = User::where('email', 'user1@test.com')->first();
    $user2 = User::where('email', 'user2@test.com')->first();

    $user1Btc = Asset::where('user_id', $user1->id)->where('symbol', 'BTC')->first();
    $user1Eth = Asset::where('user_id', $user1->id)->where('symbol', 'ETH')->first();
    $user2Btc = Asset::where('user_id', $user2->id)->where('symbol', 'BTC')->first();
    $user2Eth = Asset::where('user_id', $user2->id)->where('symbol', 'ETH')->first();

    expect($user1Btc)->not->toBeNull();
    expect($user1Eth)->not->toBeNull();
    expect($user2Btc)->not->toBeNull();
    expect($user2Eth)->not->toBeNull();
});

test('AssetSeeder sets correct initial amounts', function () {
    $this->seed(UserSeeder::class);
    $this->seed(AssetSeeder::class);

    $user1 = User::where('email', 'user1@test.com')->first();

    $btc = Asset::where('user_id', $user1->id)->where('symbol', 'BTC')->first();
    $eth = Asset::where('user_id', $user1->id)->where('symbol', 'ETH')->first();

    expect($btc->amount)->toBe('1.00000000');
    expect($btc->locked_amount)->toBe('0.00000000');
    expect($eth->amount)->toBe('10.00000000');
    expect($eth->locked_amount)->toBe('0.00000000');
});

test('seeders are idempotent and do not create duplicates', function () {
    // First run
    $this->seed(UserSeeder::class);
    $this->seed(AssetSeeder::class);

    $usersCountFirst = User::whereIn('email', ['user1@test.com', 'user2@test.com'])->count();
    $assetsCountFirst = Asset::count();

    // Second run
    $this->seed(UserSeeder::class);
    $this->seed(AssetSeeder::class);

    $usersCountSecond = User::whereIn('email', ['user1@test.com', 'user2@test.com'])->count();
    $assetsCountSecond = Asset::count();

    expect($usersCountFirst)->toBe(2);
    expect($usersCountSecond)->toBe(2);
    expect($assetsCountFirst)->toBe(4);
    expect($assetsCountSecond)->toBe(4);
});
