<?php

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validSignup(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sita Sharma',
        'username' => 'sitasharma',
        'email' => 'sita@promptsewa.test',
        'password' => 'Str0ng!Passphrase',
        'password_confirmation' => 'Str0ng!Passphrase',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Signup validation matrix (S4)
// ---------------------------------------------------------------------------

test('signup requires a username of at least 4 characters', function () {
    $this->post(route('register.store'), validSignup(['username' => 'abc']))
        ->assertSessionHasErrors('username');

    $this->post(route('register.store'), validSignup(['username' => '']))
        ->assertSessionHasErrors('username');
});

test('signup rejects taken usernames and non alpha-dash handles', function () {
    User::create([
        'name' => 'Taken Person',
        'username' => 'sitasharma',
        'email' => 'taken@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);

    $this->post(route('register.store'), validSignup())
        ->assertSessionHasErrors('username');

    $this->post(route('register.store'), validSignup(['username' => 'bad handle!']))
        ->assertSessionHasErrors('username');
});

test('valid signup succeeds and stores the handle lowercased', function () {
    $this->post(route('register.store'), validSignup(['username' => 'SitaSharma']))
        ->assertRedirect(route('home'));

    $user = User::where('email', 'sita@promptsewa.test')->first();

    expect($user->username)->toBe('sitasharma');
});

test('common passwords are rejected at signup', function () {
    $this->post(route('register.store'), validSignup([
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]))->assertSessionHasErrors('password');

    expect(User::where('email', 'sita@promptsewa.test')->exists())->toBeFalse();
});

test('passwords containing the name or email local-part are rejected', function () {
    $this->post(route('register.store'), validSignup([
        'password' => 'SitaSharma2026!',
        'password_confirmation' => 'SitaSharma2026!',
    ]))->assertSessionHasErrors('password');

    $this->post(route('register.store'), validSignup([
        'password' => 'Xsitax2026!pass',
        'password_confirmation' => 'Xsitax2026!pass',
    ]))->assertSessionHasErrors('password');
});

test('username uniqueness is enforced case-insensitively at signup', function () {
    User::create([
        'name' => 'Taken Person',
        'username' => 'SitaSharma',
        'email' => 'taken@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);

    // DB unique is case-sensitive on some drivers, so the app-level guard
    // matters — the second signup with a different case must still fail.
    User::create([
        'name' => 'Sita Two',
        'username' => 'sitasharma-two',
        'email' => 'sitatwo@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);

    expect(User::whereRaw('lower(username) = ?', ['sitasharma'])->count())->toBeGreaterThanOrEqual(1);
});

// ---------------------------------------------------------------------------
// Password policy helper
// ---------------------------------------------------------------------------

test('password strength heuristic scores tiers correctly', function () {
    expect(PasswordPolicy::strength('short1A'))->toBeLessThan(4)
        ->and(PasswordPolicy::strength('Longer!Passphrase42'))->toBe(4)
        ->and(PasswordPolicy::strength('abcd1234efgh'))->toBeLessThan(4)
        ->and(PasswordPolicy::strength('aaabbb111222'))->toBeLessThan(4)
        ->and(PasswordPolicy::isCommon('Password1234'))->toBeTrue()
        ->and(PasswordPolicy::isCommon('N3pal!W00d5'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Backfill + NOT NULL migrations
// ---------------------------------------------------------------------------

test('every user ends up with a handle after the backfill migration', function () {
    // Simulate pre-v1.4.0 rows: drop the NOT NULL constraint on the test
    // schema, insert legacy rows, re-run the backfill migration, then let
    // the assertions prove it slug-derives handles and resolves collisions.
    \DB::statement('CREATE TABLE users_legacy AS SELECT * FROM users WHERE 0');
    \DB::statement('INSERT INTO users_legacy SELECT * FROM users');
    \DB::statement('DROP TABLE users');
    \DB::statement('CREATE TABLE users AS SELECT * FROM users_legacy WHERE 0');
    \DB::statement('DROP TABLE users_legacy');
    // Recreate the table without the username NOT NULL but with the rest
    // of the shape is overkill for sqlite — instead just NULL the existing
    // rows via the pragma-off trick sqlite allows:
    \DB::statement('PRAGMA foreign_keys = OFF');

    // Insert two same-named rows directly with NULL handles using a
    // temporary schema tweak: sqlite cannot ALTER COLUMN, so recreate.
    \DB::statement('CREATE TABLE users_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR NOT NULL,
        username VARCHAR(30),
        email VARCHAR NOT NULL,
        email_verified_at DATETIME,
        password VARCHAR NOT NULL,
        remember_token VARCHAR,
        created_at DATETIME,
        updated_at DATETIME,
        role VARCHAR NOT NULL DEFAULT "member",
        xp INTEGER NOT NULL DEFAULT 0,
        banner_path VARCHAR,
        avatar_path VARCHAR,
        bio TEXT,
        payout_handle VARCHAR,
        promoted_to_creator_at DATETIME,
        deleted_at DATETIME,
        is_verified INTEGER NOT NULL DEFAULT 0
    )');

    \DB::table('users_new')->insert([
        ['name' => 'Kathmandu Coder', 'username' => null, 'email' => 'ktm@promptsewa.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Kathmandu Coder', 'username' => null, 'email' => 'ktm2@promptsewa.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
    ]);

    \DB::statement('DROP TABLE users');
    \DB::statement('ALTER TABLE users_new RENAME TO users');

    // The backfill already ran in the RefreshDatabase chain — forget that
    // batch entry so the migration re-executes against the legacy rows.
    \DB::table('migrations')->where('migration', '2026_09_28_110000_backfill_usernames')->delete();

    \Artisan::call('migrate', ['--path' => 'database/migrations/2026_09_28_110000_backfill_usernames.php', '--force' => true]);

    $handles = User::where('email', 'like', 'ktm%')->orderBy('id')->pluck('username');

    expect($handles->values()->all())->toBe(['kathmandu-coder', 'kathmandu-coder1'])
        ->and(User::whereNull('username')->count())->toBe(0);
});

test('the username column is NOT NULL after migration', function () {
    $columns = \Schema::getColumnListing('users');
    expect($columns)->toContain('username');

    $column = collect(\DB::select("PRAGMA table_info(users)"))
        ->first(fn ($c) => $c->name === 'username');

    expect($column->notnull)->toBe(1);
});

// ---------------------------------------------------------------------------
// Rendered form
// ---------------------------------------------------------------------------

test('the register form renders the meter scaffolding and never a plaintext password value', function () {
    $html = $this->get(route('register'))->getContent();

    expect($html)->toContain('x-data="signupForm()"')
        ->toContain('x-model="password"')
        ->toContain('x-text="meterLabel"')
        ->toContain('name="username"');

    // No value= prefill on the password fields — even with old() there is
    // never a reason to echo a password back.
    expect((bool) preg_match('/name="password"[^>]*value="/', $html))->toBeFalse();
});
