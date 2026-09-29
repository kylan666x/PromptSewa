<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * T1 (v1.5.0) — official house account, one-shot idempotent.
 *
 * Creates (or flags) the `promptsewa` house account: role admin,
 * verified, is_official, deterministic email + password, default avatar
 * copied from the current brand mark. Production-safe: this is NOT a
 * demo seeder and the D4 gate does not apply — the house account is a
 * permanent first-party identity. The founder uploads the real photo
 * afterwards via Admin → Users.
 */
class OfficialAccount extends Command
{
    protected $signature = 'pv:official-account
        {--password= : Password for the account (default: random, printed once)}';

    protected $description = 'Create or flag the official PromptSewa house account (T1) — idempotent';

    private const USERNAME = 'promptsewa';

    private const EMAIL = 'official@promptsewa.test';

    public function handle(): int
    {
        $user = User::query()->where('username', self::USERNAME)->first();

        if ($user === null) {
            $password = (string) ($this->option('password') ?: bin2hex(random_bytes(12)));

            $user = DB::transaction(function () use ($password) {
                return User::create([
                    'name' => 'PromptSewa',
                    'username' => self::USERNAME,
                    'email' => self::EMAIL,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_ADMIN,
                    'is_verified' => true,
                    'is_official' => true,
                    'bio' => 'The official PromptSewa account — house packs, announcements and curated picks.',
                ]);
            });

            $this->info('Official account created (id '.$user->id.').');
            $this->line('  email:    '.self::EMAIL);
            $this->line('  password: '.$password.'  ← copy this NOW, it is not stored in plain text.');
        } else {
            // Idempotent: flag an existing account rather than fail.
            $user->forceFill([
                'role' => User::ROLE_ADMIN,
                'is_verified' => true,
                'is_official' => true,
            ])->save();

            $this->info('Existing user "'.$user->username.'" (id '.$user->id.') flagged as the official account.');
        }

        // Default avatar: copy the current brand mark onto the public disk
        // (founder uploads the real photo later via Admin → Users).
        if ($user->avatar_path === null) {
            $brandMark = (string) app(\App\Services\SettingsService::class)->get('brand_mark_path', '');

            if ($brandMark !== '' && Storage::disk('public')->exists($brandMark)) {
                $extension = pathinfo($brandMark, PATHINFO_EXTENSION) ?: 'png';
                $target = 'avatars/official-promptsewa.'.$extension;
                Storage::disk('public')->copy($brandMark, $target);
                $user->forceFill(['avatar_path' => $target])->save();
                $this->line('  avatar:  brand mark copied to '.$target);
            } else {
                $this->line('  avatar:  no brand mark configured — initials badge shows until the founder uploads a photo.');
            }
        }

        return self::SUCCESS;
    }
}
