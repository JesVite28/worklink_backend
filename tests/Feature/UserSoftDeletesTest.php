<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('soft deletes a user via model and leaves record in database', function () {
    $user = User::factory()->create();

    $user->delete();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

it('soft deletes a user through the API endpoint and prevents returning it afterward', function () {
    $user = User::factory()->create();

    $token = auth('api')->login($user);
    $header = ['Authorization' => "Bearer $token"];

    $response = $this->withHeaders($header)->deleteJson("/api/users/{$user->id}");
    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    // record still exists but trashed
    $this->assertSoftDeleted('users', ['id' => $user->id]);

    // index should no longer return the user
    $list = $this->withHeaders($header)->getJson('/api/users');
    $list->assertJsonMissing(['id' => $user->id]);

    // show should return 404
    $this->withHeaders($header)->getJson("/api/users/{$user->id}")
         ->assertStatus(404);
});
