<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'balance' => '10000.00000000',
    ]);

    $response = $this->withHeaders([
        'Referer' => 'http://localhost',
    ])->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => 'test@example.com',
                'balance' => '10000.00000000',
            ],
        ]);
});

test('user cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid credentials',
        ]);
});

test('user cannot login with non-existent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid credentials',
        ]);
});

test('login requires email and password', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('authenticated user can access /api/user', function () {
    $user = User::factory()->create([
        'balance' => '5000.00000000',
    ]);

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => '5000.00000000',
        ]);
});

test('unauthenticated user receives 401 on protected routes', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

test('unauthenticated user receives 401 on logout', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});

test('user can logout successfully', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withHeaders([
        'Referer' => 'http://localhost',
    ])->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Logged out successfully',
        ]);
});

test('csrf cookie endpoint is available', function () {
    $response = $this->get('/sanctum/csrf-cookie');

    $response->assertStatus(204);
});

test('login endpoint is rate limited', function () {
    // Make 5 requests (the limit)
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
    }

    // 6th request should be rate limited
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
