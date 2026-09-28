<?php

namespace App\Support;

/**
 * S4 — server-side password floor.
 *
 * Heuristic list of the most-breached passwords (top-of-list only; the
 * point is to reject "password", "12345678" and friends, not to maintain
 * a full HaveIBeenPwned mirror — that lands with the v1.7 trust work).
 * Containment checks (name/email in password) live in the controller since
 * they need the user context.
 */
final class PasswordPolicy
{
    /** Lowercased, length-8+ common passwords (the floor is 8 chars). */
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'password1234',
        'passw0rd', 'letmein1', 'qwerty12', 'qwerty123', '1q2w3e4r5t',
        'iloveyou1', 'admin1234', 'welcome1', 'monkey123', 'dragon123',
        'sunshine1', 'princess1', 'football1', 'baseball1', 'master123',
        'nepal123', 'nepal1234', 'kathmandu1', 'hello1234',
    ];

    public static function isCommon(string $password): bool
    {
        return in_array(strtolower($password), self::COMMON, true);
    }

    /**
     * Strength score 0–4 used by the client meter's server-side mirror:
     * length tiers + character-class mix + penalties.
     */
    public static function strength(string $password): int
    {
        $score = 0;
        $len = mb_strlen($password);

        if ($len >= 8) {
            $score++;
        }
        if ($len >= 12) {
            $score++;
        }

        $classes = preg_match('/[a-z]/', $password)
            + preg_match('/[A-Z]/', $password)
            + preg_match('/[0-9]/', $password)
            + preg_match('/[^a-zA-Z0-9]/', $password);

        if ($classes >= 3) {
            $score++;
        }
        if ($classes >= 4 && $len >= 10) {
            $score++;
        }

        // Penalties: sequences and repeats.
        if (preg_match('/(?:0123|1234|2345|3456|4567|5678|6789|abcd|qwer|asdf|zxcv)/i', $password)) {
            $score--;
        }
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score--;
        }

        return max(0, min(4, $score));
    }
}
