<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    // make sure there is a user to login
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('secret123'),
    ]);
});

it('allows a user to login and receive a token', function () {
    /**@var \App\Models\User $user */
    $user = $this->user;
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data' => ['token']]);
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401);
});

it('returns user info with valid token', function () {
    /** @var \App\Models\User $user */
    $user = $this->user;
    $token = auth('api')->login($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.email', 'test@example.com');
});

it('allows logout and invalidates token', function () {
    /** @var \App\Models\User $user */
    $user = $this->user;
    $token = auth('api')->login($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->postJson('/api/logout');

    $response->assertStatus(200);

    // subsequent request with same token should fail
    $second = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->getJson('/api/me');

    $second->assertStatus(401);
});

it('can refresh the token', function () {
    /** @var \App\Models\User $user */
    $user = $this->user;
    $token = auth('api')->login($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->postJson('/api/refresh');

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data' => ['token']]);

    $newToken = $response->json('data.token');
    expect($newToken)->not->toBe($token);
});
