{{--
    A1 (v1.7.6) — plain-text fallback for the reset email. Rendered by
    MailChannel alongside the HTML part, so a text-only client (or a
    reader with images off) still receives the working link.

    @var string $url
    @var int    $minutes
    @var string $siteName
    @var string $name
--}}
Hi {{ $name }},

We received a request to reset the password on your {{ $siteName }} account.
Open this link to choose a new one:

{{ $url }}

The link works once and expires in {{ $minutes }} minutes.

Did not ask for this? Nothing has changed on your account — ignore this email
and your old password stays active.

— {{ $siteName }}