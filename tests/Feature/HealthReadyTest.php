<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('GET /health/ready mengembalikan 200 danstatus sehat', function () {
    $response = $this->get('/health/ready');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('db', 'ok')
        ->assertJsonStructure([
            'status',
            'db',
            'failed_jobs',
            'checked_at',
        ]);
});

it('/health/ready tidak memerlukan autentikasi', function () {
    $this->get('/health/ready')->assertOk();
});
