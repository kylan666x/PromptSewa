<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Smoke Test
|--------------------------------------------------------------------------
|
| Production uses the `database` session driver (cPanel constraint), so
| feature tests migrate the DB first via RefreshDatabase.
|
*/

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
